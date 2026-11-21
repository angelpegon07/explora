<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Fase 13: las 7 páginas de juego (`/juegos/juegos/<slug>/`) son vistas Blade con un layout común (`layouts/juego`).
 *
 * Aquí se prueba con el kernel de Laravel (sin servidor HTTP): contenido de cada página, orden de sus scripts clave,
 * redirecciones 301, 404 de slugs desconocidos, enlaces del portal y coherencia de `public/.htaccess` con `config/juegos.php`.
 * Lo que necesita un servidor real (archivos estáticos con su content-type, carpetas sin listado, que la carpeta llegue a
 * Laravel) está en {@see ServidorDesarrolloTest}; las reglas de Apache se verificaron a mano con httpd (ver REGISTRO_CAMBIOS).
 *
 * Los datos de cada juego viven en `datosJuegos()`: título, ids críticos (los que usa el JS del juego) y el orden esperado de
 * los scripts. Si se retoca una página, esta tabla es lo único que hay que actualizar.
 */
class JuegosPaginasTest extends TestCase
{
    /**
     * GET que CONSERVA la barra final de la URL.
     *
     * `$this->get()` de Laravel recorta la barra final (`trim($uri, '/')`), y aquí la barra es justo lo que se prueba:
     * se arma la petición a mano y se pasa por el kernel HTTP, como hace tests/Equivalencia/comparar-paginas.php.
     *
     * @param  string  $uri  Ruta con su barra final y cadena de consulta si procede.
     */
    private function pedir(string $uri): TestResponse
    {
        return $this->createTestResponse($this->app->make(Kernel::class)->handle(Request::create('http://localhost'.$uri, 'GET')));
    }

    /**
     * Esperado por juego: título, ids críticos, script de jQuery y si `pantalla_completa.js` va ANTES de `terminar_juego.js`
     * (solo sopa_letras; el resto lo carga después).
     *
     * @return array<string, array{0:string,1:string,2:list<string>,3:string,4:bool}>
     */
    public static function datosJuegos(): array
    {
        return [
            'laberinto' => ['laberinto', 'Laberinto Espacial', ['mapa', 'player', 'timer_text', 'pregunta_text', 'opciones_container', 'categorias_container', 'miModal', 'miModal2'], 'assets/bootstrap/jquery.min.js', false],
            'banderas-color' => ['banderas-color', 'La Bandera de Colombia', ['contenedor_juego1', 'contenedor_juego2', 'contenedor_bandera', 'boton_validar', 'pregunta_adivina'], 'banderas-color/jquery.min.js', false],
            'puzzle' => ['puzzle', 'Rompecabezas Interactivo', ['puzzle-board', 'pieces-container', 'btn_ayuda', 'btn_reiniciar', 'myModal', 'myModal2', 'categorias'], 'assets/bootstrap/jquery.min.js', false],
            'sopa_letras' => ['sopa_letras', 'Sopa de letras', ['principal', 'pal', 'contador', 'nivelModal', 'final'], 'assets/bootstrap/jquery.min.js', true],
            'juego_ahorcado' => ['juego_ahorcado', 'Adivina la Palabra', ['principal', 'categoria', 'numero', 'image6', 'imagen_final'], 'assets/bootstrap/jquery.min.js', false],
            'arrastra' => ['arrastra', 'Arrastra y Suelta', ['categoryModal', 'categoriesGrid', 'gameArea', 'optionsContainer', 'checkAnswers'], 'assets/bootstrap/jquery.min.js', false],
            'completa' => ['completa', 'Completa la Oración', ['seccion-categorias', 'categorias-container', 'opciones-container', 'btn-siguiente', 'puntuacion'], 'assets/bootstrap/jquery.min.js', false],
        ];
    }

    /**
     * Cada juego responde 200 con barra final, con su `<title>`, sus ids críticos y recursos con URL absolutas.
     *
     * @param  list<string>  $ids
     *
     * @dataProvider juegosProvider
     */
    public function test_cada_juego_se_sirve_con_barra_final(string $slug, string $titulo, array $ids): void
    {
        $respuesta = $this->pedir("/juegos/juegos/$slug/");
        $respuesta->assertOk();
        $html = $respuesta->getContent();

        $this->assertStringContainsString('<title>'.e($titulo).'</title>', $html);
        $this->assertSame(1, substr_count($html, '<title>'), 'el layout emite un solo <title>');
        foreach ($ids as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html, "falta #$id en $slug");
        }
        // Ningún recurso relativo (src/href sin esquema ni «/»): el original dependía de la URL de la página.
        preg_match_all('/\s(?:src|href)="([^"]*)"/i', $html, $urls);
        foreach ($urls[1] as $url) {
            $this->assertMatchesRegularExpression('#^(https?://|\#|data:|javascript:|/(?!/))|^$#i', $url, "URL relativa en $slug: $url");
        }
        // El estilo propio está enlazado (ya no hay <style> inline) y el HTML no trae datos del servidor.
        $this->assertStringContainsString("/juegos/juegos/$slug/estilo-pagina.css", $html);
        $this->assertStringNotContainsString('<style', $html);
    }

    /**
     * Orden de carga de los scripts clave: jQuery < explora-api < terminar_juego (dependencias) y, en sopa_letras,
     * pantalla_completa ANTES de terminar_juego (orden inverso al resto, que se conserva).
     *
     * @dataProvider juegosProvider
     */
    public function test_orden_de_los_scripts_clave(string $slug, string $titulo, array $ids, string $jquery, bool $pantallaAntes): void
    {
        $html = $this->pedir("/juegos/juegos/$slug/")->getContent();

        $posJquery = strpos($html, $jquery);
        $posApi = strpos($html, '/js/explora-api.js');
        $posTerminar = strpos($html, '/juegos/js/terminar_juego.js');
        $posPantalla = strpos($html, '/juegos/js/pantalla_completa.js');

        foreach (['jquery' => $posJquery, 'explora-api' => $posApi, 'terminar_juego' => $posTerminar, 'pantalla_completa' => $posPantalla] as $nombre => $pos) {
            $this->assertNotFalse($pos, "$slug no carga $nombre");
        }
        $this->assertLessThan($posApi, $posJquery, "$slug: jQuery debe ir antes que explora-api.js");
        $this->assertLessThan($posTerminar, $posApi, "$slug: explora-api.js debe ir antes que terminar_juego.js");
        $pantallaAntes
            ? $this->assertLessThan($posTerminar, $posPantalla, "$slug: pantalla_completa.js va ANTES de terminar_juego.js")
            : $this->assertGreaterThan($posTerminar, $posPantalla, "$slug: pantalla_completa.js va DESPUÉS de terminar_juego.js");
    }

    /**
     * Particularidades de librerías que NO deben unificarse en esta fase: banderas-color usa su Bootstrap 4; arrastra no
     * carga pantalla_completa.css; sopa_letras y ahorcado no declaran `<meta viewport>`; sopa carga su JS extraído.
     */
    public function test_conservan_sus_particularidades(): void
    {
        $banderas = $this->pedir('/juegos/juegos/banderas-color/')->getContent();
        $this->assertStringContainsString('/juegos/juegos/banderas-color/bootstrap/bootstrap.min.css', $banderas);
        $this->assertStringNotContainsString('bootstrap-5.3', $banderas);

        $this->assertStringNotContainsString('pantalla_completa.css', $this->pedir('/juegos/juegos/arrastra/')->getContent());
        $this->assertStringContainsString('pantalla_completa.css', $this->pedir('/juegos/juegos/completa/')->getContent());

        foreach (['sopa_letras', 'juego_ahorcado'] as $slug) {
            $this->assertStringNotContainsString('name="viewport"', $this->pedir("/juegos/juegos/$slug/")->getContent(), $slug);
        }
        foreach (['laberinto', 'puzzle', 'completa'] as $slug) {
            $this->assertStringContainsString('name="viewport"', $this->pedir("/juegos/juegos/$slug/")->getContent(), $slug);
        }
        $sopa = $this->pedir('/juegos/juegos/sopa_letras/')->getContent();
        $this->assertStringContainsString('/juegos/juegos/sopa_letras/juego.js', $sopa);
        $this->assertFileExists(dirname(__DIR__, 2).'/public/juegos/juegos/sopa_letras/juego.js');
    }

    /**
     * La forma sin barra y la antigua `/index.html` redirigen (301) a la URL canónica CON barra, conservando la consulta.
     *
     * @dataProvider juegosProvider
     */
    public function test_sin_barra_e_index_html_redirigen_a_la_forma_canonica(string $slug): void
    {
        // Se compara la cabecera Location EXACTA: assertRedirect(url(...)) recortaría la barra final de ambos lados y
        // no detectaría un bucle (forma sin barra <-> canónica).
        $raiz = 'http://localhost';
        $casos = [
            "/juegos/juegos/$slug" => "/juegos/juegos/$slug/",
            "/juegos/juegos/$slug?x=1" => "/juegos/juegos/$slug/?x=1",
            "/juegos/juegos/$slug/index.html" => "/juegos/juegos/$slug/",
            "/juegos/juegos/$slug/index.html?x=1" => "/juegos/juegos/$slug/?x=1",
        ];
        foreach ($casos as $pedida => $destino) {
            $respuesta = $this->pedir($pedida);
            $respuesta->assertStatus(301);
            $this->assertSame($raiz.$destino, $respuesta->headers->get('Location'), $pedida);
        }
    }

    /**
     * Un slug fuera de la lista cerrada (o con otra capitalización) da el 404 normal de Laravel.
     */
    public function test_un_slug_desconocido_da_404(): void
    {
        foreach (['nope', 'Puzzle', 'assets', 'sounds', 'mapas', 'puzzle%2F..', 'laberinto-2'] as $slug) {
            $this->pedir("/juegos/juegos/$slug/")->assertNotFound();
            $this->pedir("/juegos/juegos/$slug")->assertNotFound();
            $this->pedir("/juegos/juegos/$slug/index.html")->assertNotFound();
        }
    }

    /**
     * El portal enlaza a la URL canónica de cada juego (con barra), ya no a `index.html`.
     */
    public function test_el_portal_enlaza_a_las_urls_canonicas(): void
    {
        $html = $this->pedir('/juegos')->getContent();
        $this->assertStringNotContainsString('/index.html', str_replace('juegos/index.html', '', $html));
        foreach (config('juegos') as $slug) {
            $this->assertStringContainsString(url("/juegos/juegos/$slug/"), $html, $slug);
            $this->assertStringNotContainsString("/juegos/juegos/$slug/index.html", $html, $slug);
        }
        // 5 enlaces <a href> y 2 onclick (arrastra y completa).
        $this->assertSame(5, preg_match_all('#<a href="[^"]*/juegos/juegos/[a-z_-]+/"#', $html));
        $this->assertSame(2, preg_match_all("#location\\.href = '[^']*/juegos/juegos/[a-z_-]+/'#", $html));
    }

    /**
     * `public/.htaccess` tiene que listar EXACTAMENTE los slugs de `config/juegos.php`: Apache no puede leer el archivo
     * de configuración, así que su regla se copia a mano y esta prueba vigila que no se desincronice.
     */
    public function test_el_htaccess_coincide_con_config_juegos(): void
    {
        $htaccess = (string) file_get_contents(dirname(__DIR__, 2).'/public/.htaccess');
        $this->assertSame(1, preg_match('#^\s*RewriteRule \^juegos/juegos/\(([^)]*)\)/\?\$ index\.php \[L\]$#m', $htaccess, $m), 'no se encontró la regla de los juegos en .htaccess');

        $enHtaccess = explode('|', $m[1]);
        $enConfig = config('juegos');
        sort($enHtaccess);
        sort($enConfig);
        $this->assertSame($enConfig, $enHtaccess);
        $this->assertCount(7, $enConfig);
    }

    /**
     * Seguridad de las vistas: nada de salida sin escapar (`{!! !!}`) ni de PHP crudo en el layout, los parciales y las
     * 7 vistas; y cada juego tiene su vista y su `estilo-pagina.css`.
     */
    public function test_las_vistas_no_usan_salida_sin_escapar(): void
    {
        $vistas = dirname(__DIR__, 2).'/resources/views';
        $archivos = array_merge(glob("$vistas/juegos/juego/*.blade.php"), glob("$vistas/juegos/partials/*.blade.php"), ["$vistas/layouts/juego.blade.php"]);
        $this->assertCount(7 + 5 + 1, $archivos);
        foreach ($archivos as $archivo) {
            $sinComentarios = preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($archivo));
            $this->assertStringNotContainsString('{!!', $sinComentarios, basename($archivo));
            $this->assertStringNotContainsString('<?php', $sinComentarios, basename($archivo));
            $this->assertStringNotContainsString('@php', $sinComentarios, basename($archivo));
        }
        foreach (config('juegos') as $slug) {
            $this->assertFileExists("$vistas/juegos/juego/$slug.blade.php");
            $this->assertFileExists(dirname(__DIR__, 2)."/public/juegos/juegos/$slug/estilo-pagina.css");
            $this->assertFileDoesNotExist(dirname(__DIR__, 2)."/public/juegos/juegos/$slug/index.html");
        }
    }

    /**
     * El nombre del juego que se envía a `/api/resultados` sigue escrito en el JS de cada juego (no se mueve al servidor).
     */
    public function test_los_nombres_de_juego_siguen_en_cada_js(): void
    {
        $publico = dirname(__DIR__, 2).'/public/juegos/juegos';
        $esperados = [
            'laberinto/js/js2.js' => ['laberinto'],
            'banderas-color/script.js' => ['Pinta la bandera', 'Adivina la bandera'],
            'puzzle/script.js' => ['Rompecabezas'],
            'sopa_letras/juego.js' => ['Sopa de letras'],
            'juego_ahorcado/ahorcado.js' => ['Ahorcado'],
            'arrastra/game.js' => ['Arrastra y suelta'],
            'completa/script.js' => ['Completa la oración'],
        ];
        foreach ($esperados as $archivo => $nombres) {
            $js = (string) file_get_contents("$publico/$archivo");
            foreach ($nombres as $nombre) {
                $this->assertMatchesRegularExpression('/["\']'.preg_quote($nombre, '/').'["\']/', $js, "$archivo debe enviar nombre_juego «{$nombre}»");
            }
        }
    }

    /**
     * Proveedor: una fila por juego (slug, título, ids, jQuery esperado, pantalla_completa antes de terminar_juego).
     *
     * @return array<string, array{0:string,1:string,2:list<string>,3:string,4:bool}>
     */
    public static function juegosProvider(): array
    {
        return self::datosJuegos();
    }
}
