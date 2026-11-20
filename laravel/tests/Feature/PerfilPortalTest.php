<?php

namespace Tests\Feature;

use App\Models\Explora\PerfilExplora;
use App\Models\Pedigital\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\RespaldaPerfiles;
use Tests\TestCase;

/**
 * Fase 12: el perfil (`/perfil`) y el portal de juegos (`/juegos`) se pintan en el SERVIDOR con la sesión de Laravel.
 *
 * Cubre: aviso sin sesión (sin formulario ni datos) y cabeceras de caché; datos correctos con sesión (nombre, login,
 * grado, avatar y lista de avatares según `avatares_max`); escape de HTML del nombre; cuenta inactiva tratada como sin
 * sesión; aislamiento entre usuarios; estado inicial de la barra del portal; y las redirecciones 301 de las URL antiguas.
 *
 * La prueba de que `/juegos` y `/juegos/` llegan a Laravel desde Apache o el servidor de desarrollo (carpetas reales)
 * está en {@see ServidorDesarrolloTest}: aquí no hay servidor HTTP, solo el kernel de Laravel.
 */
class PerfilPortalTest extends TestCase
{
    use RespaldaPerfiles;

    /**
     * Respalda los perfiles de los usuarios de prueba (se crean avatares propios en algunas pruebas).
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->respaldarPerfiles();
    }

    /**
     * Restaura `perfiles_explora` como estaba.
     */
    protected function tearDown(): void
    {
        $this->restaurarPerfiles();
        parent::tearDown();
    }

    /**
     * Inicia sesión por la API (como el navegador) y devuelve las cookies recibidas (sesión + XSRF).
     * (Mismo mecanismo que AutenticacionTest::entrar(): cada usuario tiene su propio juego de cookies.)
     *
     * @return array<string, string> Cookies por nombre.
     */
    private function entrar(string $usuario): array
    {
        $r = $this->postJson('/api/sesion', ['usuario' => $usuario, 'contrasena' => 'password']);
        $r->assertOk();
        $cookies = [];
        foreach ($r->headers->getCookies() as $c) {
            $cookies[$c->getName()] = $c->getValue();
        }
        // En el servidor real cada petición relee al usuario; en las pruebas el guard lo conserva en memoria.
        $this->app['auth']->forgetGuards();

        return $cookies;
    }

    /**
     * Pide una página reenviando las cookies de una sesión (simula el navegador de esa persona).
     *
     * @param  array<string, string>  $cookies  Cookies devueltas por {@see self::entrar()}; vacío = anónimo.
     */
    private function paginaCon(string $ruta, array $cookies = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        foreach ($cookies as $nombre => $valor) {
            $this->withUnencryptedCookie($nombre, $valor);
        }

        return $this->get($ruta);
    }

    /**
     * Ejecuta $fn con una conexión de ESCRITURA a pedigital_test (solo para preparar escenarios; igual que
     * AutenticacionTest). Falla, no se omite, si faltan las credenciales.
     */
    private function conEscrituraEnPedigitalTest(callable $fn): void
    {
        if (! env('LEGACY_DB_USER')) {
            $this->fail('Faltan LEGACY_DB_USER/LEGACY_DB_PASS en laravel/.env: son necesarios para preparar escenarios en pedigital_test.');
        }
        $cfg = config('database.connections.pedigital');
        $this->assertStringEndsWith('_test', $cfg['database']);
        config(['database.connections.pedigital_rw' => array_merge($cfg, ['username' => env('LEGACY_DB_USER'), 'password' => env('LEGACY_DB_PASS')])]);
        $fn(DB::connection('pedigital_rw'));
    }

    // ------------------------------------------------------------------------------------------------ perfil

    /**
     * Sin sesión: 200 con el aviso y el enlace a la portada; ni formulario, ni historial, ni datos; el script del
     * perfil (el que pide /api/historial) no se carga; y la respuesta no se puede guardar en ninguna caché.
     */
    public function test_el_perfil_anonimo_muestra_solo_el_aviso(): void
    {
        $r = $this->paginaCon('/perfil');
        $r->assertOk();
        $html = $r->getContent();

        $this->assertStringContainsString('id="aviso-sesion"', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('Debe iniciar sesión para ver su perfil.', $html);
        $this->assertStringContainsString('<a href="/">Ir al inicio</a>', $html);

        // Nada del formulario, del historial, del modal ni del menú.
        foreach (['id="nombre"', 'id="usuario"', 'id="grado"', 'id="avatar-preview"', 'id="btn-guardar"', 'id="historial"',
            'id="historial-body"', 'id="modal-avatar"', 'data-avatar=', 'id="menu-perfil"', 'miPerfil/script.js'] as $trozo) {
            $this->assertStringNotContainsString($trozo, $html, "El perfil anónimo no debe contener $trozo");
        }
        // Ningún dato de los usuarios de prueba (ni nombre ni login).
        $this->assertStringNotContainsString('Usuario 1', $html);
        $this->assertStringNotContainsString('usuario1', $html);

        // Datos personales: ninguna caché (ni compartida ni del navegador) debe guardar esta respuesta.
        $cache = (string) $r->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('no-store', $cache);
    }

    /**
     * Con sesión: la página trae pintados el nombre, el login, el grado seleccionado, el avatar vigente (el del perfil
     * propio) y tantas opciones de avatar como `avatares_max`; con `Cache-Control: private, no-store`.
     */
    public function test_el_perfil_con_sesion_pinta_los_datos_del_usuario(): void
    {
        PerfilExplora::updateOrCreate(['usuario_id' => 1], ['avatar' => 7]);
        $cookies = $this->entrar('usuario1');

        $r = $this->paginaCon('/perfil', $cookies);
        $r->assertOk();
        $html = $r->getContent();

        $this->assertMatchesRegularExpression('/id="nombre"[^>]*value="Usuario 1"/', $html);
        $this->assertMatchesRegularExpression('/id="usuario"[^>]*value="usuario1"/', $html);
        // Grado 6: solo esa opción está marcada; el aviso «Selecciona tu grado» no.
        $this->assertSame(1, preg_match_all('/<option[^>]*\bselected\b/', $this->bloqueGrado($html)));
        $this->assertMatchesRegularExpression('/<option value="6" selected>6°<\/option>/', $html);
        $this->assertSame(11, preg_match_all('/<option value="\d+"/', $html));
        $this->assertDoesNotMatchRegularExpression('/<option selected disabled>/', $html);
        // Avatar vigente.
        $this->assertMatchesRegularExpression('#id="avatar-preview"#', $html);
        $this->assertMatchesRegularExpression('#<img\s+src="[^"]*/juegos/images/avatars/7\.png"\s+alt="Avatar" id="avatar-preview"#', $html);
        // Menú y historial presentes y sin aviso.
        foreach (['id="menu-perfil"', 'id="historial-body"', 'id="btn-guardar"', 'id="modal-avatar"', 'miPerfil/script.js'] as $trozo) {
            $this->assertStringContainsString($trozo, $html);
        }
        $this->assertStringNotContainsString('id="aviso-sesion"', $html);

        // Lista de avatares = avatares_max (hoy 35), con el contrato [data-avatar] role=button tabindex=0.
        $max = (int) config('explora.avatares_max');
        $this->assertSame($max, preg_match_all('/data-avatar="\d+"/', $html));
        $this->assertStringContainsString('id="avatar_'.$max.'"', $html);
        $this->assertStringNotContainsString('id="avatar_'.($max + 1).'"', $html);
        $this->assertSame($max, preg_match_all('/class="avatar_container" role="button" tabindex="0"/', $html));

        $cache = (string) $r->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('no-store', $cache);
    }

    /**
     * Devuelve el HTML del `<select id="grado">`.
     */
    private function bloqueGrado(string $html): string
    {
        $this->assertSame(1, preg_match('#<select[^>]*id="grado".*?</select>#s', $html, $m));

        return $m[0];
    }

    /**
     * La lista de avatares sale de la configuración, no de un número fijo en el JS: con otro tope cambia la cantidad.
     */
    public function test_la_lista_de_avatares_sigue_la_configuracion(): void
    {
        config(['explora.avatares_max' => 12]);
        $cookies = $this->entrar('usuario1');

        $html = $this->paginaCon('/perfil', $cookies)->getContent();
        $this->assertSame(12, preg_match_all('/data-avatar="\d+"/', $html));
        $this->assertStringContainsString('id="avatar_12"', $html);
        $this->assertStringNotContainsString('id="avatar_13"', $html);
    }

    /**
     * Un avatar guardado fuera de 1..max (dato heredado inválido) no pide una imagen inexistente: se usa el 1.
     */
    public function test_un_avatar_fuera_de_rango_se_sustituye_por_el_1(): void
    {
        PerfilExplora::updateOrCreate(['usuario_id' => 1], ['avatar' => 99]);
        $cookies = $this->entrar('usuario1');

        $html = $this->paginaCon('/perfil', $cookies)->getContent();
        $this->assertMatchesRegularExpression('#src="[^"]*/avatars/1\.png"\s+alt="Avatar" id="avatar-preview"#', $html);
        $this->assertStringNotContainsString('/avatars/99.png', $html);
    }

    /**
     * El nombre viene de una base externa y puede contener HTML: aparece ESCAPADO, nunca como etiqueta. Se prepara en
     * `pedigital_test` (con la conexión de escritura solo de pruebas) y se restaura al terminar.
     */
    public function test_el_nombre_con_html_aparece_escapado(): void
    {
        $this->conEscrituraEnPedigitalTest(function ($db) {
            $fila = $db->table('users')->where('id', 3)->first(['nombre_usuario', 'grado_usuario']);
            try {
                $db->table('users')->where('id', 3)->update([
                    'nombre_usuario' => '<script>alert(1)</script> "x" \'y\' & <img src=x onerror=alert(2)>',
                    'grado_usuario' => '9',
                ]);
                $cookies = $this->entrar('usuario3');
                $html = $this->paginaCon('/perfil', $cookies)->getContent();

                $this->assertStringNotContainsString('<script>alert(1)', $html);
                $this->assertStringNotContainsString('<img src=x', $html);
                $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &quot;x&quot; &#039;y&#039; &amp; &lt;img src=x onerror=alert(2)&gt;', $html);
                // De paso, el grado 9 queda seleccionado.
                $this->assertMatchesRegularExpression('/<option value="9" selected>9°<\/option>/', $html);
            } finally {
                $db->table('users')->where('id', 3)->update([
                    'nombre_usuario' => $fila->nombre_usuario, 'grado_usuario' => $fila->grado_usuario,
                ]);
            }
        });
    }

    /**
     * Lo mismo sobre la VISTA con datos falsos (sin base de datos): nombre y login con HTML y comillas salen escapados
     * y no pueden cerrar el atributo `value`.
     */
    public function test_la_vista_escapa_nombre_y_login_con_datos_falsos(): void
    {
        $hostil = '"><script>alert(1)</script>\'';
        $html = view('perfil.index', ['datos' => [
            'nombre' => $hostil, 'usuario' => $hostil, 'grado' => '5', 'avatar' => 3, 'avatares' => 4,
        ]])->render();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('value=""><', $html);
        $this->assertSame(2, substr_count($html, '&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;&#039;'));
        $this->assertSame(4, preg_match_all('/data-avatar="\d+"/', $html));
    }

    /**
     * Una cuenta con estado distinto de ACTIVO, aunque aún conserve la cookie de sesión, se trata como sin sesión: solo
     * el aviso, sin datos. La página NO cierra la sesión ni escribe en la base (eso lo hace la API).
     */
    public function test_una_cuenta_inactiva_se_trata_como_sin_sesion(): void
    {
        $this->conEscrituraEnPedigitalTest(function ($db) {
            try {
                $cookies = $this->entrar('usuario2');
                $this->paginaCon('/perfil', $cookies)->assertSee('id="nombre"', false);

                $db->table('users')->where('id', 2)->update(['estado_usuario' => 'INACTIVO']);
                $perfil = $this->paginaCon('/perfil', $cookies);
                $perfil->assertOk()->assertSee('Debe iniciar sesión para ver su perfil.');
                $this->assertStringNotContainsString('id="nombre"', $perfil->getContent());
                $this->assertStringNotContainsString('Usuario 2', $perfil->getContent());
                $this->assertStringContainsString('no-store', (string) $perfil->headers->get('Cache-Control'));

                // El portal también la trata como anónima.
                $portal = $this->paginaCon('/juegos', $cookies)->getContent();
                $this->assertMatchesRegularExpression('/id="btn-iniciar" style="display: block;"/', $portal);
                $this->assertMatchesRegularExpression('/id="btn-perfil" class="btn btn-outline-warning"/', str_replace('style="display: none;" ', '', $portal));
            } finally {
                $db->table('users')->where('id', 2)->update(['estado_usuario' => 'ACTIVO']);
            }
        });
    }

    /**
     * Cada persona ve SOLO sus datos: A y B, con avatares distintos, no se mezclan, y la URL no admite ids (un `?id=`
     * o `?usuario=` ajeno se ignora).
     */
    public function test_un_usuario_no_ve_los_datos_de_otro(): void
    {
        PerfilExplora::updateOrCreate(['usuario_id' => 1], ['avatar' => 5]);
        PerfilExplora::updateOrCreate(['usuario_id' => 2], ['avatar' => 9]);
        $a = $this->entrar('usuario1');
        $b = $this->entrar('usuario2');

        $paraA = $this->paginaCon('/perfil?id=2&usuario=usuario2&id_usuario=2', $a)->getContent();
        $this->assertStringContainsString('value="Usuario 1"', $paraA);
        $this->assertMatchesRegularExpression('#/avatars/5\.png"\s+alt="Avatar" id="avatar-preview"#', $paraA);
        $this->assertStringNotContainsString('Usuario 2', $paraA);
        $this->assertStringNotContainsString('usuario2', $paraA);

        $paraB = $this->paginaCon('/perfil?id=1', $b)->getContent();
        $this->assertStringContainsString('value="Usuario 2"', $paraB);
        $this->assertMatchesRegularExpression('#/avatars/9\.png"\s+alt="Avatar" id="avatar-preview"#', $paraB);
        $this->assertStringNotContainsString('Usuario 1', $paraB);
        $this->assertStringNotContainsString('usuario1', $paraB);
    }

    /**
     * Ni el hash, ni el correo, ni el token, ni el id interno salen en el HTML del perfil.
     */
    public function test_el_perfil_no_filtra_datos_internos(): void
    {
        $html = $this->paginaCon('/perfil', $this->entrar('usuario1'))->getContent();

        $this->assertStringNotContainsString('$2y$', $html);
        $this->assertStringNotContainsStringIgnoringCase('pasword', $html);
        $correo = (string) Usuario::findOrFail(1)->email_usuario;
        if ($correo !== '') {
            $this->assertStringNotContainsString($correo, $html);
        }
        $this->assertStringNotContainsString('remember_token', $html);
    }

    // (El cierre de sesión → perfil con aviso se comprueba en el navegador, con la sesión real de archivo: ver
    // tests/Browser/perfil.spec.js. El almacén de sesión en memoria de PHPUnit no lo reproduce con fidelidad.)

    // ------------------------------------------------------------------------------------------------ portal

    /**
     * Anónimo: «Iniciar sesión» visible; «Mi Perfil» y «Salir» ocultos; enlaces al perfil y a la portada; caché
     * privada y revalidable.
     */
    public function test_el_portal_anonimo_muestra_iniciar_sesion(): void
    {
        $r = $this->paginaCon('/juegos');
        $r->assertOk();
        $html = $r->getContent();

        $this->assertStringContainsString('<title>Juegos Educativos Explora</title>', $html);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(url('/perfil'), '/').'" style="display: none;" id="btn-perfil"/', $html);
        $this->assertMatchesRegularExpression('/id="btn-cerrar" aria-label="Cerrar sesión" style="display: none;"/', $html);
        $this->assertMatchesRegularExpression('/id="btn-iniciar" style="display: block;"/', $html);
        $this->assertStringContainsString('<a href="/" class="btn btn-outline-danger">', $html);
        $this->assertSame(6, substr_count($html, 'class="puzzle-piece'));
        $this->assertStringContainsString('id="myModal"', $html);

        $cache = (string) $r->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('no-cache', $cache);
    }

    /**
     * Con sesión: «Mi Perfil» y «Salir» visibles y «Iniciar sesión» oculto, ya en el HTML (sin esperar al JS).
     */
    public function test_el_portal_con_sesion_muestra_perfil_y_salir(): void
    {
        $r = $this->paginaCon('/juegos', $this->entrar('usuario1'));
        $r->assertOk();
        $html = $r->getContent();

        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(url('/perfil'), '/').'" style="display: block;" id="btn-perfil"/', $html);
        $this->assertMatchesRegularExpression('/id="btn-cerrar" aria-label="Cerrar sesión" style="display: block;"/', $html);
        $this->assertMatchesRegularExpression('/id="btn-iniciar" style="display: none;"/', $html);
        // El portal no muestra datos personales: solo el estado de la sesión.
        $this->assertStringNotContainsString('Usuario 1', $html);
        $this->assertStringNotContainsString('usuario1', $html);
        $this->assertStringContainsString('no-cache', (string) $r->headers->get('Cache-Control'));
    }

    /**
     * `/juegos/` (con barra) llega a la misma ruta de Laravel sin redirigir, y el portal carga sus recursos con URL
     * absolutas (las relativas del HTML original ya no resolverían desde `/juegos`).
     */
    public function test_el_portal_responde_con_barra_final_y_usa_urls_absolutas(): void
    {
        $this->paginaCon('/juegos/')->assertOk()->assertSee('Juegos Educativos Explora');

        $html = $this->paginaCon('/juegos')->getContent();
        preg_match_all('/\s(?:src|href)="([^"]*)"/i', $html, $urls);
        foreach ($urls[1] as $u) {
            $this->assertMatchesRegularExpression('#^(https?://|\#|/$)#i', $u, "URL no absoluta en el portal: $u");
        }
        $this->assertStringContainsString(asset('juegos/juegos/laberinto/index.html'), $html);
        $this->assertStringContainsString(asset('juegos/js/anio-pie.js'), $html);
    }

    // ----------------------------------------------------------------------------- redirecciones y plantillas

    /**
     * Las URL antiguas redirigen (301) a las nuevas y conservan la cadena de consulta.
     */
    public function test_las_urls_antiguas_redirigen_con_301(): void
    {
        $this->get('/miPerfil.html')->assertStatus(301)->assertRedirect('/perfil');
        $this->get('/juegos/index.html')->assertStatus(301)->assertRedirect('/juegos');
        $this->get('/juegos/index.html?a=1&b=2')->assertStatus(301)->assertRedirect('/juegos?a=1&b=2');
        $this->get('/miPerfil.html?x=1')->assertStatus(301)->assertRedirect('/perfil?x=1');
    }

    /**
     * Los archivos estáticos antiguos se eliminaron (un archivo real ganaría a la ruta) y el menú de la portada enlaza
     * a `/juegos`, no a `juegos/index.html`.
     */
    public function test_los_html_antiguos_ya_no_existen_y_el_menu_enlaza_al_portal(): void
    {
        $this->assertFileDoesNotExist(public_path('miPerfil.html'));
        $this->assertFileDoesNotExist(public_path('juegos/index.html'));

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('id="btn_menu_juegos" href="'.url('/juegos').'"', $html);
        $this->assertStringNotContainsString('juegos/index.html', $html);
    }

    /**
     * Las vistas nuevas no usan `{!! !!}` (salida sin escapar): todo el contenido dinámico pasa por `{{ }}`.
     */
    public function test_las_vistas_nuevas_no_usan_salida_sin_escapar(): void
    {
        foreach (['perfil', 'juegos', 'layouts'] as $carpeta) {
            foreach (File::allFiles(resource_path('views/'.$carpeta)) as $archivo) {
                $this->assertStringNotContainsString('{!!', $archivo->getContents(), $archivo->getFilename());
            }
        }
    }
}
