<?php

namespace Tests\Feature;

use App\Support\Assets;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Fase 17F, bloque A2: versionado de assets (`asset_v()` / {@see Assets}).
 *
 * Comprueba que la URL de un CSS/JS propio lleva `?v=` con 10 hex del md5 del contenido, que cambia cuando cambia el
 * archivo, que NO versiona rutas inexistentes ni que salen de `public/` (path traversal) y que las páginas públicas que
 * no dependen de la base de datos incluyen la versión en sus `<link>` y `<script src>` propios.
 * Los archivos temporales se crean bajo `public/` con nombre único y se borran en tearDown.
 */
class AssetsVersionadosTest extends TestCase
{
    /** @var list<string> Archivos temporales creados por la prueba (rutas absolutas), borrados en tearDown. */
    private array $temporales = [];

    protected function tearDown(): void
    {
        foreach ($this->temporales as $ruta) {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
        Assets::olvidar();
        parent::tearDown();
    }

    /**
     * Crea un archivo temporal único bajo public/ y devuelve su ruta relativa.
     *
     * @param  string  $contenido  Contenido del archivo.
     * @return string Ruta relativa a public/.
     */
    private function temporal(string $contenido): string
    {
        $relativa = 'prueba-assets-'.bin2hex(random_bytes(6)).'.js';
        $absoluta = public_path($relativa);
        file_put_contents($absoluta, $contenido);
        $this->temporales[] = $absoluta;

        return $relativa;
    }

    public function test_la_url_lleva_version_de_10_hex_del_contenido(): void
    {
        $ruta = $this->temporal('console.log(1);');

        $esperada = asset($ruta).'?v='.substr(md5('console.log(1);'), 0, 10);

        $this->assertSame($esperada, asset_v($ruta));
        $this->assertMatchesRegularExpression('/\?v=[0-9a-f]{10}$/', asset_v($ruta));
    }

    public function test_la_version_cambia_si_cambia_el_contenido(): void
    {
        $ruta = $this->temporal('var a = 1;');
        $antes = asset_v($ruta);

        // Distinto contenido (y tamaño) => nueva clave de caché y nuevo hash; se vacía el memo de la petición.
        file_put_contents(public_path($ruta), 'var a = 2222;');
        clearstatcache();
        Assets::olvidar();
        $despues = asset_v($ruta);

        $this->assertNotSame($antes, $despues);
        $this->assertStringStartsWith(asset($ruta).'?v=', $despues);
    }

    public function test_un_archivo_inexistente_no_se_versiona(): void
    {
        $ruta = 'no-existe-'.bin2hex(random_bytes(4)).'.js';
        $this->assertSame(asset($ruta), asset_v($ruta));
        $this->assertStringNotContainsString('?v=', asset_v($ruta));
    }

    public function test_una_ruta_que_sale_de_public_no_se_versiona(): void
    {
        // composer.json existe, pero está UN nivel por encima de public/: no debe versionarse ni leerse.
        $ruta = '../composer.json';
        $this->assertFileExists(public_path($ruta));
        $this->assertSame(asset($ruta), asset_v($ruta));
        $this->assertStringNotContainsString('?v=', asset_v($ruta));

        // Un directorio dentro de public/ tampoco es un archivo versionable.
        $this->assertStringNotContainsString('?v=', asset_v('js'));
    }

    /**
     * Páginas sin base de datos: sus recursos propios (`<link>` y `<script src>` locales) llevan `?v=`.
     *
     * @return array<string,array{0:string}>
     */
    public static function paginas(): array
    {
        return [
            'inicio' => ['/'],
            'portal de juegos' => ['/juegos'],
            'vertientes' => ['/vertientes'],
            'línea de tiempo' => ['/lineaTiempo/'],
        ];
    }

    /**
     * @dataProvider paginas
     */
    public function test_las_paginas_versionan_sus_css_y_js_propios(string $url): void
    {
        // Misma mecánica que PaginasEstaticasTest: petición directa al kernel contra http://localhost (la ruta de
        // `/lineaTiempo/` redirige si el host no es el canónico).
        $respuesta = $this->createTestResponse($this->app->make(Kernel::class)->handle(Request::create('http://localhost'.$url, 'GET')));
        $html = $respuesta->assertOk()->getContent();

        preg_match_all('/<(?:script[^>]*\ssrc|link[^>]*\shref)="([^"]+\.(?:js|css)[^"]*)"/', (string) $html, $m);
        $locales = array_filter($m[1], fn (string $u): bool => ! preg_match('#^(https?:)?//#', $u) || str_starts_with($u, url('/')));

        $this->assertNotEmpty($locales, "$url no carga CSS/JS propios");
        foreach ($locales as $u) {
            $this->assertMatchesRegularExpression('/\.(?:js|css)\?v=[0-9a-f]{10}$/', $u, "$url: sin versión en $u");
        }
    }
}
