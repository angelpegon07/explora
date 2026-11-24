<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pruebas de la portada (`GET /`, vista `inicio`; Fase 11): el mapa general de departamentos con su ficha y el test.
 *
 * Comprueban que la ruta responde con el contenido esperado, que los ids de los que dependen verMas.js, quiz.js y el
 * resto del JS siguen en la página, que se usa la Mapplic de la portada (no la genérica de los mapas) y que la URL
 * antigua `/index.html` redirige con 301. La equivalencia con el HTML original la verifica
 * `tests/Equivalencia/comparar-paginas.php`; el comportamiento, las pruebas de navegador (Playwright).
 */
class InicioTest extends TestCase
{
    /**
     * `/` responde 200 con el título del sitio y todos los bloques críticos de la página.
     */
    public function test_la_portada_se_sirve_con_sus_bloques(): void
    {
        $respuesta = $this->get('/');
        $respuesta->assertOk();
        $respuesta->assertSee('<title>Explora Colombia y aprende</title>', false);

        $html = $respuesta->getContent();
        // Ids de los que dependen mapplic.js, verMas.js (ficha), quiz.js (test), chat.js y el menú.
        foreach ([
            'id="mapplic"', 'id="opciones_mapa"', 'id="departamento_seleccionado"', 'id="modal_sitios"',
            'id="contenedorTest"', 'id="boton_comenzar"', 'id="quiz"', 'id="loadbar"', 'id="circularMenu"', 'id="chat"',
            'id="myModal"', 'id="modalOpcionesAtlas"', 'id="contenedorVideo"', 'id="contenedorImagenes"',
            'id="menuAccesibilidad"', 'id="final"', 'class="containerImagenContenido"', 'id="imagenModalContenido"',
        ] as $trozo) {
            $this->assertStringContainsString($trozo, $html, "La portada no contiene $trozo");
        }
        // Una sola vez cada id crítico (los de la ficha tienen duplicados heredados, no estos).
        foreach (['id="departamento_seleccionado"', 'id="contenedorTest"', 'id="boton_comenzar"', 'id="quiz"'] as $trozo) {
            $this->assertSame(1, substr_count($html, $trozo), "$trozo debe aparecer una vez");
        }
        // Variantes propias de la portada: chat completo y relleno de 20 px en el modal de pantalla completa.
        $this->assertStringContainsString('onclick="minimizarChat()"', $html);
        $this->assertStringContainsString('style=" padding: 20px;"', $html);
        // El aviso «solo para el mapa general» es de los mapas temáticos, no de la portada.
        $this->assertStringNotContainsString('solo está disponible para el mapa general', $html);
    }

    /**
     * La portada carga `mapplic.js` (no `mapplic_generico.js`) y su JS, en el orden original, con la configuración
     * `window.EXPLORA_MAPA` impresa como JSON.
     */
    public function test_la_portada_carga_los_scripts_en_orden(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString(asset('mapplic/mapplic.js').'?v=', $html);
        $this->assertStringNotContainsString('mapplic_generico.js', $html);
        $this->assertStringNotContainsString('mapa-tematico.js', $html);

        $orden = ['vendor/jquery/jquery-3.7.1.min.js', 'js/explora-api.js', 'js/hammer.min.js', 'js/jquery.easing.js', 'js/jquery.mousewheel.js',
            'mapplic/mapplic.js', 'bootstrap/bootstrap.min.js', 'mapplic/verMas.js', 'mapplic/js/quiz.js', 'mapplic/js/general.js',
            'mapplic/js/chat.js', 'js/mapa-comun.js', 'js/inicio.js', 'driver/tutorial.js', 'mapplic/js/iniciarSesion.js'];
        $posicion = -1;
        foreach ($orden as $script) {
            $actual = strpos($html, 'src="'.asset($script).'?v=');
            $this->assertNotFalse($actual, "Falta el script $script");
            $this->assertGreaterThan($posicion, $actual, "$script está fuera de orden");
            $posicion = $actual;
        }

        // Configuración para el JS, sin «<» ni «>» crudos (JSON_HEX_TAG).
        $this->assertSame(1, preg_match('/window\.EXPLORA_MAPA = (\{.*?\});/s', $html, $m));
        $this->assertStringNotContainsString('<', $m[1]);
        $cfg = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(asset('departamentos.json'), $cfg['json']);
        $this->assertSame(asset('multimedia/imagenes'), $cfg['carpetaImagenes']);
        $this->assertTrue($cfg['ocultarNiveles']);
    }

    /**
     * El orden de las hojas de estilo es el original de la portada: general.css, icono y utilidad.css (al revés que en los
     * mapas temáticos). La cascada depende de él.
     */
    public function test_el_orden_de_las_hojas_es_el_de_la_portada(): void
    {
        $html = $this->get('/')->getContent();
        $general = strpos($html, asset('css/general.css'));
        $utilidad = strpos($html, asset('css/utilidad.css'));
        $this->assertNotFalse($general);
        $this->assertLessThan($utilidad, $general);

        $mapa = $this->get('/climatico')->getContent();
        $this->assertLessThan(strpos($mapa, asset('css/general.css')), strpos($mapa, asset('css/utilidad.css')));
    }

    /**
     * El tutorial de la portada es `inicio`; no arrastra el texto suelto «iv>» ni usa rutas relativas.
     */
    public function test_el_tutorial_y_las_urls_de_la_portada(): void
    {
        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('onclick="iniciarTour(\'inicio\')"', $html);
        $this->assertDoesNotMatchRegularExpression('/(^|[^<\/dnl])iv>/', $html);
        $this->assertStringNotContainsString('../', $html);

        preg_match_all('/\s(?:src|href)="([^"]*)"/', $html, $m);
        foreach ($m[1] as $url) {
            if ($url === '' || $url[0] === '#' || str_starts_with($url, 'javascript:')) {
                continue;
            }
            $this->assertMatchesRegularExpression('#^https?://#', $url, "URL no absoluta en la portada: $url");
        }
        // El bloque condicional de Internet Explorer (código muerto) ya no se envía.
        $this->assertStringNotContainsString('html5shiv', $html);
        $this->assertStringNotContainsString('mapplic-ie.css', $html);
    }

    /**
     * Los <div> de la página están equilibrados (sin `</div>` sobrantes como los del HTML original).
     */
    public function test_los_div_de_la_portada_estan_equilibrados(): void
    {
        $html = preg_replace(['/<!--.*?-->/s', '#<script\b.*?</script>#is'], '', $this->get('/')->getContent());
        $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'));
    }

    /**
     * `/index.html` (marcadores antiguos) redirige con 301 a `/` y conserva la consulta.
     */
    public function test_index_html_redirige_con_301_a_la_raiz(): void
    {
        $this->get('/index.html')->assertStatus(301)->assertRedirect(url('/'));
        $this->get('/index.html?x=1')->assertStatus(301)->assertRedirect(url('/?x=1'));
    }

    /**
     * Las URL del menú de tipo de mapa y de la portada no apuntan ya a `*.html` de los mapas migrados.
     */
    public function test_general_js_usa_urls_sin_html(): void
    {
        $js = file_get_contents(public_path('mapplic/js/general.js'));
        foreach (['/', '/vertientes', '/coordilleras', '/climatico', '/parques_naturales', '/etnias'] as $ruta) {
            $this->assertStringContainsString("window.location.href = '$ruta';", $js);
        }
        // Ninguna asignación apunta ya a un `*.html` (el comentario de documentación sí lo menciona).
        $this->assertDoesNotMatchRegularExpression('/location\.href\s*=\s*[^;]*\.html/', $js);
    }
}
