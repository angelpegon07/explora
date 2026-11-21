<?php

namespace Tests\Feature;

use App\Models\Explora\PerfilExplora;
use App\Models\Pedigital\Usuario;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\RespaldaPerfiles;
use Tests\TestCase;

/**
 * Fase 4: autenticación contra pedigital.users (solo lectura) con sesión de servidor (cookie HttpOnly).
 * Cierra SEC-03 (sin sesión / IDOR en el avatar), SEC-04 (hash en la respuesta, enumeración, sin límite) y DAT-01 (estado).
 */
class AutenticacionTest extends TestCase
{
    use RespaldaPerfiles;

    /** @var array<string, string> Cookies recibidas del servidor, que se reenvían para simular el navegador. */
    private array $cookies = [];

    /**
     * Respalda los perfiles de los usuarios de prueba antes de cada prueba (ver {@see RespaldaPerfiles}).
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->respaldarPerfiles();
    }

    /**
     * Borra los perfiles creados por las pruebas para dejar `perfiles_explora` como estaba.
     */
    protected function tearDown(): void
    {
        $this->restaurarPerfiles();
        parent::tearDown();
    }

    private function entrar(string $usuario = 'usuario1', string $clave = 'password'): TestResponse
    {
        $r = $this->postJson('/api/sesion', ['usuario' => $usuario, 'contrasena' => $clave]);
        foreach ($r->headers->getCookies() as $c) {
            $this->cookies[$c->getName()] = $c->getValue();
        }

        return $r;
    }

    private function conSesion(): static
    {
        foreach ($this->cookies as $nombre => $valor) {
            $this->withUnencryptedCookie($nombre, $valor);
        }

        return $this;
    }

    /** Ejecuta $fn con una conexión de escritura a pedigital_test (solo para preparar escenarios de prueba). */
    private function conEscrituraEnPedigitalTest(callable $fn): void
    {
        // Falla (no se omite): sin esta credencial DAT-01 quedaría sin verificar sin que nadie lo notara.
        if (! env('LEGACY_DB_USER')) {
            $this->fail('Faltan LEGACY_DB_USER/LEGACY_DB_PASS en .env: son necesarios para preparar escenarios en pedigital_test.');
        }
        $cfg = config('database.connections.pedigital');
        $this->assertStringEndsWith('_test', $cfg['database']);
        config(['database.connections.pedigital_rw' => array_merge($cfg, ['username' => env('LEGACY_DB_USER'), 'password' => env('LEGACY_DB_PASS')])]);
        $fn(DB::connection('pedigital_rw'));
    }

    /**
     * SEC-04: el login devuelve exactamente 6 campos y nunca hash, correo ni token.
     */
    public function test_login_correcto_devuelve_solo_los_campos_que_necesita_el_frontend(): void
    {
        $r = $this->entrar();
        $r->assertOk()->assertJsonPath('success', true);
        $u = $r->json('usuario_objeto');
        $this->assertEqualsCanonicalizing(
            ['id', 'nombre_usuario', 'login_usuario', 'grado_usuario', 'avatar_explora', 'tipo_usuario'],
            array_keys($u)
        );
        $this->assertSame('usuario1', $u['login_usuario']);
        // SEC-04: nunca el hash, el correo ni el token.
        $this->assertStringNotContainsString('$2y$', $r->getContent());
        $this->assertStringNotContainsString('pasword', $r->getContent());
        $this->assertStringNotContainsString('@example.test', $r->getContent());
        $this->assertNotEmpty($this->cookies, 'se esperaba cookie de sesión');
    }

    /**
     * La cookie de sesión es HttpOnly y SameSite=Lax (no la lee JavaScript).
     */
    public function test_la_cookie_de_sesion_es_httponly(): void
    {
        $r = $this->postJson('/api/sesion', ['usuario' => 'usuario1', 'contrasena' => 'password']);
        $sesion = collect($r->headers->getCookies())->first(fn ($c) => str_contains($c->getName(), 'session'));
        $this->assertNotNull($sesion);
        $this->assertTrue($sesion->isHttpOnly());
        $this->assertSame('lax', $sesion->getSameSite());
    }

    /**
     * GET /api/sesion devuelve al usuario con la cookie de sesión (reemplaza a localStorage).
     */
    public function test_la_sesion_persiste_y_se_puede_consultar(): void
    {
        $this->entrar()->assertOk();
        $this->conSesion()->getJson('/api/sesion')->assertOk()
            ->assertJsonPath('usuario_objeto.login_usuario', 'usuario1')
            ->assertJsonMissingPath('usuario_objeto.pasword_usuario');
    }

    /**
     * Sin sesión, GET /api/sesion responde 401 con `success:false`.
     */
    public function test_sin_sesion_se_responde_401_json(): void
    {
        $this->getJson('/api/sesion')->assertStatus(401)->assertJsonPath('success', false);
    }

    /**
     * SEC-04: ambos casos devuelven 401 con el mismo cuerpo, así no se pueden enumerar usuarios.
     */
    public function test_usuario_inexistente_y_clave_incorrecta_son_indistinguibles(): void
    {
        $mal = $this->entrar('usuario1', 'incorrecta');
        $no = $this->entrar('no_existe_zz', 'incorrecta');
        $mal->assertStatus(401);
        $no->assertStatus(401);
        $this->assertSame($mal->json(), $no->json(), 'SEC-04: la respuesta no debe permitir enumerar usuarios');
        $this->assertFalse($mal->json('success'));
    }

    /**
     * Validación: faltan campos o `usuario` no es texto → 422 (y no un error 500).
     */
    public function test_exige_usuario_y_contrasena(): void
    {
        $this->postJson('/api/sesion', ['usuario' => 'usuario1'])->assertStatus(422)->assertJsonPath('success', false);
        $this->postJson('/api/sesion', [])->assertStatus(422);
        $this->postJson('/api/sesion', ['usuario' => ['x'], 'contrasena' => 'y'])->assertStatus(422);
    }

    /**
     * DAT-01: una cuenta con `estado_usuario` distinto de ACTIVO recibe 401. Cambia el estado en pedigital_test y lo
     * restaura.
     */
    public function test_un_usuario_inactivo_no_puede_entrar(): void
    {
        $this->conEscrituraEnPedigitalTest(function ($db) {
            try {
                $db->table('users')->where('id', 2)->update(['estado_usuario' => 'INACTIVO']);
                $this->entrar('usuario2')->assertStatus(401);
                $db->table('users')->where('id', 2)->update(['estado_usuario' => 'ACTIVO']);
                $this->entrar('usuario2')->assertOk();
            } finally {
                $db->table('users')->where('id', 2)->update(['estado_usuario' => 'ACTIVO']);
            }
        });
    }

    /**
     * SEC-04: tras 5 intentos/min de la misma pareja usuario+IP responde 429 con Retry-After, sin bloquear a otros
     * usuarios.
     */
    public function test_limita_los_intentos_de_inicio_de_sesion(): void
    {
        $login = 'bloqueo_'.uniqid();
        for ($i = 0; $i < 5; $i++) {
            $this->entrar($login, 'x')->assertStatus(401);
        }
        $r = $this->entrar($login, 'x');
        $r->assertStatus(429)->assertJsonPath('success', false);
        $this->assertNotNull($r->headers->get('Retry-After'));
        // El bloqueo es por usuario+IP: otro usuario sigue pudiendo entrar.
        $this->entrar('usuario1')->assertOk();
    }

    /**
     * DELETE /api/sesion cierra la sesión (la siguiente consulta da 401) y no escribe en pedigital.
     */
    public function test_cerrar_sesion_invalida_la_sesion_sin_escribir_en_pedigital(): void
    {
        $this->entrar()->assertOk();
        $this->conSesion()->deleteJson('/api/sesion')->assertOk()->assertJsonPath('success', true);
        $this->conSesion()->getJson('/api/sesion')->assertStatus(401);
    }

    /** CSRF: toda escritura con sesión exige el token (SEC-10). */
    public function test_csrf_se_exige_en_produccion(): void
    {
        // Laravel desactiva CSRF cuando corre bajo pruebas; se simula un entorno real para comprobarlo.
        $this->app['env'] = 'local';
        $this->postJson('/api/sesion', ['usuario' => 'usuario1', 'contrasena' => 'password'])->assertStatus(419);

        // Con sesión pero sin token CSRF, la escritura se rechaza; con el token correcto se acepta.
        // (Sin sesión la ruta responde 401 antes de evaluar CSRF: Laravel ordena `Authenticate` antes que
        // VerifyCsrfToken en $middlewarePriority. Comprobado también por HTTP real en la Fase 4. Con sesión válida y
        // token distinto sí responde 419.)
        $usuario = Usuario::findOrFail(1);
        $this->actingAs($usuario, 'web')->patchJson('/api/perfil/avatar', ['avatar' => 3])->assertStatus(419);
        $this->actingAs($usuario, 'web')->withSession(['_token' => 'tok'])->withHeader('X-CSRF-TOKEN', 'tok')
            ->patchJson('/api/perfil/avatar', ['avatar' => 3])->assertOk();
    }

    /**
     * SEC-03: sin sesión el cambio de avatar responde 401 y no crea ningún perfil.
     */
    public function test_el_avatar_requiere_sesion(): void
    {
        $this->patchJson('/api/perfil/avatar', ['avatar' => 3])->assertStatus(401);
        $this->assertNull(PerfilExplora::where('usuario_id', 1)->first());
    }

    /**
     * El avatar queda en `perfiles_explora`, se refleja en la sesión y `pedigital.users` no cambia.
     */
    public function test_el_usuario_cambia_su_propio_avatar_sin_tocar_pedigital(): void
    {
        $antes = (int) Usuario::findOrFail(1)->avatar_explora;
        $this->entrar()->assertOk();
        $this->conSesion()->patchJson('/api/perfil/avatar', ['avatar' => 7])->assertOk()
            ->assertJsonPath('status', 'success')->assertJsonPath('avatar', 7);

        $this->assertSame(7, PerfilExplora::avatarDe(1));
        $this->assertSame($antes, (int) Usuario::findOrFail(1)->avatar_explora, 'pedigital queda intacto');
        $this->conSesion()->getJson('/api/sesion')->assertJsonPath('usuario_objeto.avatar_explora', 7);
    }

    /**
     * SEC-03: aunque el cuerpo traiga `usuario_id`/`id_usuario` de otro, solo cambia el avatar del usuario de la
     * sesión.
     */
    public function test_no_se_puede_cambiar_el_avatar_de_otro_usuario(): void
    {
        // SEC-03: el id viene de la sesión, no del cliente.
        $this->entrar()->assertOk();
        $this->conSesion()->patchJson('/api/perfil/avatar', ['avatar' => 9, 'usuario_id' => 2, 'id_usuario' => 2])->assertOk();
        $this->assertSame(9, PerfilExplora::avatarDe(1));
        $this->assertNull(PerfilExplora::where('usuario_id', 2)->first());
    }

    /**
     * Valores no válidos (0, negativos, > 35, texto, null, inyección, decimales) → 422 y no se guarda nada.
     */
    public function test_avatar_invalido_se_rechaza(): void
    {
        $this->entrar()->assertOk();
        foreach ([0, -1, 36, 999, 'abc', null, '3; DROP TABLE users', 2.5] as $malo) {
            $this->conSesion()->patchJson('/api/perfil/avatar', ['avatar' => $malo])->assertStatus(422);
        }
        $this->assertNull(PerfilExplora::where('usuario_id', 1)->first());
    }

    /**
     * Usuario implementa Authenticatable pero no usa «recordarme» (no escribe en pedigital al cerrar sesión).
     */
    public function test_el_usuario_de_pedigital_es_autenticable_y_sigue_siendo_de_solo_lectura(): void
    {
        $u = Usuario::findOrFail(1);
        $this->assertInstanceOf(Authenticatable::class, $u);
        $this->assertNull($u->getRememberToken(), 'no se usa «recordarme»: evita escrituras en pedigital al cerrar sesión');
        $u->setRememberToken('x');
        $this->assertNull($u->getRememberToken());
    }

    /**
     * Humo: el limitador `login` existe (el comportamiento real lo prueba
     * test_limita_los_intentos_de_inicio_de_sesion).
     */
    public function test_el_limitador_de_login_esta_registrado(): void
    {
        $this->assertNotNull(app(RateLimiter::class)->limiter('login'));
    }

    /** Rotar el id de sesión al iniciar sesión impide la fijación de sesión (el atacante no conoce el id nuevo). */
    public function test_el_login_regenera_el_id_de_sesion(): void
    {
        $antes = $this->postJson('/api/sesion', ['usuario' => 'usuario1', 'contrasena' => 'incorrecta']);
        $idAntes = $this->idDeSesion($antes);
        $this->assertNotNull($idAntes);

        // Se reenvía la cookie de la sesión previa (la que un atacante pudo haber fijado).
        $this->withUnencryptedCookie(config('session.cookie'), $this->cookieDeSesion($antes));
        $despues = $this->postJson('/api/sesion', ['usuario' => 'usuario1', 'contrasena' => 'password'])->assertOk();

        $this->assertNotSame($idAntes, $this->idDeSesion($despues), 'el id de sesión debe cambiar al autenticarse');
    }

    /** El cierre de sesión (DELETE) también exige token CSRF: sin él 419, con él 200. */
    public function test_csrf_tambien_protege_el_cierre_de_sesion(): void
    {
        $this->app['env'] = 'local';
        $usuario = Usuario::findOrFail(1);

        $this->actingAs($usuario, 'web')->deleteJson('/api/sesion')->assertStatus(419);
        $this->actingAs($usuario, 'web')->withSession(['_token' => 'tok'])->withHeader('X-CSRF-TOKEN', 'tok')
            ->deleteJson('/api/sesion')->assertOk();
    }

    /**
     * Límite por CUENTA (SEC-04): 20 fallos en 15 min bloquean la cuenta aunque el atacante cambie de IP o varíe
     * mayúsculas. Una vez bloqueada, ni la contraseña correcta entra (se comprueba el límite antes que la clave).
     */
    public function test_el_limite_por_cuenta_no_se_esquiva_cambiando_de_ip_ni_de_mayusculas(): void
    {
        $variantes = ['usuario1', 'USUARIO1', 'Usuario1'];
        for ($i = 0; $i < 20; $i++) {
            $this->desdeLaIp("10.1.0.$i")->postJson('/api/sesion', ['usuario' => $variantes[$i % 3], 'contrasena' => 'mal'])
                ->assertStatus(401);
        }

        $r = $this->desdeLaIp('10.1.0.99')->postJson('/api/sesion', ['usuario' => 'usuario1', 'contrasena' => 'password']);
        $r->assertStatus(429)->assertJsonPath('success', false);
        $this->assertNotNull($r->headers->get('Retry-After'));
        app(RateLimiter::class)->clear('login-cuenta:1');
    }

    /** Un nombre inexistente se limita igual que uno real (para no revelar cuáles existen) y sus variantes comparten contador. */
    public function test_el_limite_por_cuenta_tambien_aplica_a_usuarios_inexistentes(): void
    {
        $variantes = ['fantasma_zz', 'FANTASMA_ZZ'];
        for ($i = 0; $i < 20; $i++) {
            $this->desdeLaIp("10.2.0.$i")->postJson('/api/sesion', ['usuario' => $variantes[$i % 2], 'contrasena' => 'mal'])
                ->assertStatus(401);
        }
        $this->desdeLaIp('10.2.0.99')->postJson('/api/sesion', ['usuario' => 'Fantasma_ZZ', 'contrasena' => 'mal'])
            ->assertStatus(429);
    }

    /** Un inicio de sesión correcto reinicia el contador de fallos de la cuenta. */
    public function test_un_login_correcto_reinicia_el_contador_de_fallos(): void
    {
        for ($i = 0; $i < 19; $i++) {
            $this->desdeLaIp("10.3.0.$i")->postJson('/api/sesion', ['usuario' => 'usuario1', 'contrasena' => 'mal'])
                ->assertStatus(401);
        }
        $this->desdeLaIp('10.3.1.1')->postJson('/api/sesion', ['usuario' => 'usuario1', 'contrasena' => 'password'])->assertOk();

        // Con el contador reiniciado, otros 19 fallos siguen sin bloquear la cuenta.
        for ($i = 0; $i < 19; $i++) {
            $this->desdeLaIp("10.3.2.$i")->postJson('/api/sesion', ['usuario' => 'usuario1', 'contrasena' => 'mal'])
                ->assertStatus(401);
        }
    }

    /** `TrimStrings` no debe recortar la contraseña (el legado no lo hacía): «password » y « password» son claves distintas. */
    public function test_la_contrasena_no_se_recorta(): void
    {
        $this->entrar('usuario1', 'password ')->assertStatus(401);
        $this->entrar('usuario1', ' password')->assertStatus(401);
        $this->entrar('usuario1', 'password')->assertOk();
    }

    /**
     * DAT-01 en sesiones abiertas: si la cuenta se desactiva MIENTRAS hay sesión, la siguiente petición la cierra
     * (middleware `cuenta.activa`) y el avatar tampoco se puede cambiar.
     */
    public function test_una_cuenta_desactivada_pierde_la_sesion_abierta(): void
    {
        $this->conEscrituraEnPedigitalTest(function ($db) {
            try {
                $this->entrar('usuario2')->assertOk();
                $this->conSesion()->getJson('/api/sesion')->assertOk();

                $db->table('users')->where('id', 2)->update(['estado_usuario' => 'INACTIVO']);
                // En un servidor real cada petición relee al usuario; en las pruebas el guard lo conserva en memoria
                // entre peticiones simuladas, así que se descarta para reproducir ese comportamiento.
                $this->app['auth']->forgetGuards();
                $this->conSesion()->getJson('/api/sesion')->assertStatus(401);
                $this->app['auth']->forgetGuards();
                $this->conSesion()->patchJson('/api/perfil/avatar', ['avatar' => 4])->assertStatus(401);
                $this->assertNull(PerfilExplora::where('usuario_id', 2)->first());
            } finally {
                $db->table('users')->where('id', 2)->update(['estado_usuario' => 'ACTIVO']);
            }
        });
    }

    /**
     * Una cuenta SIN hash de contraseña no puede entrar con ninguna clave (no debe compararse con el hash señuelo).
     * Se prepara vaciando el hash de un usuario de pedigital_test y se restaura el valor original sin mostrarlo.
     */
    public function test_una_cuenta_sin_hash_no_puede_entrar(): void
    {
        $this->conEscrituraEnPedigitalTest(function ($db) {
            $original = $db->table('users')->where('id', 3)->value('pasword_usuario');
            try {
                $db->table('users')->where('id', 3)->update(['pasword_usuario' => '']);
                $this->entrar('usuario3', 'cualquiera')->assertStatus(401);
            } finally {
                $db->table('users')->where('id', 3)->update(['pasword_usuario' => $original]);
            }
        });
    }

    /**
     * En los datos reales hay un hash `$2a$` (el legado lo aceptaba): `getAuthPassword()` debe normalizar `$2a$` y
     * `$2b$` a `$2y$` para que Laravel pueda compararlos; `$2x$` (variante con defecto) no se toca.
     */
    public function test_los_hashes_bcrypt_2a_y_2b_se_aceptan(): void
    {
        $y = password_hash('clave', PASSWORD_BCRYPT, ['cost' => 4]);
        foreach (['$2a$', '$2b$', '$2y$'] as $prefijo) {
            $usuario = new Usuario(['pasword_usuario' => $prefijo.substr($y, 4)]);
            $this->assertTrue(Hash::check('clave', $usuario->getAuthPassword()), "prefijo $prefijo");
            $this->assertFalse(Hash::check('otra', $usuario->getAuthPassword()), "prefijo $prefijo");
        }
        $this->assertSame('', (new Usuario(['pasword_usuario' => '']))->getAuthPassword());
        $this->assertStringStartsWith('$2x$', (new Usuario(['pasword_usuario' => '$2x$10$abc']))->getAuthPassword());
    }

    /** El avatar acepta los bordes (1 y 35) y números escritos como texto («7»), que es como llegan en un formulario. */
    public function test_el_avatar_acepta_los_bordes_y_numeros_como_texto(): void
    {
        $this->entrar()->assertOk();
        foreach ([1, 35, '7'] as $bueno) {
            $this->conSesion()->patchJson('/api/perfil/avatar', ['avatar' => $bueno])->assertOk();
        }
        $this->assertSame(7, PerfilExplora::avatarDe(1));
        $this->conSesion()->patchJson('/api/perfil/avatar', ['avatar' => 36])->assertStatus(422);
    }

    /** Un avatar vacío o 0 en pedigital no debe producir «0.png» (no existe): se usa el avatar 1. */
    public function test_un_avatar_vacio_o_cero_en_pedigital_se_sustituye_por_el_1(): void
    {
        $this->conEscrituraEnPedigitalTest(function ($db) {
            $original = $db->table('users')->where('id', 3)->value('avatar_explora');
            try {
                $db->table('users')->where('id', 3)->update(['avatar_explora' => 0]);
                $this->assertSame(1, PerfilExplora::avatarDe(3));
            } finally {
                $db->table('users')->where('id', 3)->update(['avatar_explora' => $original]);
            }
        });
    }

    /**
     * Ejecuta la siguiente petición simulando que viene de otra dirección IP (para probar límites por IP y por cuenta).
     *
     * @param  string  $ip  Dirección IP de origen simulada.
     */
    private function desdeLaIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    /**
     * Valor (cifrado) de la cookie de sesión de una respuesta.
     *
     * @param  TestResponse  $r  Respuesta del servidor.
     */
    private function cookieDeSesion(TestResponse $r): ?string
    {
        foreach ($r->headers->getCookies() as $c) {
            if ($c->getName() === config('session.cookie')) {
                return $c->getValue();
            }
        }

        return null;
    }

    /**
     * Id de sesión (descifrado) que el servidor entrega en la cookie de sesión de una respuesta.
     *
     * @param  TestResponse  $r  Respuesta del servidor.
     * @return string|null Id de sesión, o null si la respuesta no trae cookie de sesión.
     */
    private function idDeSesion(TestResponse $r): ?string
    {
        $valor = $this->cookieDeSesion($r);

        return $valor ? explode('|', app('encrypter')->decrypt($valor, false), 2)[1] ?? null : null;
    }
}
