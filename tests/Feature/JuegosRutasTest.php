<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Fase 13, chequeo ESTÁTICO: todas las rutas LITERALES con las que cada juego referencia sus propios archivos existen en disco.
 *
 * Casi todo lo que cargan los juegos en ejecución es RELATIVO a la URL de la página (`fetch('js/preguntas.json')`,
 * `$.getJSON('datos.json')`, `../sounds/x.mp3`, `url(...)` de CSS), por eso la URL canónica lleva barra final. Esta prueba
 * lo comprueba sin navegador para cada juego, en tres fuentes:
 *   1. los `src`/`href` y los `url()` de los atributos `style` del HTML renderizado;
 *   2. los `url(...)` de los CSS propios, resueltos contra la carpeta del CSS;
 *   3. las cadenas literales con extensión de recurso en los JS propios (y en los compartidos `terminar_juego.js` y
 *      `pantalla_completa.js`), resueltas contra la URL de la página (la carpeta del juego).
 * Las rutas construidas por concatenación en tiempo de ejecución (`'img/' + n + '.png'`) no se ven aquí: las cubren las
 * pruebas de navegador (tests/Browser/juegos.spec.js, que falla ante cualquier respuesta 404 propia).
 *
 * REFERENCIAS ROTAS HEREDADAS conocidas (se reportan, NO se corrigen en esta fase): la prueba exige que sean EXACTAMENTE
 * estas, de modo que un recurso nuevo roto o uno arreglado haga fallar la prueba y obligue a actualizar la lista.
 */
class JuegosRutasTest extends TestCase
{
    /** Extensiones que se consideran recurso del juego al buscar literales en JS. */
    private const EXTENSIONES = 'json|mp3|wav|ogg|png|jpe?g|gif|webp|svg|ttf|woff2?|css|js|mp4|html';

    /** @var array<string, list<string>> JS y CSS PROPIOS de cada juego (relativos a su carpeta); los de terceros no se analizan. */
    private const PROPIOS = [
        'laberinto' => ['js/js2.js', 'css/css2.css', 'estilo-pagina.css'],
        'banderas-color' => ['script.js', 'style.css', 'estilo-pagina.css'],
        'puzzle' => ['script.js', 'styles.css', 'estilo-pagina.css'],
        'sopa_letras' => ['juego.js', 'wordfindgame.js', 'style.css', 'estilo-pagina.css'],
        'juego_ahorcado' => ['ahorcado.js', 'estilo-ahoracado.css', 'estilo-pagina.css'],
        'arrastra' => ['game.js', 'data.js', 'styles.css', 'estilo-pagina.css'],
        'completa' => ['script.js', 'styles.css', 'estilo-pagina.css'],
    ];

    /** @var list<string> Referencias rotas ya conocidas («juego: archivo que la contiene -> ruta resuelta»). */
    private const CONOCIDAS_ROTAS = [
        'laberinto: HTML -> juegos/juegos/laberinto/css/perdio_gano.png',
        'sopa_letras: style.css -> juegos/juegos/portadas/sopa_letras.png',
    ];

    /**
     * Resuelve `$ruta` contra la carpeta `$base` (ambas relativas a `public/`) quitando `.` y `..`.
     */
    private function resolver(string $ruta, string $base): string
    {
        $partes = array_values(array_filter(explode('/', $base), static fn ($p) => $p !== ''));
        foreach (explode('/', preg_replace('/[?#].*$/', '', $ruta)) as $s) {
            if ($s === '..') {
                array_pop($partes);
            } elseif ($s !== '.' && $s !== '') {
                $partes[] = $s;
            }
        }

        return implode('/', $partes);
    }

    /**
     * ¿Es una ruta local que hay que comprobar? (no URL externa, ni `data:`, ni raíz del sitio, ni plantilla dinámica).
     */
    private function esLocal(string $ruta): bool
    {
        return $ruta !== '' && ! preg_match('#^(https?:|//|/|data:|javascript:|\#|mailto:)#i', $ruta) && ! preg_match('/[{}$]|\+/', $ruta);
    }

    /**
     * Todas las rutas literales de cada juego existen en disco, salvo las dos conocidas.
     */
    public function test_las_rutas_literales_de_cada_juego_existen(): void
    {
        $publico = dirname(__DIR__, 2).'/public/';
        $faltan = [];
        $comprobadas = 0;

        foreach (config('juegos') as $slug) {
            $carpeta = "juegos/juegos/$slug/";
            $rutas = []; // ruta resuelta => origen

            // 1) HTML renderizado.
            $html = $this->app->make(Kernel::class)->handle(Request::create("http://localhost/$carpeta", 'GET'))->getContent();
            preg_match_all('/\s(?:src|href)="(http:\/\/localhost\/[^"]*)"/i', $html, $m);
            foreach ($m[1] as $url) {
                $rutas[ltrim(preg_replace('#^http://localhost#', '', preg_replace('/\?v=[0-9a-f]{10}$/', '', $url)), '/')] = 'HTML';
            }
            preg_match_all('/style="[^"]*url\((?:["\']?)http:\/\/localhost\/([^)"\']*)/i', $html, $m);
            foreach ($m[1] as $url) {
                $rutas[$url] = 'HTML';
            }

            // 2) CSS propios: url() relativas a la carpeta del propio CSS.
            // 3) JS propios y compartidos: literales relativos a la URL de la página.
            $archivos = array_map(static fn ($f) => $carpeta.$f, self::PROPIOS[$slug]);
            array_push($archivos, 'juegos/js/terminar_juego.js', 'juegos/js/pantalla_completa.js', 'juegos/css/terminar_juego.css', 'juegos/css/pantalla_completa.css');
            foreach (array_unique($archivos) as $archivo) {
                $this->assertFileExists($publico.$archivo, "$slug: falta el archivo propio $archivo");
                $contenido = (string) file_get_contents($publico.$archivo);
                $compartido = str_starts_with($archivo, 'juegos/js/') || str_starts_with($archivo, 'juegos/css/');
                if (str_ends_with($archivo, '.css')) {
                    $contenido = preg_replace('#/\*.*?\*/#s', '', $contenido);
                    preg_match_all('/url\(\s*["\']?([^)"\']+)["\']?\s*\)/', $contenido, $m);
                    foreach (array_filter($m[1], fn ($r) => $this->esLocal($r)) as $r) {
                        // Los CSS compartidos (juegos/css/…) resuelven contra su propia carpeta; los del juego, contra la del juego.
                        $rutas[$this->resolver($r, dirname($archivo))] = basename($archivo);
                    }
                } else {
                    preg_match_all('/["\']([^"\'\s]+\.(?:'.self::EXTENSIONES.')(?:\?[^"\'\s]*)?)["\']/i', $contenido, $m);
                    foreach (array_filter($m[1], fn ($r) => $this->esLocal($r)) as $r) {
                        // Un JS compartido o propio carga contra la URL de la página; los CSS, contra el CSS (arriba).
                        $rutas[$this->resolver($r, $carpeta)] = ($compartido ? 'compartido ' : '').basename($archivo);
                    }
                }
            }

            foreach ($rutas as $ruta => $origen) {
                // Los «volver» ya apuntan directamente a `/juegos` (test_el_volver_apunta_directo_al_portal). Se conserva la excepción
                // por si reaparece una referencia antigua a `../../index.html`: `/juegos/index.html` ya no es un archivo (lo redirige
                // Laravel con 301, lo cubre JuegosPaginasTest/PerfilPortalTest), así que no se exige en disco.
                if ($ruta === 'juegos/index.html') {
                    continue;
                }
                $comprobadas++;
                if (! file_exists($publico.$ruta)) {
                    $faltan[] = "$slug: $origen -> $ruta";
                }
            }
        }

        // Los literales de los JS compartidos que no existen para un juego concreto (p. ej. una imagen que usa solo otro)
        // no deben ocultar fallos: la lista de conocidas es EXACTA.
        sort($faltan);
        $esperadas = self::CONOCIDAS_ROTAS;
        sort($esperadas);
        $this->assertGreaterThan(100, $comprobadas, 'el escaneo debería haber comprobado muchas rutas');
        $this->assertSame($esperadas, $faltan, 'Referencias rotas distintas de las conocidas (se esperaba exactamente la lista CONOCIDAS_ROTAS)');
    }

    /**
     * Los enlaces «volver» de los juegos (JS propio y vistas Blade) apuntan DIRECTAMENTE a `/juegos`: ya no quedan usos de
     * `../../index.html`, que obligaba a un salto 301 (`/juegos/index.html` -> `/juegos`). Esa URL antigua sigue redirigiendo
     * para los marcadores guardados (lo cubre JuegosPaginasTest).
     */
    public function test_el_volver_apunta_directo_al_portal(): void
    {
        $raiz = dirname(__DIR__, 2);
        $archivos = array_merge(
            glob($raiz.'/public/juegos/juegos/*/*.js') ?: [],
            glob($raiz.'/public/juegos/juegos/*/js/*.js') ?: [],
            glob($raiz.'/resources/views/juegos/juego/*.blade.php') ?: [],
        );
        $this->assertNotEmpty($archivos);

        $conVolver = 0;
        foreach ($archivos as $archivo) {
            $contenido = (string) file_get_contents($archivo);
            $this->assertStringNotContainsString('../../index.html', $contenido, basename($archivo).' aún enlaza ../../index.html');
            $conVolver += substr_count($contenido, "'/juegos'");
        }
        // 13 usos en los JS + 11 en las vistas Blade.
        $this->assertSame(24, $conVolver, 'Número de enlaces «volver» a /juegos');
    }
}
