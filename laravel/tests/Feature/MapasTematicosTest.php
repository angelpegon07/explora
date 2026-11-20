<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pruebas de las cinco páginas de mapas temáticos (Fase 10): una vista Blade parametrizada por `config/mapas.php`.
 *
 * Comprueban lo que cambia entre mapas (título, JSON, carpeta de imágenes, tutorial, rasgos propios), la
 * redirección 301 de las URL `.html` antiguas, el 404 de slugs desconocidos y que los datos de la configuración se
 * escapan al imprimirse. La equivalencia con el HTML original la verifica `tests/Equivalencia/comparar-paginas.php`.
 */
class MapasTematicosTest extends TestCase
{
    /**
     * @return array<string, array{0:string,1:string,2:string,3:string,4:string}> slug, título, JSON, carpeta de imágenes, tutorial.
     */
    public static function mapas(): array
    {
        return [
            'climatico' => ['climatico', 'Mapa Climático de Colombia', 'climatico.json', 'multimedia/imagenes', 'climatico'],
            'coordilleras' => ['coordilleras', 'Mapa de Cordilleras de Colombia', 'coordilleras.json', 'multimedia/imagenes', 'sistema montañoso'],
            'etnias' => ['etnias', 'Etnias de Colombia', 'etnias.json', 'maps_parques/imagenes_parques/{carpeta}', 'etnias'],
            'parques_naturales' => ['parques_naturales', 'Parques Naturales de Colombia', 'parques_naturales.json', 'maps_parques/imagenes_parques/{carpeta}', 'parques_naturales'],
            'vertientes' => ['vertientes', 'Hidrografía de Colombia', 'vertientes.json', 'multimedia/imagenes', 'hidrografia'],
        ];
    }

    /**
     * Extrae y decodifica el objeto `window.EXPLORA_MAPA` impreso en la página.
     *
     * @return array<string,mixed>
     */
    private function configuracionJs(string $html): array
    {
        $this->assertSame(1, preg_match('/window\.EXPLORA_MAPA = (\{.*?\});/s', $html, $m), 'Falta window.EXPLORA_MAPA');

        return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Cada ruta responde 200 con su título, el objeto EXPLORA_MAPA correcto y el tutorial de su mapa.
     *
     * @dataProvider mapas
     */
    public function test_cada_mapa_se_sirve_con_sus_datos(string $slug, string $titulo, string $json, string $carpeta, string $tutorial): void
    {
        $respuesta = $this->get('/'.$slug);
        $respuesta->assertOk();
        $respuesta->assertSee('<title>'.$titulo.'</title>', false);

        $html = $respuesta->getContent();
        $cfg = $this->configuracionJs($html);
        $this->assertSame($slug, $cfg['slug']);
        $this->assertSame(url($json), $cfg['json']);
        $this->assertSame(url($carpeta), $cfg['carpetaImagenes']);

        // El tutorial se imprime como literal JS seguro dentro del onclick del botón de ayuda.
        $this->assertSame(1, preg_match('/onclick="iniciarTour\(\'(.*?)\'\)" id="btn_tutorial"/u', $html, $m));
        $this->assertSame($tutorial, json_decode('"'.$m[1].'"'));

        // Toda página lleva los mismos bloques compartidos, incluido #final (coordilleras antes no lo tenía).
        foreach (['id="mapplic"', 'id="opciones_mapa"', 'id="chat"', 'id="myModal"', 'id="modalOpcionesAtlas"', 'id="final"', 'id="menuAccesibilidad"'] as $trozo) {
            $this->assertStringContainsString($trozo, $html, "$slug no contiene $trozo");
        }
        $this->assertStringContainsString(asset('js/mapa-tematico.js'), $html);
    }

    /**
     * Todas las URL de recursos son absolutas (asset()): nada relativo ni con `../`.
     *
     * @dataProvider mapas
     */
    public function test_las_urls_de_recursos_son_absolutas(string $slug): void
    {
        $html = $this->get('/'.$slug)->getContent();
        preg_match_all('/\s(?:src|href)="([^"]*)"/', $html, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $url) {
            if ($url === '' || $url[0] === '#' || str_starts_with($url, 'javascript:')) {
                continue;
            }
            $this->assertMatchesRegularExpression('#^https?://#', $url, "URL no absoluta en $slug: $url");
        }
        $this->assertStringNotContainsString('../', $html);
    }

    /**
     * La URL antigua `/x.html` redirige con 301 a `/x` (conserva la consulta).
     *
     * @dataProvider mapas
     */
    public function test_la_url_html_antigua_redirige_con_301(string $slug): void
    {
        $this->get('/'.$slug.'.html')->assertStatus(301)->assertRedirect(url('/'.$slug));
        $this->get('/'.$slug.'.html?x=1')->assertStatus(301)->assertRedirect(url('/'.$slug.'?x=1'));
    }

    /**
     * Un slug desconocido (o con otra forma) da 404, también con `.html`.
     */
    public function test_un_slug_desconocido_da_404(): void
    {
        foreach (['/desconocido', '/volcanes', '/desconocido.html', '/climatico2', '/climatico/otra', '/Climatico'] as $ruta) {
            $this->get($ruta)->assertNotFound();
        }
    }

    /**
     * Solo climatico lleva el panel #dato_interes, su CSS y los onclick que lo abren.
     *
     * @dataProvider mapas
     */
    public function test_solo_climatico_lleva_el_dato_de_interes(string $slug): void
    {
        $html = $this->get('/'.$slug)->getContent();
        $esClimatico = $slug === 'climatico';

        foreach (['id="dato_interes"', 'id="pensando_gif"', 'mostrarCerrarDatoInteres();', '.dato_interes {'] as $trozo) {
            $esClimatico
                ? $this->assertStringContainsString($trozo, $html, "climatico debe contener $trozo")
                : $this->assertStringNotContainsString($trozo, $html, "$slug no debe contener $trozo");
        }
        $this->assertSame($esClimatico, $this->configuracionJs($html)['datoInteres']);
    }

    /**
     * Solo vertientes lleva la variante de Mapplic y el ocultado de niveles; el resto no.
     *
     * @dataProvider mapas
     */
    public function test_solo_vertientes_lleva_la_variante(string $slug): void
    {
        $cfg = $this->configuracionJs($this->get('/'.$slug)->getContent());
        $this->assertSame($slug === 'vertientes' ? 'vertientes' : null, $cfg['variante']);
        $this->assertSame($slug === 'vertientes', $cfg['ocultarNiveles']);
    }

    /**
     * Ninguna página arrastra el texto suelto «iv>» de vertientes.html (cierre de volcanes mal escrito).
     *
     * @dataProvider mapas
     */
    public function test_no_hay_texto_suelto_iv(string $slug): void
    {
        $html = $this->get('/'.$slug)->getContent();
        $this->assertDoesNotMatchRegularExpression('/(^|[^<\/dnl])iv>/', $html);
        $this->assertStringNotContainsString('-->iv>', $html);
        $this->assertStringNotContainsString('volcanes', str_replace("seleccionarTipoMapa('volcanes')", '', $html), 'El bloque de volcanes debe quedar fuera del HTML');
        $this->assertStringNotContainsString("seleccionarTipoMapa('volcanes')", $html);
    }

    /**
     * Los datos de la configuración se escapan: un título con `<` o comillas no puede inyectar marcado, y los datos de
     * `EXPLORA_MAPA` no pueden cerrar el <script> ni salirse de la cadena JS.
     */
    public function test_el_escapado_de_la_configuracion_funciona(): void
    {
        $malicioso = '</title><script>alert(1)</script>"\'&';
        config([
            'mapas.climatico.titulo' => $malicioso,
            'mapas.climatico.json' => 'a"</script><img src=x onerror=alert(1)>.json',
            'mapas.climatico.tutorial' => "x');alert(1);//\"<b>",
        ]);

        $html = $this->get('/climatico')->getContent();

        // Título: aparece escapado y no como etiquetas reales.
        $this->assertStringContainsString('<title>&lt;/title&gt;&lt;script&gt;alert(1)&lt;/script&gt;&quot;&#039;&amp;</title>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);

        // El JSON de EXPLORA_MAPA no contiene «<» ni «>» sin escapar (HEX_TAG): no puede cerrar el <script>.
        preg_match('/window\.EXPLORA_MAPA = (\{.*?\});\s*<\/script>/s', $html, $m);
        $this->assertNotEmpty($m, 'No se pudo aislar window.EXPLORA_MAPA');
        $this->assertStringNotContainsString('<', $m[1]);
        $this->assertStringNotContainsString('>', $m[1]);
        // El valor sobrevive íntegro al ida y vuelta (escapado, no alterado).
        $this->assertSame(asset(config('mapas.climatico.json')), json_decode($m[1], true)['json']);

        // El tutorial, dentro del atributo onclick, se emite como literal JS: sin comillas, «<» ni «>» crudos hasta el cierre.
        $this->assertSame(1, preg_match('/onclick="iniciarTour\(\'([^"\'<>]*)\'\)" id="btn_tutorial"/', $html, $t), 'El tutorial no quedó contenido en su cadena');
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringNotContainsString("');alert(1)", $html);
    }

    /**
     * Las claves de config/mapas.php son exactamente las rutas disponibles (alta de un mapa = una entrada).
     */
    public function test_la_configuracion_define_los_cinco_mapas(): void
    {
        $this->assertSame(
            ['climatico', 'coordilleras', 'etnias', 'parques_naturales', 'vertientes'],
            array_keys(config('mapas'))
        );
    }
}
