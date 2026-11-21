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
     * El sitio estático (CSS, JS y páginas de cada juego) se sirve con 200: el router deja pasar los archivos
     * legítimos, incluso los que cuelgan de carpetas que comparten nombre con una ruta de Laravel (`/juegos/…`,
     * `/miPerfil/…`). La portada ya no es un archivo (Fase 11): `/index.html` redirige (301) a `/`; tampoco lo son el
     * perfil ni el portal de juegos (Fase 12), cuyas URL antiguas redirigen (ver test_el_perfil_y_el_portal_se_sirven_desde_laravel).
     */
    public function test_sirve_el_sitio_estatico(): void
    {
        [$codigo, $tipo] = $this->pedir('/juegos/css/styles.css');
        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('text/css', $tipo);
        // Fase 13: los archivos de la carpeta de un juego se sirven tal cual; su index.html ya no existe y redirige (301).
        $this->assertSame(200, $this->pedir('/juegos/juegos/puzzle/estilo-pagina.css')[0]);
        $this->assertSame(301, $this->pedir('/juegos/juegos/puzzle/index.html')[0]);
        $this->assertSame(200, $this->pedir('/miPerfil/script.js')[0]);
        $this->assertSame(200, $this->pedir('/miPerfil/cerrar-sesion.js')[0]);
        $this->assertSame(200, $this->pedir('/juegos/js/anio-pie.js')[0]);
        $this->assertSame(301, $this->pedir('/index.html', $location)[0]);
        $this->assertSame($this->base, $location); // Laravel emite la raíz sin barra final
        // Los HTML de los mapas ya no existen (Fase 10): la URL antigua redirige, ver test_los_mapas_se_sirven_desde_laravel.
    }

    /**
     * Fase 12: `/perfil`, `/juegos` y `/juegos/` los atiende Laravel aunque `public/juegos/` sea una CARPETA real (el
     * router la envía a Laravel solo para esas dos URL exactas); las URL antiguas redirigen con 301 (con su consulta) y
     * las URL antiguas `/juegos/index.html` (marcadores guardados; los «volver» de los juegos ya apuntan directo a `/juegos`) siguen llegando al portal por esa redirección.
     */
    public function test_el_perfil_y_el_portal_se_sirven_desde_laravel(): void
    {
        foreach (['/juegos', '/juegos/'] as $ruta) {
            [$codigo, $tipo, $cuerpo] = $this->pedir($ruta);
            $this->assertSame(200, $codigo, $ruta);
            $this->assertStringContainsString('text/html', $tipo, $ruta);
            $this->assertStringContainsString('<title>Juegos Educativos Explora</title>', $cuerpo, $ruta);
            $this->assertStringContainsString('id="btn-iniciar" style="display: block;"', $cuerpo, $ruta);
        }

        [$codigo, , $cuerpo] = $this->pedir('/perfil');
        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('Debe iniciar sesión para ver su perfil.', $cuerpo);
        $this->assertStringNotContainsString('id="nombre"', $cuerpo);

        $this->assertSame(301, $this->pedir('/juegos/index.html', $location)[0]);
        $this->assertSame($this->base.'/juegos', $location);
        $this->assertSame(301, $this->pedir('/juegos/index.html?x=1', $location)[0]);
        $this->assertSame($this->base.'/juegos?x=1', $location);
        $this->assertSame(301, $this->pedir('/miPerfil.html', $location)[0]);
        $this->assertSame($this->base.'/perfil', $location);
    }

    /**
     * Las carpetas sin `index.html` siguen sin responder: ni listado ni 200 (el router da 404; Apache, 403 por
     * `-Indexes`). Solo `/juegos` y `/juegos/` son la excepción de Laravel; `/miPerfil/`, `/juegos/js/`, `/juegos/css/`…
     * no, y tampoco `/juegos/juegos/` (carpeta de los juegos).
     */
    public function test_las_carpetas_sin_index_no_se_listan(): void
    {
        foreach (['/miPerfil/', '/miPerfil', '/juegos/js/', '/juegos/js', '/juegos/css/', '/juegos/juegos/', '/juegos/images/avatars/', '/css/'] as $ruta) {
            [$codigo, , $cuerpo] = $this->pedir($ruta);
            $this->assertContains($codigo, [403, 404], $ruta);
            $this->assertStringNotContainsString('script.js', $cuerpo, "$ruta parece listar el contenido de la carpeta");
            $this->assertStringNotContainsString('Index of', $cuerpo, $ruta);
        }
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
                $this->assertStringNotContainsString($pista, $cuerpo, "La respuesta a $ruta filtra «{$pista}»");
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

        // `/` (portada), `/perfil` y el portal de juegos (Laravel) también se niegan; los archivos estáticos no tocan
        // la base y siguen sirviéndose.
        foreach (['/', '/perfil', '/juegos', '/juegos/', '/juegos/index.html', '/juegos/juegos/puzzle/'] as $ruta) {
            $this->assertSame(500, $this->pedir($ruta)[0], $ruta);
        }
        $this->assertSame(200, $this->pedir('/juegos/css/styles.css')[0]);
    }

    /**
     * `/` ya no es un archivo estático (Fase 11): la sirve Laravel (vista `inicio`) con 200 y HTML, como Apache con
     * `DirectoryIndex index.php index.html`. Ya no existe `public/index.html`.
     */
    public function test_la_raiz_la_sirve_laravel(): void
    {
        $this->assertFileDoesNotExist(dirname(__DIR__, 2).'/public/index.html');

        [$codigo, $tipo, $cuerpo] = $this->pedir('/');
        $this->assertSame(200, $codigo);
        $this->assertStringContainsString('text/html', $tipo);
        $this->assertStringContainsString('<title>Explora Colombia y aprende</title>', $cuerpo);
        $this->assertStringContainsString('id="mapplic"', $cuerpo);
    }

    /**
     * Una carpeta con index.html se sirve con «/» final y se redirige (301) si se pide sin ella. Desde la Fase 15 ya no queda
     * ninguna carpeta con `index.html` en `public/` (todas las páginas son vistas Blade; la demo de terceros de la sopa de letras
     * se retiró en la verificación posterior), así que la prueba crea una CARPETA TEMPORAL bajo `public/` y la borra siempre al
     * terminar (aunque falle): el servidor atiende lo que haya en disco en cada petición.
     */
    public function test_las_carpetas_con_index_html_se_sirven_y_redirigen(): void
    {
        $nombre = '_prueba_carpeta_'.bin2hex(random_bytes(4));
        $carpeta = dirname(__DIR__, 2).'/public/'.$nombre;
        mkdir($carpeta);
        file_put_contents($carpeta.'/index.html', '<!doctype html><title>Carpeta temporal</title>');

        try {
            [$codigo, $tipo, $cuerpo] = $this->pedir("/$nombre/");
            $this->assertSame(200, $codigo);
            $this->assertStringContainsString('text/html', $tipo);
            $this->assertStringContainsString('Carpeta temporal', $cuerpo);

            $this->assertSame(301, $this->pedir("/$nombre", $location)[0]);
            $this->assertSame("/$nombre/", $location);
        } finally {
            @unlink($carpeta.'/index.html');
            @rmdir($carpeta);
        }
    }

    /**
     * Ya no queda ninguna carpeta propia con `index.html` bajo `public/` (salvo dependencias locales sin versionar): si
     * reapareciera alguna, esta prueba avisa para que se decida si debe pasar a Blade (como las demás páginas).
     */
    public function test_no_quedan_carpetas_con_index_html_en_public(): void
    {
        $raiz = dirname(__DIR__, 2).'/public';
        $encontrados = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS),
            static fn (\SplFileInfo $f): bool => $f->getFilename() !== 'node_modules',
        ));
        foreach ($it as $archivo) {
            if ($archivo->getFilename() === 'index.html') {
                $encontrados[] = $archivo->getPathname();
            }
        }
        $this->assertSame([], $encontrados);
    }

    /**
     * Fase 15: `/lineaTiempo/` y `/ViajaPED/` (lista de config/paginas_estaticas.php) las atiende Laravel. Con barra -> 200 con
     * la vista; sin barra -> 301 a la forma con barra conservando la consulta; `/index.html` -> 301 (lo emite Laravel). Los
     * archivos estáticos de cada carpeta se sirven tal cual con su content-type y las subcarpetas no se listan.
     */
    public function test_linea_de_tiempo_y_viajaped_se_sirven_desde_laravel(): void
    {
        $titulos = ['lineaTiempo' => 'Línea de Tiempo Histórica', 'ViajaPED' => 'Épocas Históricas'];
        $this->assertSame(array_keys($titulos), array_values(require dirname(__DIR__, 2).'/config/paginas_estaticas.php'));

        foreach ($titulos as $slug => $titulo) {
            [$codigo, $tipo, $cuerpo] = $this->pedir("/$slug/");
            $this->assertSame(200, $codigo, $slug);
            $this->assertStringContainsString('text/html', $tipo, $slug);
            $this->assertStringContainsString("<title>$titulo</title>", $cuerpo, $slug);

            $this->assertSame(301, $this->pedir("/$slug?a=1", $location)[0], $slug);
            $this->assertSame("/$slug/?a=1", $location, $slug);

            $this->assertSame(301, $this->pedir("/$slug/index.html", $location)[0], $slug);
            $this->assertSame($this->base."/$slug/", $location, $slug);
        }
        // Los archivos de cada carpeta siguen sirviéndose directamente (con su content-type) y se leen con rutas relativas.
        $estaticos = [
            '/lineaTiempo/eventos.json' => 'json',
            '/lineaTiempo/script.js' => 'javascript',
            '/lineaTiempo/styles.css' => 'text/css',
            '/lineaTiempo/all.min.css' => 'text/css',
            '/lineaTiempo/gsap.min.js' => 'javascript',
            '/lineaTiempo/ScrollTrigger.min.js' => 'javascript',
            '/lineaTiempo/favicon.png' => 'image/png',
            '/lineaTiempo/img/explora.png' => 'image/png',
            '/ViajaPED/data.json' => 'json',
            '/ViajaPED/script.js' => 'javascript',
            '/ViajaPED/styles.css' => 'text/css',
            '/ViajaPED/img/fondo.png' => 'image/png',
            '/ViajaPED/epocas/antigua.svg' => 'image/svg+xml',
        ];
        foreach ($estaticos as $ruta => $tipoEsperado) {
            [$codigo, $tipo] = $this->pedir($ruta);
            $this->assertSame(200, $codigo, $ruta);
            $this->assertStringContainsString($tipoEsperado, $tipo, $ruta);
        }
        // Las subcarpetas no se listan (el router da 404; Apache, 403 por -Indexes).
        foreach (['/lineaTiempo/img/', '/lineaTiempo/img', '/lineaTiempo/webfonts/', '/ViajaPED/img/', '/ViajaPED/epocas/', '/ViajaPED/fonts'] as $ruta) {
            [$codigo, , $cuerpo] = $this->pedir($ruta);
            $this->assertContains($codigo, [403, 404], $ruta);
            $this->assertStringNotContainsString('explora.png', $cuerpo, $ruta);
        }
        // Distinción de mayúsculas (como Apache) y carpetas hermanas sin vista: no llegan a Laravel.
        foreach (['/lineatiempo/', '/LINEATIEMPO/', '/viajaped/', '/jsons/', '/miPerfil/'] as $ruta) {
            $this->assertContains($this->pedir($ruta)[0], [403, 404], $ruta);
        }
        // La carpeta ViajaPED/node_modules (si existe en local, sin versionar) tampoco se lista.
        $this->assertContains($this->pedir('/ViajaPED/node_modules/')[0], [403, 404]);
    }

    /**
     * Fase 13: las siete carpetas de juego (lista de config/juegos.php) las atiende Laravel. Con barra -> 200 con la
     * vista; sin barra -> 301 a la forma con barra conservando la consulta; `/index.html` -> 301 (lo emite Laravel).
     * Las demás carpetas sin index.html siguen sin responder y un slug desconocido no llega a ninguna vista.
     */
    public function test_los_juegos_se_sirven_desde_laravel(): void
    {
        foreach (require dirname(__DIR__, 2).'/config/juegos.php' as $slug) {
            [$codigo, $tipo, $cuerpo] = $this->pedir("/juegos/juegos/$slug/");
            $this->assertSame(200, $codigo, $slug);
            $this->assertStringContainsString('text/html', $tipo, $slug);
            $this->assertStringContainsString('<title>', $cuerpo, $slug);

            $this->assertSame(301, $this->pedir("/juegos/juegos/$slug?a=1", $location)[0], $slug);
            $this->assertSame("/juegos/juegos/$slug/?a=1", $location, $slug);

            $this->assertSame(301, $this->pedir("/juegos/juegos/$slug/index.html", $location)[0], $slug);
            $this->assertSame($this->base."/juegos/juegos/$slug/", $location, $slug);
        }
        // Los archivos estáticos de las carpetas de juego siguen sirviéndose tal cual, con su content-type.
        $estaticos = [
            '/juegos/juegos/laberinto/js/preguntas.json' => 'json',
            '/juegos/juegos/puzzle/categorias.json' => 'json',
            '/juegos/juegos/sopa_letras/datos.json' => 'json',
            '/juegos/juegos/juego_ahorcado/pistas.json' => 'json',
            '/juegos/juegos/completa/completa.json' => 'json',
            '/juegos/juegos/banderas-color/json_banderas.json' => 'json',
            '/juegos/juegos/arrastra/data.js' => 'javascript',
            '/juegos/juegos/arrastra/estilo-pagina.css' => 'text/css',
            '/juegos/juegos/sopa_letras/juego.js' => 'javascript',
            '/juegos/juegos/sounds/ok.mp3' => 'audio/mpeg',
        ];
        foreach ($estaticos as $ruta => $tipoEsperado) {
            [$codigo, $tipo] = $this->pedir($ruta);
            $this->assertSame(200, $codigo, $ruta);
            $this->assertStringContainsString($tipoEsperado, $tipo, $ruta);
        }
        // Las subcarpetas de los juegos no se listan (el router da 404; Apache, 403 por -Indexes).
        foreach (['/juegos/juegos/laberinto/js/', '/juegos/juegos/laberinto/js', '/juegos/juegos/laberinto/css/', '/juegos/juegos/puzzle/img/'] as $ruta) {
            [$codigo, , $cuerpo] = $this->pedir($ruta);
            $this->assertContains($codigo, [403, 404], $ruta);
            $this->assertStringNotContainsString('preguntas.json', $cuerpo, $ruta);
        }
        // Distinción de mayúsculas (como Apache) y carpetas hermanas sin vista: no llegan a Laravel.
        foreach (['/juegos/juegos/Puzzle/', '/juegos/juegos/assets/', '/juegos/juegos/sounds/', '/juegos/juegos/mapas/'] as $ruta) {
            $this->assertContains($this->pedir($ruta)[0], [403, 404], $ruta);
        }
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
     * @return array<string, array{0:string}> Los 7 PHP legados sustituidos por la API (lista única de config/servicios_retirados.php).
     */
    public static function phpSustituidos(): array
    {
        $rutas = array_map(static fn (string $r): string => '/'.$r, require dirname(__DIR__, 2).'/config/servicios_retirados.php');

        return array_combine($rutas, array_map(static fn (string $r): array => [$r], $rutas));
    }

    /**
     * Los PHP sustituidos responden 410 con JSON `{"status":"error","mensaje":"Servicio retirado"}`. Desde la Fase 15 sus
     * archivos ya no existen: el 410 lo da el propio router (en Apache, la ruta de Laravel; ver ServiciosRetiradosTest).
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
            // Chat legado: desactivado en desarrollo (credenciales de MySQL escritas en el código). Fuera de la Fase 15: el
            // archivo sigue en el repositorio, pero este servidor nunca lo ejecuta y NO es un 410 (no es un servicio retirado).
            '/mapplic/php/chat.php',
            '/mapplic/php/chat%20copy.php',
            '/MAPPLIC/PHP/CHAT.PHP',
            // `conexion*.php` se eliminaron en la Fase 15 (404 por inexistentes; antes, por bloqueo).
            '/mapplic/php/conexion.php',
            '/mapplic/php/conexion_pedigital.php',
            '/juegos/php/conexion.php',
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
            // Source maps de Bootstrap (existen en disco; solo los pide la consola del navegador): bloqueados como en public/.htaccess.
            '/juegos/librerias/bootstrap-5.3/css/bootstrap.min.css.map',
            '/juegos/librerias/bootstrap-5.3/js/bootstrap.bundle.min.js.map',
            '/JUEGOS/LIBRERIAS/BOOTSTRAP-5.3/CSS/BOOTSTRAP.CSS.MAP',
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

    /**
     * Una barra codificada (`%2F`, en mayúsculas o minúsculas) en la ruta se rechaza con 400, como lo hace Apache por defecto
     * (`AllowEncodedSlashes Off`). Antes el router la decodificaba y `/juegos/juegos%2Fpuzzle/` llegaba a Laravel (200 en
     * desarrollo, error en Apache). `%2E%2E` ya se rechazaba por separado (recorrido de directorios).
     */
    public function test_una_barra_codificada_se_rechaza(): void
    {
        foreach ([
            '/juegos/juegos%2Fpuzzle/',
            '/juegos/juegos%2fpuzzle/',
            '/climatico%2Fx',
            '/api%2Fsesion',
            '/%2F',
            '/js/%2Fexplora-api.js',
        ] as $ruta) {
            $this->assertSame(400, $this->pedir($ruta)[0], $ruta);
        }
        // Una consulta con %2F no es parte de la ruta: sigue funcionando.
        $this->assertSame(200, $this->pedir('/climatico?x=a%2Fb')[0]);
    }

    /**
     * Los source maps (`*.map`) dan 404 en el router aunque existan en disco, y la lista de extensiones sensibles del router
     * es la MISMA que la de `public/.htaccess` (si se añade una en un sitio y no en el otro, esta prueba falla).
     */
    public function test_las_extensiones_sensibles_coinciden_con_el_htaccess(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2).'/public/juegos/librerias/bootstrap-5.3/css/bootstrap.min.css.map');
        $this->assertSame(404, $this->pedir('/juegos/librerias/bootstrap-5.3/css/bootstrap.min.css.map')[0]);

        $this->assertSame(1, preg_match('/<FilesMatch "\\\.\(([a-z|]+)\)\$">\s*Require all denied/', file_get_contents(dirname(__DIR__, 2).'/public/.htaccess'), $ht));
        $this->assertSame(1, preg_match('/preg_match\(\'\/\\\.\(([a-z|]+)\)\$\/\', end\(\$minusculas\)\)/', file_get_contents(dirname(__DIR__, 2).'/servidor-desarrollo.php'), $router));
        $this->assertSame($ht[1], $router[1]);
        $this->assertContains('map', explode('|', $ht[1]));
    }
}
