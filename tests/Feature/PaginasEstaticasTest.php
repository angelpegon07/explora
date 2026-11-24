<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Fase 15: `/lineaTiempo/` y `/ViajaPED/` son vistas Blade (`resources/views/paginas/<slug>.blade.php`, layout `layouts/base`)
 * servidas por `PaginaEstaticaController`; sus `index.html` se eliminaron.
 *
 * Con el kernel de Laravel (sin servidor HTTP) se prueba: contenido de cada página, ids que usa su JS, orden de scripts,
 * redirecciones 301, 404 de nombres desconocidos, enlaces del menú circular y coherencia de `public/.htaccess` con
 * `config/paginas_estaticas.php`. Lo que necesita un servidor real (estáticos con su content-type, carpetas sin listado, que la
 * carpeta llegue a Laravel) está en {@see ServidorDesarrolloTest}; las reglas de Apache se verificaron a mano con httpd.
 */
class PaginasEstaticasTest extends TestCase
{
    /**
     * GET que CONSERVA la barra final de la URL (`$this->get()` la recorta y aquí la barra es justo lo que se prueba).
     *
     * @param  string  $uri  Ruta con su barra final y cadena de consulta si procede.
     */
    private function pedir(string $uri): TestResponse
    {
        return $this->createTestResponse($this->app->make(Kernel::class)->handle(Request::create('http://localhost'.$uri, 'GET')));
    }

    /**
     * Esperado por página: slug, título, ids que usa su `script.js` y scripts en el orden en que deben cargarse.
     *
     * @return array<string, array{0:string,1:string,2:list<string>,3:list<string>}>
     */
    public static function paginas(): array
    {
        return [
            'lineaTiempo' => ['lineaTiempo', 'Línea de Tiempo Histórica', ['timeline', 'modalOverlay', 'modalClose', 'modalContent', 'goTop', 'toggleMenu', 'navMenu', 'navMenuList'], ['gsap.min.js', 'ScrollTrigger.min.js', 'script.js']],
            'ViajaPED' => ['ViajaPED', 'Épocas Históricas', ['background', 'tooltip', 'header-title', 'eras-section', 'era-details-section', 'element-modal', 'modal-title', 'modal-content'], ['script.js']],
        ];
    }

    /**
     * Cada página responde 200 con barra final: `<title>` único, ids críticos, ningún `<style>`/`<script>` inline y solo URL
     * absolutas (el original dependía de rutas relativas en el HTML).
     *
     * @param  list<string>  $ids
     *
     * @dataProvider paginas
     */
    public function test_cada_pagina_se_sirve_con_barra_final(string $slug, string $titulo, array $ids): void
    {
        $respuesta = $this->pedir("/$slug/");
        $respuesta->assertOk();
        $html = $respuesta->getContent();

        $this->assertStringContainsString('<title>'.e($titulo).'</title>', $html);
        $this->assertSame(1, substr_count($html, '<title>'));
        $this->assertStringContainsString('<html lang="es">', $html);
        foreach ($ids as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html, "falta #$id en $slug");
        }
        $this->assertStringNotContainsString('<style', $html);
        $this->assertSame(0, preg_match('#<script\b(?![^>]*\bsrc=)#i', $html), 'no debe haber JS inline');
        preg_match_all('/\s(?:src|href)="([^"]*)"/i', $html, $urls);
        foreach ($urls[1] as $url) {
            $this->assertMatchesRegularExpression('#^(https?://|\#|data:|javascript:|/(?!/))|^$#i', $url, "URL relativa en $slug: $url");
        }
    }

    /**
     * Los scripts se cargan en su orden (GSAP < ScrollTrigger < script.js en lineaTiempo) y desde su carpeta, al final del body.
     *
     * @param  list<string>  $ids
     * @param  list<string>  $scripts
     *
     * @dataProvider paginas
     */
    public function test_orden_de_los_scripts(string $slug, string $titulo, array $ids, array $scripts): void
    {
        $html = $this->pedir("/$slug/")->getContent();
        preg_match_all('#<script src="([^"]*)"></script>#', $html, $m);
        // Las URL llevan `?v=<10 hex>` (asset_v): se comprueba el formato y se compara la ruta sin la query.
        foreach ($m[1] as $u) {
            $this->assertMatchesRegularExpression('/\?v=[0-9a-f]{10}$/', $u);
        }
        $m[1] = array_map(static fn (string $u): string => (string) preg_replace('/\?v=[0-9a-f]{10}$/', '', $u), $m[1]);

        // `js/teclado.js` (teclado en elementos role="button", Fase 17F) lo carga el layout base en TODAS las páginas y va el
        // último: se comprueba aparte para que esta prueba siga fijando solo los scripts propios de cada página.
        $this->assertSame(url('/js/teclado.js'), end($m[1]), 'teclado.js debe cargarse el último');
        $this->assertSame(array_map(static fn (string $s): string => url("/$slug/$s"), $scripts), array_slice($m[1], 0, -1));
        // Todos tras el HTML que usan (el último `</div>` del cuerpo precede al primer script).
        $this->assertGreaterThan(strrpos($html, '</div>'), strpos($html, '<script'));
        // Las hojas de estilo van en el <head>, antes del <body>.
        $this->assertLessThan(strpos($html, '<body'), strpos($html, "/$slug/styles.css"));
    }

    /**
     * Particularidades de cada original que se conservan: lineaTiempo trae Font Awesome local y su favicon; ViajaPED no tenía
     * favicon (y no carga el de los juegos que pone el layout por defecto).
     */
    public function test_conservan_sus_particularidades(): void
    {
        $linea = $this->pedir('/lineaTiempo/')->getContent();
        $this->assertStringContainsString('/vendor/fontawesome/css/all.min.css', $linea);
        $this->assertStringContainsString('<link rel="icon" type="image/jpg" href="'.url('/lineaTiempo/favicon.png').'" />', $linea);
        $this->assertStringNotContainsString('juegos/images/Isotipo.png', $linea);

        $viaja = $this->pedir('/ViajaPED/')->getContent();
        $this->assertStringNotContainsString('rel="icon"', $viaja);
        $this->assertStringNotContainsString('shortcut icon', $viaja);
        $this->assertSame(20, substr_count($viaja, 'class="tunnel-circle"'));
        $this->assertSame(5, substr_count($viaja, 'class="era-item"'));
    }

    /**
     * La forma sin barra y `index.html` redirigen (301) a la forma canónica CON barra, conservando la consulta. Se compara la
     * cabecera Location exacta (assertRedirect recortaría la barra final y no detectaría un bucle).
     *
     * @dataProvider paginas
     */
    public function test_sin_barra_e_index_html_redirigen_a_la_forma_canonica(string $slug): void
    {
        $casos = [
            "/$slug" => "/$slug/",
            "/$slug?x=1" => "/$slug/?x=1",
            "/$slug/index.html" => "/$slug/",
            "/$slug/index.html?x=1" => "/$slug/?x=1",
        ];
        foreach ($casos as $pedida => $destino) {
            $respuesta = $this->pedir($pedida);
            $respuesta->assertStatus(301);
            $this->assertSame('http://localhost'.$destino, $respuesta->headers->get('Location'), $pedida);
        }
        // Los `index.html` originales ya no existen.
        $this->assertFileDoesNotExist(public_path("$slug/index.html"));
    }

    /**
     * Otros nombres o capitalizaciones dan el 404 normal de Laravel (lista cerrada, se respetan las mayúsculas).
     */
    public function test_un_nombre_desconocido_da_404(): void
    {
        foreach (['lineatiempo', 'LINEATIEMPO', 'viajaped', 'ViajaPed', 'miPerfil', 'jsons', 'lineaTiempo2', 'img'] as $nombre) {
            $this->pedir("/$nombre/")->assertNotFound();
            $this->pedir("/$nombre/index.html")->assertNotFound();
        }
    }

    /**
     * El menú circular (portada y mapas) enlaza a las URL canónicas CON barra y ya no a `index.html`.
     */
    public function test_el_menu_circular_enlaza_a_las_urls_canonicas(): void
    {
        foreach (['/', '/climatico', '/etnias'] as $pagina) {
            $html = $this->pedir($pagina)->getContent();
            $this->assertStringContainsString('id="btn_menu_linea_tiempo" href="'.url('/lineaTiempo').'/" target="_blank"', $html, $pagina);
            $this->assertStringContainsString('id="btn_menu_viaja_ped" href="'.url('/ViajaPED').'/" target="_blank"', $html, $pagina);
            $this->assertStringNotContainsString('lineaTiempo/index.html', $html, $pagina);
            $this->assertStringNotContainsString('ViajaPED/index.html', $html, $pagina);
        }
    }

    /**
     * `public/.htaccess` debe listar EXACTAMENTE los slugs de `config/paginas_estaticas.php`: Apache no puede leer el archivo
     * de configuración, así que su regla se copia a mano y esta prueba vigila que no se desincronice.
     */
    public function test_el_htaccess_coincide_con_config_paginas_estaticas(): void
    {
        $htaccess = (string) file_get_contents(dirname(__DIR__, 2).'/public/.htaccess');
        $this->assertSame(1, preg_match('#^\s*RewriteRule \^\(([^)]*)\)/\?\$ index\.php \[L\]$#m', $htaccess, $m), 'no se encontró la regla de las páginas estáticas en .htaccess');

        $enHtaccess = explode('|', $m[1]);
        $enConfig = config('paginas_estaticas');
        sort($enHtaccess);
        sort($enConfig);
        $this->assertSame($enConfig, $enHtaccess);
        $this->assertSame(['ViajaPED', 'lineaTiempo'], $enConfig);
    }

    /**
     * Los archivos que cargan el HTML y el JS de cada página existen en su carpeta (los datos JSON y los CSS/JS enlazados).
     */
    public function test_los_archivos_de_cada_carpeta_existen(): void
    {
        foreach (['lineaTiempo' => ['eventos.json', 'styles.css', 'gsap.min.js', 'ScrollTrigger.min.js', 'script.js', 'favicon.png', 'img/explora.png'],
            'ViajaPED' => ['data.json', 'styles.css', 'script.js', 'img/fondo.png', 'img/maquina.png', 'fonts/LondrinaSolid-Regular.ttf']] as $slug => $archivos) {
            foreach ($archivos as $archivo) {
                $this->assertFileExists(public_path("$slug/$archivo"), "$slug/$archivo");
            }
        }
        // Código muerto retirado: dependencias de React que ninguna página usa.
        $this->assertFileDoesNotExist(public_path('ViajaPED/package.json'));
        $this->assertFileDoesNotExist(public_path('ViajaPED/package-lock.json'));
    }
}
