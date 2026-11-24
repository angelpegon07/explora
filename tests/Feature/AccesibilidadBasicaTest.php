<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Accesibilidad básica y PWA mínimo (Fase 17F-C), comprobados sobre el HTML que renderiza Laravel.
 *
 * No escriben en la base de datos: solo renderizan páginas públicas y leen archivos de `public/`. Cubren lo que se
 * puede verificar sin navegador; el comportamiento real del teclado y del foco lo ejercitan las pruebas E2E.
 *
 * Qué se vigila:
 *  - el viewport permite ampliar (sin `user-scalable=no` ni `maximum-scale=1`);
 *  - enlace «Saltar al contenido» y landmark principal (`<main` o `role="main"`);
 *  - al menos un `<h1>` por página;
 *  - ninguna `<img>` sin atributo `alt` (las decorativas llevan `alt=""`);
 *  - el botón del menú de accesibilidad tiene nombre accesible y estado `aria-expanded`;
 *  - manifiesto web (JSON válido, campos requeridos e iconos con las dimensiones declaradas) y `theme-color`;
 *  - el CSS propio respeta `prefers-reduced-motion`.
 */
class AccesibilidadBasicaTest extends TestCase
{
    /**
     * Renderiza una ruta CONSERVANDO la barra final (las páginas de juego la necesitan; `$this->get()` la recorta).
     *
     * @param  string  $uri  Ruta, p. ej. `/juegos/juegos/sopa_letras/`.
     * @return string HTML de la respuesta (debe ser 200).
     */
    private function html(string $uri): string
    {
        $respuesta = $this->createTestResponse($this->app->make(Kernel::class)->handle(Request::create('http://localhost'.$uri, 'GET')));
        $respuesta->assertOk();

        return $respuesta->getContent();
    }

    /**
     * Páginas representativas: portada, portal de juegos, un mapa temático y un juego (layout `juego`).
     *
     * @return array<string, array{0:string}>
     */
    public static function paginasProvider(): array
    {
        return [
            'portada' => ['/'],
            'portal de juegos' => ['/juegos'],
            'mapa temático' => ['/vertientes'],
            'juego: sopa de letras' => ['/juegos/juegos/sopa_letras/'],
            'juego: laberinto' => ['/juegos/juegos/laberinto/'],
        ];
    }

    /**
     * @dataProvider paginasProvider
     */
    public function test_el_viewport_permite_ampliar(string $uri): void
    {
        $html = $this->html($uri);

        $this->assertStringNotContainsString('user-scalable=no', $html);
        $this->assertStringNotContainsString('maximum-scale=1', $html);
    }

    /**
     * @dataProvider paginasProvider
     */
    public function test_hay_enlace_de_salto_landmark_principal_y_h1(string $uri): void
    {
        $html = $this->html($uri);

        $this->assertMatchesRegularExpression('/<a class="saltar-contenido" href="#contenido-principal">Saltar al contenido<\/a>/', $html);
        $this->assertMatchesRegularExpression('/<main\b|role="main"/', $html, 'Falta el landmark principal');
        $this->assertStringContainsString('<h1', $html, 'Falta un <h1>');
    }

    /**
     * Toda `<img>` fuera de `<script>` y de comentarios HTML lleva atributo `alt` (vacío si es decorativa).
     *
     * @dataProvider paginasProvider
     */
    public function test_ninguna_imagen_sin_alt(string $uri): void
    {
        $html = $this->html($uri);
        // Se excluyen los <script> (plantillas de JS) y los comentarios.
        $html = preg_replace(['#<script\b.*?</script>#si', '#<!--.*?-->#s'], '', $html);

        preg_match_all('/<img\b[^>]*>/i', $html, $imagenes);
        foreach ($imagenes[0] as $etiqueta) {
            $this->assertMatchesRegularExpression('/\salt=/i', $etiqueta, "Imagen sin alt en $uri: $etiqueta");
        }
    }

    /**
     * El botón del menú de accesibilidad (solo icono) tiene nombre accesible y estado de expansión.
     */
    public function test_el_boton_del_menu_de_accesibilidad_tiene_nombre_accesible(): void
    {
        foreach (['/', '/vertientes'] as $uri) {
            $html = $this->html($uri);

            $this->assertSame(1, preg_match('/<button\b[^>]*id="btn_menu_accesibilidad"[^>]*>/s', $html, $m), "Falta el botón en $uri");
            $this->assertStringContainsString('aria-label="Menú de accesibilidad"', $m[0]);
            $this->assertStringContainsString('aria-expanded="false"', $m[0]);
            $this->assertStringContainsString('aria-controls="contenido_menu_accesibilidad"', $m[0]);
            $this->assertStringContainsString('id="contenido_menu_accesibilidad"', $html);
        }
    }

    /**
     * Las páginas enlazan el manifiesto y declaran el color del tema y el icono de la pantalla de inicio.
     *
     * @dataProvider paginasProvider
     */
    public function test_las_paginas_enlazan_manifiesto_y_theme_color(string $uri): void
    {
        $html = $this->html($uri);

        $this->assertMatchesRegularExpression('#<link rel="manifest" href="[^"]*/manifest\.webmanifest">#', $html);
        $this->assertMatchesRegularExpression('#<meta name="theme-color" content="\#[0-9a-fA-F]{6}">#', $html);
        $this->assertMatchesRegularExpression('#<link rel="apple-touch-icon" href="[^"]*/icons/icono-192\.png">#', $html);
    }

    /**
     * El manifiesto es JSON válido con los campos requeridos, y cada icono existe con las dimensiones que declara.
     */
    public function test_el_manifiesto_es_valido_y_sus_iconos_existen(): void
    {
        $ruta = public_path('manifest.webmanifest');
        $this->assertFileExists($ruta);

        $manifiesto = json_decode(file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
        foreach (['name', 'short_name', 'lang', 'start_url', 'scope', 'display', 'theme_color', 'background_color', 'icons'] as $campo) {
            $this->assertArrayHasKey($campo, $manifiesto, "Falta «{$campo}» en el manifiesto");
        }
        $this->assertSame('Explora Colombia', $manifiesto['name']);
        $this->assertSame('es', $manifiesto['lang']);
        $this->assertSame('standalone', $manifiesto['display']);

        $tamanos = [];
        foreach ($manifiesto['icons'] as $icono) {
            $archivo = public_path(ltrim($icono['src'], '/'));
            $this->assertFileExists($archivo, 'No existe el icono '.$icono['src']);
            [$ancho, $alto] = getimagesize($archivo);
            $this->assertSame($icono['sizes'], $ancho.'x'.$alto, 'Dimensiones distintas de las declaradas en '.$icono['src']);
            $tamanos[] = $icono['sizes'];
        }
        $this->assertContains('192x192', $tamanos);
        $this->assertContains('512x512', $tamanos);
    }

    /**
     * El CSS propio de accesibilidad (cargado por el layout base) respeta `prefers-reduced-motion` y define el foco visible.
     */
    public function test_el_css_propio_respeta_movimiento_reducido(): void
    {
        $css = file_get_contents(public_path('css/accesibilidad.css'));

        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('.saltar-contenido', $css);
        $this->assertStringContainsString('.sr-only', $css);
        // El layout base lo carga en todas las páginas.
        $this->assertStringContainsString('css/accesibilidad.css', $this->html('/'));
    }
}
