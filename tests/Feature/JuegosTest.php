<?php

namespace Tests\Feature;

use App\Models\Explora\PerfilExplora;
use App\Models\Explora\ResultadoJuego;
use App\Models\Pedigital\Usuario;
use Tests\Concerns\RespaldaPerfiles;
use Tests\TestCase;

/**
 * Fase 5: resultados, ranking e historial de los juegos.
 *
 * Cierra SEC-03 (id de usuario tomado de la sesión), SEC-01 (sin SQL concatenado), SEC-05 (entradas que no pueden
 * contener HTML), SEC-06 (rangos validados) y FUN-01 en lo posible. Conserva la regla de negocio del MODO INVITADO
 * (FUN-09): sin sesión se puede jugar y guardar, con `id_usuario = 0`, pero con datos validados y límite de uso.
 *
 * Todas las filas que crean estas pruebas usan una categoría con el prefijo {@see self::MARCA} y se borran al
 * terminar, por lo que la tabla `resultado_juegos` queda como estaba.
 */
class JuegosTest extends TestCase
{
    use RespaldaPerfiles;

    /** Prefijo de las categorías de prueba: permite localizar y borrar lo que crean estas pruebas. */
    private const MARCA = 'Zz Prueba Fase5 ';

    /** @var string Categoría única de esta ejecución. */
    private string $categoria;

    /**
     * Prepara una categoría única y respalda los perfiles de los usuarios de prueba.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->categoria = self::MARCA.uniqid();
        $this->respaldarPerfiles();
    }

    /**
     * Borra los resultados creados por las pruebas y restaura los perfiles.
     */
    protected function tearDown(): void
    {
        ResultadoJuego::where('categoria', 'like', self::MARCA.'%')->delete();
        $this->restaurarPerfiles();
        parent::tearDown();
    }

    /**
     * Cuerpo válido de un resultado de invitado, con los campos que sustituyen a los `*_query` del legado.
     *
     * @param  array<string, mixed>  $cambios  Campos a sobrescribir.
     * @return array<string, mixed>
     */
    private function cuerpo(array $cambios = []): array
    {
        return array_merge([
            'juego' => 'laberinto', 'nivel' => 'Principiante', 'categoria' => $this->categoria,
            'puntaje' => 7, 'tiempo' => 125,
            'nombre' => 'María José Pérez', 'grado' => '5', 'avatar' => '3.png',
        ], $cambios);
    }

    /**
     * Inserta un resultado directamente en la tabla (para preparar datos de ranking e historial).
     *
     * @param  array<string, mixed>  $campos  Campos a sobrescribir.
     */
    private function sembrar(array $campos = []): ResultadoJuego
    {
        return ResultadoJuego::create(array_merge([
            'nombre_juego' => 'laberinto', 'nivel' => 'Principiante', 'categoria' => $this->categoria,
            'puntaje' => 5, 'tiempo' => 100, 'jugador' => 'Jugador Prueba', 'avatar' => '1.png', 'grado' => '6',
            'id_usuario' => 0, 'fecha' => '01-01-2026 10:00:00',
        ], $campos));
    }

    /** Cuenta las filas de prueba de esta ejecución. */
    private function filasDePrueba(): int
    {
        return ResultadoJuego::where('categoria', $this->categoria)->count();
    }

    // ------------------------------------------------------------------ guardar

    /** FUN-09: un invitado (sin sesión) puede guardar; queda con id_usuario 0 y fecha en el formato que lee el frontend. */
    public function test_un_invitado_puede_guardar_su_resultado(): void
    {
        $r = $this->postJson('/api/resultados', $this->cuerpo())->assertStatus(201);
        $r->assertJsonPath('status', 'success');

        $fila = ResultadoJuego::where('categoria', $this->categoria)->firstOrFail();
        $this->assertSame(0, $fila->id_usuario);
        $this->assertSame('laberinto', $fila->nombre_juego);
        $this->assertSame('María José Pérez', $fila->jugador);
        $this->assertSame('5', (string) $fila->grado);
        $this->assertSame('3.png', $fila->avatar);
        $this->assertSame(7, $fila->puntaje);
        $this->assertSame(125, $fila->tiempo);
        // El historial del frontend hace fecha.split(' ') → «d-m-Y» y «H:i:s».
        $this->assertMatchesRegularExpression('/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}$/', $fila->fecha);
    }

    /** SEC-03: con sesión, la identidad (id, nombre, grado y avatar) sale de la sesión y se ignora lo que diga el cuerpo. */
    public function test_con_sesion_la_identidad_sale_de_la_sesion_y_no_del_cuerpo(): void
    {
        $usuario = Usuario::findOrFail(1);
        $this->actingAs($usuario, 'web')->postJson('/api/resultados', $this->cuerpo([
            'nombre' => 'Otra Persona', 'grado' => '11', 'avatar' => '35.png', 'id_usuario' => 2, 'usuario_id' => 2,
        ]))->assertStatus(201);

        $fila = ResultadoJuego::where('categoria', $this->categoria)->firstOrFail();
        $this->assertSame(1, $fila->id_usuario);
        $this->assertSame($usuario->nombre_usuario, $fila->jugador);
        $this->assertSame((string) $usuario->grado_usuario, (string) $fila->grado);
        $this->assertSame(PerfilExplora::avatarDe(1).'.png', $fila->avatar);
    }

    /** Un usuario con sesión no necesita enviar nombre, grado ni avatar (el frontend ya los bloquea). */
    public function test_con_sesion_no_hacen_falta_nombre_grado_ni_avatar(): void
    {
        $this->actingAs(Usuario::findOrFail(1), 'web')
            ->postJson('/api/resultados', collect($this->cuerpo())->except(['nombre', 'grado', 'avatar'])->all())
            ->assertStatus(201);
    }

    /**
     * SEC-01/05/06: cada entrada inválida se rechaza con 422 y NO se guarda nada (juego o nivel fuera de lista,
     * puntaje y tiempo fuera de rango o no numéricos, HTML o comillas en textos, grado y avatar inválidos).
     */
    public function test_entradas_invalidas_se_rechazan_sin_guardar_nada(): void
    {
        $malos = [
            ['juego' => 'hackeo'], ['juego' => ''], ['nivel' => 'Dificil'],
            ['puntaje' => -1], ['puntaje' => 1001], ['puntaje' => 999999], ['puntaje' => 'diez'], ['puntaje' => 2.5], ['puntaje' => null],
            ['tiempo' => -5], ['tiempo' => 14401], ['tiempo' => 'x'],
            ['categoria' => '<script>alert(1)</script>'], ['categoria' => "x'); DROP TABLE resultado_juegos;--"], ['categoria' => ''],
            ['categoria' => str_repeat('a', 101)],
            ['nombre' => '<img src=x onerror=alert(1)>'], ['nombre' => ''], ['nombre' => str_repeat('a', 61)], ['nombre' => ['x']],
            ['grado' => '0'], ['grado' => '12'], ['grado' => 'abc'], ['grado' => ''],
            ['avatar' => '0.png'], ['avatar' => '36.png'], ['avatar' => 'x.png'], ['avatar' => '../../etc/passwd'], ['avatar' => '3.png<'],
        ];
        foreach ($malos as $i => $cambio) {
            // Cada intento llega desde una IP distinta: las peticiones inválidas TAMBIÉN cuentan para el límite de
            // 10 por minuto (es lo deseado contra el abuso) y aquí se quiere probar la validación, no el límite.
            $this->withServerVariables(['REMOTE_ADDR' => '10.9.0.'.$i])
                ->postJson('/api/resultados', $this->cuerpo($cambio))->assertStatus(422);
        }
        $this->assertSame(0, $this->filasDePrueba());
    }

    /** Un invitado debe enviar nombre, grado y avatar (no hay sesión de la que tomarlos). */
    public function test_un_invitado_debe_enviar_nombre_grado_y_avatar(): void
    {
        foreach (['nombre', 'grado', 'avatar'] as $campo) {
            $this->postJson('/api/resultados', collect($this->cuerpo())->except($campo)->all())->assertStatus(422);
        }
        $this->assertSame(0, $this->filasDePrueba());
    }

    /** Se aceptan los 8 juegos y los 4 niveles reales (valores exactos que envía el frontend, con tildes y mayúsculas). */
    public function test_acepta_los_juegos_y_niveles_reales_del_frontend(): void
    {
        $juegos = ['laberinto', 'Sopa de letras', 'Completa la oración', 'Arrastra y suelta', 'Rompecabezas', 'Ahorcado', 'Pinta la bandera', 'Adivina la bandera'];
        foreach ($juegos as $i => $juego) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.5.0.$i"])
                ->postJson('/api/resultados', $this->cuerpo(['juego' => $juego]))->assertStatus(201);
        }
        foreach (['Principiante', 'Intermedio', 'Experto', 'Normal'] as $j => $nivel) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.5.1.$j"])
                ->postJson('/api/resultados', $this->cuerpo(['nivel' => $nivel]))->assertStatus(201);
        }
        $this->assertSame(12, $this->filasDePrueba());
    }

    /** Categorías reales con tildes, signos y la «Categoría Única» de los juegos de banderas. */
    public function test_acepta_categorias_con_tildes_y_signos(): void
    {
        foreach (['Mapa Político de Colombia', 'Categoría Única', 'Evolución de la bandera (1810-1819)', 'Etnias de Colombia'] as $i => $cat) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.5.2.$i"])
                ->postJson('/api/resultados', $this->cuerpo(['categoria' => $cat]))->assertStatus(201);
        }
        ResultadoJuego::whereIn('categoria', ['Mapa Político de Colombia', 'Categoría Única', 'Evolución de la bandera (1810-1819)', 'Etnias de Colombia'])
            ->where('jugador', 'María José Pérez')->delete();
    }

    /** Límite de uso del modo invitado: 10 resultados por minuto y por IP; el 11.º recibe 429 con Retry-After. */
    public function test_el_modo_invitado_tiene_limite_de_uso(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/resultados', $this->cuerpo())->assertStatus(201);
        }
        $r = $this->postJson('/api/resultados', $this->cuerpo())->assertStatus(429);
        $this->assertNotNull($r->headers->get('Retry-After'));
        $this->assertSame(10, $this->filasDePrueba());
    }

    /** Toda escritura exige token CSRF (también la de los invitados); sin él, 419. */
    public function test_guardar_exige_token_csrf(): void
    {
        $this->app['env'] = 'local';
        $this->postJson('/api/resultados', $this->cuerpo())->assertStatus(419);
        $this->withSession(['_token' => 'tok'])->withHeader('X-CSRF-TOKEN', 'tok')
            ->postJson('/api/resultados', $this->cuerpo())->assertStatus(201);
    }

    /** Un resultado de una cuenta que se desactivó con la sesión abierta se rechaza (401) en lugar de guardarse. */
    public function test_una_cuenta_desactivada_no_puede_guardar(): void
    {
        $usuario = Usuario::findOrFail(1);
        $usuario->estado_usuario = 'INACTIVO'; // solo en memoria: el modelo es de solo lectura y no se guarda
        $this->actingAs($usuario, 'web')->postJson('/api/resultados', $this->cuerpo())->assertStatus(401);
        $this->assertSame(0, $this->filasDePrueba());
    }

    // ------------------------------------------------------------------ ranking

    /** El ranking devuelve máximo 5 filas ordenadas por puntaje (desc) y tiempo (asc), con SOLO los campos que lee el frontend. */
    public function test_el_ranking_muestra_el_top_5_ordenado_con_los_campos_del_frontend(): void
    {
        foreach ([[5, 100], [9, 300], [9, 200], [2, 50], [7, 90], [8, 10], [1, 1]] as $i => [$puntaje, $tiempo]) {
            $this->sembrar(['puntaje' => $puntaje, 'tiempo' => $tiempo, 'jugador' => "Jugador $i", 'id_usuario' => $i + 100]);
        }
        $r = $this->getJson('/api/ranking?'.http_build_query(['juego' => 'laberinto', 'nivel' => 'Principiante', 'categoria' => $this->categoria]))
            ->assertOk();

        $filas = $r->json();
        $this->assertCount(5, $filas);
        $this->assertSame([[9, 200], [9, 300], [8, 10], [7, 90], [5, 100]], array_map(fn ($f) => [$f['puntaje'], $f['tiempo']], $filas));
        $this->assertEqualsCanonicalizing(['jugador', 'avatar', 'grado', 'puntaje', 'tiempo'], array_keys($filas[0]));
        $this->assertStringNotContainsString('id_usuario', $r->getContent(), 'el ranking no debe revelar ids de usuario');
    }

    /** El ranking filtra por juego, nivel y categoría: otra combinación no devuelve nada. */
    public function test_el_ranking_filtra_por_juego_nivel_y_categoria(): void
    {
        $this->sembrar();
        $base = ['juego' => 'laberinto', 'nivel' => 'Principiante', 'categoria' => $this->categoria];
        $this->getJson('/api/ranking?'.http_build_query($base))->assertOk()->assertJsonCount(1);
        foreach ([['juego' => 'Ahorcado'], ['nivel' => 'Experto'], ['categoria' => $this->categoria.'x']] as $cambio) {
            $this->getJson('/api/ranking?'.http_build_query(array_merge($base, $cambio)))->assertOk()->assertExactJson([]);
        }
    }

    /** SEC-01: parámetros inválidos o inyectados → 422 (el legado terminaba en error fatal o filtraba todo). */
    public function test_el_ranking_rechaza_parametros_invalidos_o_inyectados(): void
    {
        $this->getJson('/api/ranking')->assertStatus(422);
        $this->getJson('/api/ranking?'.http_build_query(['juego' => 'laberinto', 'nivel' => "x' OR '1'='1", 'categoria' => "x' OR '1'='1"]))
            ->assertStatus(422);
        $this->getJson('/api/ranking?'.http_build_query(['juego' => ['a'], 'nivel' => 'Normal', 'categoria' => 'x']))->assertStatus(422);
    }

    /** El ranking es público (no requiere sesión) y tiene límite de peticiones. */
    public function test_el_ranking_es_publico_y_limitado(): void
    {
        $r = $this->getJson('/api/ranking?'.http_build_query(['juego' => 'laberinto', 'nivel' => 'Normal', 'categoria' => 'x']))->assertOk();
        $this->assertNotNull($r->headers->get('X-RateLimit-Limit'));
    }

    // ------------------------------------------------------------------ historial

    /** SEC-03: sin sesión no hay historial (el legado entregaba el de cualquier id). */
    public function test_el_historial_requiere_sesion(): void
    {
        $this->getJson('/api/historial')->assertStatus(401);
    }

    /**
     * El historial entrega SOLO los resultados del usuario de la sesión, del más reciente al más antiguo, con los campos
     * que lee miPerfil/script.js; ignora cualquier `id_usuario` enviado en la URL.
     */
    public function test_el_historial_es_solo_del_usuario_de_la_sesion(): void
    {
        $this->sembrar(['id_usuario' => 1, 'puntaje' => 1, 'fecha' => '01-01-2026 10:00:00']);
        $this->sembrar(['id_usuario' => 1, 'puntaje' => 2, 'fecha' => '02-01-2026 11:30:00']);
        $this->sembrar(['id_usuario' => 2, 'puntaje' => 9, 'fecha' => '03-01-2026 12:00:00']);

        $r = $this->actingAs(Usuario::findOrFail(1), 'web')->getJson('/api/historial?id_usuario=2')->assertOk();
        $propias = collect($r->json())->where('categoria', $this->categoria)->values();

        $this->assertCount(2, $propias);
        $this->assertSame([2, 1], $propias->pluck('puntaje')->all(), 'del más reciente al más antiguo');
        $this->assertEqualsCanonicalizing(
            ['id', 'nombre_juego', 'categoria', 'nivel', 'puntaje', 'tiempo', 'fecha'],
            array_keys($propias[0])
        );
        $this->assertStringNotContainsString('"jugador"', $r->getContent());
        $this->assertStringNotContainsString('id_usuario', $r->getContent());
    }

    /** Un usuario sin resultados recibe una lista vacía (no un error). */
    public function test_el_historial_sin_resultados_es_una_lista_vacia(): void
    {
        $this->actingAs(Usuario::findOrFail(3), 'web')->getJson('/api/historial')->assertOk()->assertExactJson([]);
    }
}
