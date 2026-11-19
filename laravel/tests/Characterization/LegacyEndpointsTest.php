<?php

namespace Tests\Characterization;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PRUEBAS DE CARACTERIZACIÓN del PHP legado (Fase 1).
 *
 * Fijan el comportamiento ACTUAL de los 8 endpoints, incluidos sus defectos, para
 * poder comprobar equivalencia durante la migración. Las aserciones marcadas con
 * "DEFECTO <ID>" describen un fallo conocido (ver MATRIZ_HALLAZGOS.md): al migrar cada
 * endpoint la aserción se INVIERTE y se mueve a las pruebas del nuevo código.
 *
 * Requisitos: servir una COPIA del legado contra las bases *_test:
 *     tests/Legacy/serve.sh        (puerto 8081)
 * Si el servidor no responde, las pruebas se omiten (no fallan).
 */
class LegacyEndpointsTest extends TestCase
{
    private string $base;

    /**
     * Omite todas las pruebas de la clase si el servidor legado (tests/Legacy/serve.sh) no responde, en lugar de fallar.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->base = rtrim(env('LEGACY_BASE_URL', 'http://127.0.0.1:8081'), '/');
        try {
            Http::timeout(3)->get($this->base.'/mapplic/php/chat.php');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Servidor legado no disponible: ejecute tests/Legacy/serve.sh');
        }
    }

    private function legacyGet(string $path, array $query = []): Response
    {
        return Http::timeout(60)->get($this->base.$path, $query);
    }

    private function legacyPost(string $path, array $data = []): Response
    {
        return Http::timeout(60)->asForm()->post($this->base.$path, $data);
    }

    // ---------------------------------------------------------------- consultarInfo

    /**
     * Fija que la ficha del legado trae las 16 secciones y que las tablas hijas usan `descripcion.id` (8 para
     * Antioquia) y no el id del departamento (2): origen del modelo indirecto (TEC-01).
     */
    public function test_consultar_info_devuelve_la_ficha_completa(): void
    {
        $r = $this->legacyGet('/mapplic/php/consultarInfo.php', ['id' => 2]);
        $this->assertSame(200, $r->status());
        $json = $r->json();
        foreach (['descripcion', 'nombreDepartamento', 'historia', 'geografia', 'hidrografia', 'clima',
            'demografia', 'etnografia', 'faunaFlora', 'economia', 'cultura', 'gastronomia',
            'festividades', 'sitios', 'personajes', 'capital'] as $k) {
            $this->assertArrayHasKey($k, $json, "falta la sección $k");
        }
        $this->assertSame('Antioquia', $json['nombreDepartamento']['nombre']);
        // La FK intermedia: descripcion.id (8) != departamento (2)  -> TEC-01
        $this->assertNotEquals($json['descripcion']['id'], $json['descripcion']['departamento']);
    }

    /**
     * REN-03 / TEC-03: la ficha del legado pesa ≈9,8 MB (imágenes base64, DAT-07) y se sirve como `text/html`, por eso
     * `verMas.js` usa `JSON.parse`.
     */
    public function test_consultar_info_pesa_varios_megabytes_y_no_declara_json(): void
    {
        $r = $this->legacyGet('/mapplic/php/consultarInfo.php', ['id' => 2]);
        // DEFECTO REN-03: ficha de ~9,8 MB (contenido embebido) por apertura.
        $this->assertGreaterThan(1_000_000, strlen($r->body()));
        // DEFECTO TEC-03: Content-Type text/html en lugar de application/json.
        $this->assertStringContainsString('text/html', $r->header('Content-Type'));
    }

    /**
     * Fija que un departamento inexistente responde HTTP 200 con `{error}` (la API nueva responde 404).
     */
    public function test_consultar_info_departamento_inexistente_devuelve_error_json(): void
    {
        $r = $this->legacyGet('/mapplic/php/consultarInfo.php', ['id' => 999]);
        $this->assertSame(200, $r->status()); // DEFECTO: error con HTTP 200
        $this->assertStringContainsString('No se encontraron registros', $r->json('error'));
    }

    /**
     * SEC-07: un id no numérico provoca un error fatal de PHP/MySQL que revela la ruta del servidor.
     */
    public function test_consultar_info_id_no_numerico_expone_error_fatal_y_ruta(): void
    {
        $r = $this->legacyGet('/mapplic/php/consultarInfo.php', ['id' => 'abc']);
        // DEFECTO SEC-07: error fatal de PHP/MySQL con ruta del servidor.
        $this->assertStringContainsString('Fatal error', $r->body());
        $this->assertStringContainsString('consultarInfo.php', $r->body());
    }

    /**
     * SEC-01: «2 OR 1=1» devuelve el primer departamento en lugar del 2 (inyección SQL en lectura).
     */
    public function test_consultar_info_es_inyectable(): void
    {
        // DEFECTO SEC-01: "2 OR 1=1" devuelve el PRIMER departamento, no el 2.
        $r = $this->legacyGet('/mapplic/php/consultarInfo.php', ['id' => '2 OR 1=1']);
        $this->assertSame('1', (string) $r->json('descripcion.departamento'));
    }

    // ---------------------------------------------------------------- buscarPreguntas

    /**
     * Fija la forma del quiz legado: 30 preguntas por departamento, `correcta` visible (SEC-06) y `remember_token`
     * filtrado.
     */
    public function test_buscar_preguntas_devuelve_preguntas_y_departamento(): void
    {
        $json = $this->legacyGet('/mapplic/php/buscarPreguntas.php', ['id' => 2])->json();
        $this->assertCount(30, $json['preguntas']); // el quiz muestra "de 10": FUN-06
        $this->assertSame('Medellín', $json['departamento']['capital']);
        $multiple = collect($json['preguntas'])->firstWhere('tipo_pregunta', 'multiple');
        $this->assertNotEmpty($multiple['opciones']);
        // DEFECTO SEC-06: la opción correcta viaja al navegador.
        $this->assertArrayHasKey('correcta', $multiple['opciones'][0]);
        // DEFECTO: se filtran columnas internas.
        $this->assertArrayHasKey('remember_token', $multiple);
    }

    /**
     * FUN-02: un departamento sin datos responde cuerpo vacío (no JSON), lo que rompe `quiz.js`.
     */
    public function test_buscar_preguntas_departamento_inexistente_responde_vacio(): void
    {
        $r = $this->legacyGet('/mapplic/php/buscarPreguntas.php', ['id' => 999]);
        $this->assertSame(200, $r->status());
        $this->assertSame('', $r->body()); // DEFECTO FUN-02: cuerpo vacío, no JSON
    }

    /**
     * SEC-07: sin el parámetro `id` el legado muestra un error fatal con la ruta del servidor.
     */
    public function test_buscar_preguntas_sin_id_expone_error_fatal(): void
    {
        $r = $this->legacyGet('/mapplic/php/buscarPreguntas.php');
        $this->assertStringContainsString('Fatal error', $r->body()); // SEC-07
    }

    // ---------------------------------------------------------------- chat

    /**
     * Fija el único caso del chat que responde un JSON controlado (falta el parámetro `cadena`).
     */
    public function test_chat_sin_cadena_responde_json_de_error_controlado(): void
    {
        $r = $this->legacyGet('/mapplic/php/chat.php');
        $this->assertStringContainsString('application/json', $r->header('Content-Type'));
        $this->assertFalse($r->json('success'));
        $this->assertSame('Debe enviar el parámetro cadena.', $r->json('mensaje'));
    }

    /**
     * SEC-07 / FUN-05: sin Ollama el mensaje interno de cURL llega al cliente. Se omite si Ollama está activo.
     */
    public function test_chat_sin_ollama_devuelve_error_de_conexion(): void
    {
        $r = $this->legacyGet('/mapplic/php/chat.php', ['cadena' => 'capital de antioquia']);
        if ($r->json('success') === true) {
            $this->markTestSkipped('Ollama está activo: la prueba solo caracteriza el modo sin IA.');
        }
        // DEFECTO SEC-07: el mensaje interno de cURL llega al cliente.
        $this->assertStringContainsString('Error conectando con Ollama', $r->json('mensaje'));
    }

    // ---------------------------------------------------------------- iniciarSesion

    /**
     * SEC-04: el login legado devuelve la fila completa de `users`, incluidos hash, correo y token.
     */
    public function test_login_correcto_devuelve_el_usuario_con_el_hash(): void
    {
        $r = $this->legacyPost('/mapplic/php/iniciarSesion.php', ['usuario' => 'usuario1', 'contrasena' => 'password']);
        $this->assertTrue($r->json('success'));
        $u = $r->json('usuario_objeto');
        $this->assertSame('Estudiante', $u['tipo_usuario']);
        // DEFECTO SEC-04: fila completa, con hash y datos personales.
        foreach (['pasword_usuario', 'email_usuario', 'remember_token', 'estado_usuario'] as $campo) {
            $this->assertArrayHasKey($campo, $u);
        }
        $this->assertStringStartsWith('$2y$', $u['pasword_usuario']);
    }

    /**
     * SEC-04: mensajes distintos para usuario inexistente y clave incorrecta permiten enumerar usuarios.
     */
    public function test_login_distingue_usuario_inexistente_de_clave_incorrecta(): void
    {
        $mal = $this->legacyPost('/mapplic/php/iniciarSesion.php', ['usuario' => 'usuario1', 'contrasena' => 'x'])->json();
        $no = $this->legacyPost('/mapplic/php/iniciarSesion.php', ['usuario' => 'no_existe', 'contrasena' => 'x'])->json();
        $this->assertFalse($mal['success']);
        $this->assertFalse($no['success']);
        // DEFECTO SEC-04: mensajes distintos permiten enumerar usuarios.
        $this->assertSame('Contraseña incorrecta', $mal['mensaje']);
        $this->assertSame('Usuario no encontrado', $no['mensaje']);
    }

    /**
     * DAT-01: el legado no consulta `estado_usuario`; aquí solo se documenta que el campo viaja en la respuesta,
     * porque todos los usuarios de la copia están ACTIVO.
     */
    public function test_login_ignora_el_estado_del_usuario(): void
    {
        // DAT-01: el código no consulta estado_usuario. Con los datos actuales todos son
        // ACTIVO, así que solo se documenta que el campo viaja en la respuesta.
        $r = $this->legacyPost('/mapplic/php/iniciarSesion.php', ['usuario' => 'usuario1', 'contrasena' => 'password']);
        $this->assertSame('ACTIVO', $r->json('usuario_objeto.estado_usuario'));
    }

    // ---------------------------------------------------------------- historial / ranking

    /**
     * SEC-03: el historial se entrega a cualquiera que indique un `id_usuario`, sin sesión.
     */
    public function test_historial_cualquiera_puede_consultar_cualquier_usuario(): void
    {
        // DEFECTO SEC-03: sin autenticación; el id viene del cliente.
        $r = $this->legacyGet('/juegos/php/consultar_historial.php', ['id_usuario' => 0]);
        $this->assertSame(200, $r->status());
        $this->assertIsArray($r->json());
        $this->assertNotEmpty($r->json());
    }

    /**
     * SEC-01: «id OR 1=1» devuelve el historial de todos los usuarios.
     */
    public function test_historial_es_inyectable(): void
    {
        $vacio = $this->legacyGet('/juegos/php/consultar_historial.php', ['id_usuario' => 987654321])->json();
        $this->assertSame([], $vacio);
        // DEFECTO SEC-01
        $todo = $this->legacyGet('/juegos/php/consultar_historial.php', ['id_usuario' => '987654321 OR 1=1'])->json();
        $this->assertNotEmpty($todo);
    }

    /**
     * Fija el comportamiento correcto del ranking que debe conservar la migración: filtro por juego/nivel/categoría y
     * máximo 5 filas.
     */
    public function test_ranking_filtra_por_juego_nivel_y_categoria(): void
    {
        $fila = $this->legacyPost('/juegos/php/consultar_ranking.php', [
            'nombre_juego' => 'laberinto', 'nivel' => 'Principiante', 'categoria' => 'Mapa Político de Colombia',
        ])->json();
        $this->assertNotEmpty($fila);
        $this->assertLessThanOrEqual(5, count($fila));
        $this->assertSame('laberinto', $fila[0]['nombre_juego']);
        $otro = $this->legacyPost('/juegos/php/consultar_ranking.php', [
            'nombre_juego' => 'laberinto', 'nivel' => 'Experto', 'categoria' => 'nada',
        ])->json();
        $this->assertSame([], $otro);
    }

    /**
     * SEC-01: «x' OR '1'='1» en nivel y categoría ignora el filtro del ranking.
     */
    public function test_ranking_es_inyectable(): void
    {
        // DEFECTO SEC-01: "x' OR '1'='1" ignora el filtro.
        $r = $this->legacyPost('/juegos/php/consultar_ranking.php', [
            'nombre_juego' => 'laberinto', 'nivel' => "x' OR '1'='1", 'categoria' => "x' OR '1'='1",
        ])->json();
        $this->assertNotEmpty($r);
    }

    // ---------------------------------------------------------------- guardar_resultado

    /**
     * SEC-03/05/06 y FUN-01: guarda sin sesión un puntaje 999999, HTML en el nombre, un `id_usuario` ajeno y la fecha
     * como texto. Limpia lo que escribe.
     */
    public function test_guardar_resultado_acepta_valores_absurdos_y_html_sin_sesion(): void
    {
        $marca = 'zz_caract_'.uniqid();
        try {
            $r = $this->legacyPost('/juegos/php/guardar_resultado.php', [
                'nombre_juego_query' => $marca,
                'nombre_jugador_query' => '<img src=x onerror=alert(1)>',
                'grado_jugador_query' => '11',
                'avatar_jugador_query' => '1.png',
                'preguntas_correctas_query' => '999999',
                'tiempo_query' => '0',
                'nivel_query' => 'zz',
                'categoria_query' => 'zz',
                'id_usuario_logueado' => '1',
            ]);
            $this->assertSame('success', $r->json('status'));

            $fila = DB::table('resultado_juegos')->where('nombre_juego', $marca)->first();
            $this->assertNotNull($fila);
            $this->assertSame(999999, (int) $fila->puntaje);                 // DEFECTO SEC-06
            $this->assertSame('<img src=x onerror=alert(1)>', $fila->jugador); // DEFECTO SEC-05
            $this->assertSame(1, (int) $fila->id_usuario);                   // DEFECTO SEC-03 (suplantación)
            // DEFECTO FUN-01 / DAT-03: la fecha se guarda como texto d-m-Y H:i:s.
            $this->assertMatchesRegularExpression('/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}$/', $fila->fecha);
        } finally {
            DB::table('resultado_juegos')->where('nombre_juego', $marca)->delete();
        }
    }

    // ---------------------------------------------------------------- cambiarAvatar

    /**
     * SEC-03: cualquiera cambia el avatar de cualquier usuario en pedigital. Restaura el valor original al terminar.
     */
    public function test_cambiar_avatar_funciona_sin_sesion_y_para_cualquier_usuario(): void
    {
        $original = DB::connection('pedigital')->table('users')->where('id', 1)->value('avatar_explora');
        try {
            $r = $this->legacyPost('/mapplic/php/cambiarAvatar.php', ['id_usuario' => 1, 'avatar' => 7]);
            $this->assertSame('success', $r->json('status')); // DEFECTO SEC-03
            $this->assertSame(7, (int) DB::connection('pedigital')->table('users')->where('id', 1)->value('avatar_explora'));
        } finally {
            $this->legacyPost('/mapplic/php/cambiarAvatar.php', ['id_usuario' => 1, 'avatar' => (int) $original]);
        }
        $this->assertSame((int) $original, (int) DB::connection('pedigital')->table('users')->where('id', 1)->value('avatar_explora'));
    }

    /**
     * SEC-07: un avatar no numérico provoca un error fatal con la ruta del servidor.
     */
    public function test_cambiar_avatar_con_valor_no_numerico_expone_error_fatal(): void
    {
        $r = $this->legacyPost('/mapplic/php/cambiarAvatar.php', ['id_usuario' => 1, 'avatar' => 'abc']);
        $this->assertStringContainsString('Fatal error', $r->body()); // SEC-07
    }
}
