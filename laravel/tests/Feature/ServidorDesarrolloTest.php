<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Pruebas del router del servidor de desarrollo (`laravel/servidor-desarrollo.php`).
 *
 * Arrancan un `php -S` REAL en un puerto libre de 127.0.0.1 con `laravel/public` (documento raíz desde la Fase 9) como DocumentRoot y hacen
 * peticiones HTTP de verdad: es la única forma de comprobar lo que importa (que el servidor integrado nunca
 * ejecute PHP legado ni sirva archivos sensibles). El proceso se arranca con las bases de PRUEBA y con
 * `EXPLORA_SOLO_BD_PRUEBA=1`, por lo que el router se negaría a atender `/api` contra bases reales.
 *
 * Es una clase de PHPUnit pura (sin arrancar Laravel en el proceso de la prueba): solo necesita HTTP.
 */
class ServidorDesarrolloTest extends TestCase
{
    /** @var resource|null Proceso del servidor integrado. */
    private $proceso = null;

    /** @var string URL base del servidor, p. ej. `http://127.0.0.1:54321`. */
    private string $base = '';

    /**
     * Arranca el servidor con las bases de prueba.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->arrancar([]);
    }

    /**
     * Arranca un `php -S` con el router y espera (hasta 10 s) a que responda.
     *
     * @param  array<string,string>  $entornoExtra  Variables que sustituyen a las de prueba por defecto (p. ej. para
     *                                              probar la guarda de bases con una base que no acaba en `_test`).
     */
    private function arrancar(array $entornoExtra): void
    {
        // Proyecto Laravel (laravel/) y documento raíz del sitio (laravel/public).
        $laravel = dirname(__DIR__, 2);
        $raiz = $laravel.'/public';
        $puerto = $this->puertoLibre();
        $this->base = 'http://127.0.0.1:'.$puerto;

        // Entorno del proceso actual más las variables que fuerzan bases de prueba.
        $entorno = array_merge(getenv(), [
            'DB_DATABASE' => 'explora_test',
            'PEDIGITAL_DB_DATABASE' => 'pedigital_test',
            'EXPLORA_SOLO_BD_PRUEBA' => '1',
        ], $entornoExtra);

        $this->proceso = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.$puerto, '-t', $raiz, $laravel.'/servidor-desarrollo.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            $laravel,
            $entorno
        );
        $this->assertIsResource($this->proceso, 'No se pudo arrancar php -S');

        // Mientras el servidor arranca, fsockopen avisa «conexión rechazada»: se silencia con un manejador
        // temporal porque PHPUnit convierte esos avisos en fallos incluso con @.
        set_error_handler(static fn (): bool => true);
        try {
            for ($i = 0; $i < 100; $i++) {
                if ($conexion = fsockopen('127.0.0.1', $puerto, $codigo, $mensaje, 0.2)) {
                    fclose($conexion);

                    return;
                }
                usleep(100000);
            }
        } finally {
            restore_error_handler();
        }
        $this->fail('El servidor de desarrollo no respondió a tiempo');
    }

    /**
     * Detiene siempre el servidor, pase o falle la prueba.
     */
    protected function tearDown(): void
    {
        $this->detener();
        parent::tearDown();
    }

    /**
     * Detiene el servidor si está en marcha.
     */
    private function detener(): void
    {
        if (is_resource($this->proceso)) {
            proc_terminate($this->proceso);
            proc_close($this->proceso);
            $this->proceso = null;
        }
    }

    /**
     * Pide al sistema un puerto libre (abre el puerto 0 y lo cierra).
     *
     * @return int Número de puerto disponible en 127.0.0.1.
     */
    private function puertoLibre(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $puerto = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        return $puerto;
    }

    /**
     * Hace un GET sin seguir redirecciones.
     *
     * @param  string  $ruta  Ruta tal cual se envía (puede llevar %2e, mayúsculas…).
     * @param  string|null  $cabeceraLocation  Si se pasa una variable, recibe el valor de la cabecera `Location`.
     * @return array{0:int,1:string,2:string} Código HTTP, cabecera Content-Type (vacía si no hay) y cuerpo.
     */
    private function pedir(string $ruta, ?string &$cabeceraLocation = null): array
    {
        $contexto = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 30]]);
        $cuerpo = (string) @file_get_contents($this->base.$ruta, false, $contexto);
        $this->assertNotEmpty($http_response_header ?? [], "Sin respuesta HTTP para $ruta");

        $codigo = (int) substr($http_response_header[0], 9, 3);
        $tipo = '';
        foreach ($http_response_header as $cabecera) {
            if (stripos($cabecera, 'Content-Type:') === 0) {
                $tipo = trim(substr($cabecera, 13));
            }
            if (stripos($cabecera, 'Location:') === 0) {
                $cabeceraLocation = trim(substr($cabecera, 9));
            }
        }
        $this->assertStringNotContainsString('/Users/', $cuerpo, "La respuesta a $ruta revela rutas del sistema");

        return [$codigo, $tipo, $cuerpo];
    }

    /**
     * El sitio estático (portada y juegos) se sirve con 200: el router deja pasar los archivos legítimos.
     */
    public function test_sirve_el_sitio_estatico(): void
    {
        $this->assertSame(200, $this->pedir('/index.html')[0]);
        $this->assertSame(200, $this->pedir('/juegos/index.html')[0]);
        // Los HTML de los mapas ya no existen (Fase 10): la URL antigua redirige, ver test_los_mapas_se_sirven_desde_laravel.
        $this->assertSame(200, $this->pedir('/miPerfil.html')[0]);
    }

    /**
     * Las rutas que no existen como archivo llegan a Laravel (como el RewriteRule de Apache): `/climatico` da 200 con la
     * página Blade, `/climatico.html` redirige (301) a ella y un slug desconocido da el 404 de Laravel.
     */
    public function test_los_mapas_se_sirven_desde_laravel(): void
    {
        [$codigo, $tipo, $cuerpo] = $this->pedir('/climatico');
        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('text/html', $tipo);
        $this->assertStringContainsString('<title>Mapa Climático de Colombia</title>', $cuerpo);

        $this->assertSame(301, $this->pedir('/climatico.html', $location)[0]);
        $this->assertSame($this->base.'/climatico', $location);

        // Con barra final: se quita (301), como la regla «Trailing Slashes» de Apache.
        $this->assertSame(301, $this->pedir('/climatico/', $location)[0]);
        $this->assertSame('/climatico', $location);
    }

    /**
     * Una ruta desconocida da el 404 HTML de Laravel (no el texto plano del router) y no filtra información interna:
     * ni trazas, ni rutas del sistema, ni la versión del framework.
     */
    public function test_una_ruta_desconocida_da_el_404_de_laravel_sin_filtrar_informacion(): void
    {
        foreach (['/inexistente', '/climatico.json/otra', '/no/existe/nada.html'] as $ruta) {
            [$codigo, $tipo, $cuerpo] = $this->pedir($ruta);
            $this->assertSame(404, $codigo, $ruta);
            $this->assertStringContainsString('text/html', $tipo, $ruta);
            $this->assertStringContainsString('Not Found', $cuerpo, $ruta);
            foreach (['Stack trace', 'vendor/', 'Illuminate\\', 'NotFoundHttpException', 'APP_KEY', 'laravel/framework'] as $pista) {
                $this->assertStringNotContainsString($pista, $cuerpo, "La respuesta a $ruta filtra «$pista»");
            }
        }
    }

    /**
     * La guarda de bases de prueba (`EXPLORA_SOLO_BD_PRUEBA=1`) también cubre las páginas Blade, no solo `/api`:
     * con una base que no acaba en `_test` el router se niega (500) y NO entrega la petición a Laravel.
     */
    public function test_la_guarda_de_bases_de_prueba_cubre_las_paginas(): void
    {
        $this->detener();
        $this->arrancar(['DB_DATABASE' => 'explora']);

        foreach (['/climatico', '/inexistente', '/api/sesion'] as $ruta) {
            [$codigo, , $cuerpo] = $this->pedir($ruta);
            $this->assertSame(500, $codigo, $ruta);
            $this->assertStringContainsString('solo para bases de prueba', $cuerpo, $ruta);
            $this->assertStringNotContainsString('<title>', $cuerpo, $ruta);
        }

        // Los archivos estáticos no tocan la base: siguen sirviéndose.
        $this->assertSame(200, $this->pedir('/index.html')[0]);
    }

    /**
     * `/` devuelve el index.html del sitio (como `DirectoryIndex index.html index.php` en Apache) y NO el
     * front controller de Laravel, aunque `public/index.php` exista: `php -S` lo preferiría sin la ayuda del router.
     */
    public function test_la_raiz_sirve_index_html(): void
    {
        [$codigo, $tipo, $cuerpo] = $this->pedir('/');
        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('text/html', $tipo);
        $this->assertSame(file_get_contents(dirname(__DIR__, 2).'/public/index.html'), $cuerpo);
    }

    /**
     * Una carpeta con index.html se sirve con «/» final y se redirige (301) si se pide sin ella.
     */
    public function test_las_carpetas_con_index_html_se_sirven_y_redirigen(): void
    {
        $this->assertSame(200, $this->pedir('/juegos/')[0]);
        $this->assertSame(301, $this->pedir('/juegos')[0]);
    }

    /**
     * `/api/sesion` llega a Laravel (ve la ruta con su prefijo) y, sin sesión, responde 401 en JSON: demuestra que
     * la API y el sitio comparten origen y que el router no altera REQUEST_URI.
     */
    public function test_la_api_llega_a_laravel_y_responde_json(): void
    {
        [$codigo, $tipo, $cuerpo] = $this->pedir('/api/sesion');
        $this->assertSame(401, $codigo);
        $this->assertStringContainsString('application/json', $tipo);
        $this->assertIsArray(json_decode($cuerpo, true));
    }

    /**
     * @return array<string, array{0:string}> Los 7 PHP legados sustituidos por la API.
     */
    public static function phpSustituidos(): array
    {
        $rutas = [
            '/mapplic/php/iniciarSesion.php', '/mapplic/php/cambiarAvatar.php', '/mapplic/php/consultarInfo.php',
            '/mapplic/php/buscarPreguntas.php', '/juegos/php/guardar_resultado.php',
            '/juegos/php/consultar_ranking.php', '/juegos/php/consultar_historial.php',
        ];

        return array_combine($rutas, array_map(static fn (string $r): array => [$r], $rutas));
    }

    /**
     * Los PHP sustituidos responden 410 con JSON `{"status":"error","mensaje":"Servicio retirado"}` y NO se
     * ejecutan (el legado devolvía hashes y era inyectable).
     *
     * @dataProvider phpSustituidos
     */
    public function test_los_php_sustituidos_responden_410(string $ruta): void
    {
        [$codigo, $tipo, $cuerpo] = $this->pedir($ruta);
        $this->assertSame(410, $codigo);
        $this->assertStringContainsString('application/json', $tipo);
        $this->assertSame(['status' => 'error', 'mensaje' => 'Servicio retirado'], json_decode($cuerpo, true));
    }

    /**
     * El bloqueo no se evade cambiando mayúsculas (APFS no distingue y Apache con [NC] tampoco).
     */
    public function test_el_bloqueo_no_distingue_mayusculas(): void
    {
        $this->assertSame(410, $this->pedir('/MAPPLIC/PHP/CONSULTARINFO.PHP')[0]);
    }

    /**
     * @return array<string, array{0:string}> Rutas que deben dar 404.
     */
    public static function rutasProhibidas(): array
    {
        $rutas = [
            // PATH_INFO: php -S ejecutaría consultarInfo.php si el router devolviera false.
            '/mapplic/php/consultarInfo.php/x.css',
            // Chat legado: desactivado en desarrollo (credenciales de MySQL escritas en el código).
            '/mapplic/php/chat.php',
            '/mapplic/php/conexion.php',
            // Ya no existen bajo el docroot (Fase 9): dan 404 por inexistentes, no por un bloqueo por nombre.
            '/laravel/.env',
            '/laravel/composer.json',
            '/vendor/autoload.php',
            '/vendor/composer/installed.json',
            '/storage/logs/laravel.log',
            '/.env',
            '/.htaccess',
            // `public/index.php` es el front controller: se pide solo vía /api; directo no se ejecuta ni se muestra su código.
            '/index.php',
            '/.git/HEAD',
            '/DOCUMENTACION_MEJORAMIENTO/REGISTRO_CAMBIOS.md',
            '/dump.sql',
        ];

        return array_combine($rutas, array_map(static fn (string $r): array => [$r], $rutas));
    }

    /**
     * Código, credenciales, repositorio, documentación y volcados no se sirven nunca (404), y el PHP «no
     * sustituido» tampoco se ejecuta.
     *
     * @dataProvider rutasProhibidas
     */
    public function test_las_rutas_sensibles_responden_404(string $ruta): void
    {
        $this->assertSame(404, $this->pedir($ruta)[0]);
    }

    /**
     * `/index.php` no expone el código fuente del front controller (404 sin cuerpo con «<?php»).
     */
    public function test_index_php_no_expone_la_fuente(): void
    {
        [$codigo, , $cuerpo] = $this->pedir('/index.php');
        $this->assertSame(404, $codigo);
        $this->assertStringNotContainsString('<?php', $cuerpo);
    }

    /**
     * Un intento de salir de la raíz con `..` codificado (`%2e%2e`) se rechaza (400 o 404), nunca se sirve.
     */
    public function test_el_recorrido_de_directorios_se_rechaza(): void
    {
        $this->assertContains($this->pedir('/%2e%2e/etc/passwd')[0], [400, 404]);
    }
}
