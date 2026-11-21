<?php

namespace Tests\Feature;

use App\Http\Middleware\CabecerasSeguridad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Cabeceras de seguridad (`App\Http\Middleware\CabecerasSeguridad`, global): llegan a páginas, API, redirecciones y errores,
 * no pisan un valor ya puesto, y `public/.htaccess` repite los mismos valores para los archivos estáticos que sirve Apache.
 */
class CabecerasSeguridadTest extends TestCase
{
    /**
     * Comprueba que la respuesta lleva las tres cabeceras con su valor.
     *
     * @param  TestResponse  $r  Respuesta a comprobar.
     * @param  string  $donde  Descripción para el mensaje de fallo.
     */
    private function afirmarCabeceras($r, string $donde): void
    {
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'), "$donde: X-Content-Type-Options");
        $this->assertSame('SAMEORIGIN', $r->headers->get('X-Frame-Options'), "$donde: X-Frame-Options");
        $this->assertSame('strict-origin-when-cross-origin', $r->headers->get('Referrer-Policy'), "$donde: Referrer-Policy");
    }

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        parent::tearDown();
    }

    public function test_una_pagina_lleva_las_cabeceras(): void
    {
        $this->afirmarCabeceras($this->get('/climatico'), 'página');
        $this->afirmarCabeceras($this->get('/perfil'), 'perfil');
    }

    public function test_la_api_lleva_las_cabeceras(): void
    {
        $this->afirmarCabeceras($this->getJson('/api/sesion'), '/api/sesion (401)');
        $this->afirmarCabeceras($this->getJson('/api/ranking'), '/api/ranking (422)');
    }

    public function test_un_redirect_lleva_las_cabeceras(): void
    {
        $r = $this->get('/climatico.html')->assertStatus(301);
        $this->afirmarCabeceras($r, 'redirección');
    }

    public function test_un_404_y_un_400_y_un_410_llevan_las_cabeceras(): void
    {
        $this->afirmarCabeceras($this->get('/no-existe-esta-ruta')->assertNotFound(), '404');
        $this->afirmarCabeceras($this->get('http://evil.example/')->assertStatus(400), '400 por host ajeno');
        $this->afirmarCabeceras($this->get('/mapplic/php/consultarInfo.php')->assertStatus(410), '410');
    }

    /**
     * Solo estas tres: no se añaden HSTS ni CSP (decisión documentada: se activan en Apache con HTTPS / primero en Report-Only).
     */
    public function test_no_se_anaden_hsts_ni_csp(): void
    {
        $r = $this->get('/');
        $this->assertFalse($r->headers->has('Strict-Transport-Security'));
        $this->assertFalse($r->headers->has('Content-Security-Policy'));
    }

    /**
     * Un valor puesto antes por el controlador NO se sobrescribe.
     */
    public function test_no_pisa_valores_ya_puestos(): void
    {
        Route::get('/_prueba-cabeceras', static fn () => response('x', 200, ['X-Frame-Options' => 'DENY']));

        $r = $this->get('/_prueba-cabeceras');
        $this->assertSame('DENY', $r->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
    }

    /**
     * `public/.htaccess` (mod_headers) repite EXACTAMENTE los valores del middleware y quita X-Powered-By, para que lleguen
     * también los estáticos que sirve Apache sin pasar por PHP.
     */
    public function test_el_htaccess_repite_las_cabeceras_del_middleware(): void
    {
        $htaccess = file_get_contents(public_path('.htaccess'));

        $this->assertSame(1, preg_match('#<IfModule mod_headers\.c>(.*?)</IfModule>#s', $htaccess, $m), 'Falta el bloque mod_headers');
        foreach (CabecerasSeguridad::CABECERAS as $nombre => $valor) {
            $this->assertStringContainsString("Header always set $nombre \"$valor\"", $m[1]);
        }
        $this->assertStringContainsString('Header unset X-Powered-By', $m[1]);
    }
}
