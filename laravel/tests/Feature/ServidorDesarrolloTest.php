<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Pruebas del router del servidor de desarrollo (`laravel/servidor-desarrollo.php`).
 *
 * Arrancan un `php -S` REAL en un puerto libre de 127.0.0.1 con la raíz del repositorio como DocumentRoot y hacen
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
     * Arranca el servidor y espera (hasta 10 s) a que responda.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $raiz = dirname(__DIR__, 3);
        $puerto = $this->puertoLibre();
        $this->base = 'http://127.0.0.1:'.$puerto;

        // Entorno del proceso actual más las variables que fuerzan bases de prueba.
        $entorno = array_merge(getenv(), [
            'DB_DATABASE' => 'explora_test',
            'PEDIGITAL_DB_DATABASE' => 'pedigital_test',
            'EXPLORA_SOLO_BD_PRUEBA' => '1',
        ]);

        $this->proceso = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.$puerto, '-t', $raiz, $raiz.'/laravel/servidor-desarrollo.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            $raiz,
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
        if (is_resource($this->proceso)) {
            proc_terminate($this->proceso);
            proc_close($this->proceso);
        }
        parent::tearDown();
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
     * @return array{0:int,1:string,2:string} Código HTTP, cabecera Content-Type (vacía si no hay) y cuerpo.
     */
    private function pedir(string $ruta): array
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
            '/laravel/.env',
            '/laravel/composer.json',
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
     * Un intento de salir de la raíz con `..` codificado (`%2e%2e`) se rechaza (400 o 404), nunca se sirve.
     */
    public function test_el_recorrido_de_directorios_se_rechaza(): void
    {
        $this->assertContains($this->pedir('/%2e%2e/etc/passwd')[0], [400, 404]);
    }
}
