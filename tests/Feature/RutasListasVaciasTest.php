<?php

namespace Tests\Feature;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Si una lista de config (`mapas`, `juegos`, `paginas_estaticas`, `servicios_retirados`) queda vacía, `routes/web.php` NO
 * debe registrar su grupo de rutas: con un `where()` vacío Symfony lanza «Routing requirement cannot be empty» y rompería
 * TODAS las rutas (verificación E-H8).
 */
class RutasListasVaciasTest extends TestCase
{
    /**
     * Vuelve a cargar `routes/web.php` con las listas indicadas (vacías) sobre una colección de rutas limpia.
     *
     * @param  list<string>  $listas  Claves de config que se vacían.
     */
    private function recargarRutasConListasVacias(array $listas): void
    {
        foreach ($listas as $lista) {
            config([$lista => []]);
        }
        $this->app['router']->setRoutes(new RouteCollection);
        Route::middleware('web')->group(base_path('routes/web.php'));
        $this->app['router']->getRoutes()->refreshNameLookups();
    }

    /**
     * @return array<string, array{0:list<string>, 1:string}> Listas a vaciar y una URL que debe dar 404 al faltar.
     */
    public static function listas(): array
    {
        return [
            'mapas' => [['mapas'], '/climatico'],
            'juegos' => [['juegos'], '/juegos/juegos/puzzle/'],
            'paginas_estaticas' => [['paginas_estaticas'], '/lineaTiempo/'],
            'servicios_retirados' => [['servicios_retirados'], '/mapplic/php/consultarInfo.php'],
        ];
    }

    /**
     * Con una sola lista vacía: el resto del sitio funciona, esas URL dan 404 y nada lanza excepciones al compilar rutas.
     *
     * @param  list<string>  $lista
     *
     * @dataProvider listas
     */
    public function test_una_lista_vacia_no_rompe_las_demas_rutas(array $lista, string $urlAusente): void
    {
        $this->recargarRutasConListasVacias($lista);

        $this->get('/')->assertOk();
        $this->get($urlAusente)->assertNotFound();
        // Una URL inexistente obliga a compilar TODAS las rutas: antes de la guarda esto daba 500.
        $this->get('/ruta-inexistente')->assertNotFound();
    }

    /**
     * Con las cuatro listas vacías a la vez, la portada y las redirecciones fijas siguen respondiendo.
     */
    public function test_con_todas_las_listas_vacias_sigue_la_portada(): void
    {
        $this->recargarRutasConListasVacias(['mapas', 'juegos', 'paginas_estaticas', 'servicios_retirados']);

        $this->get('/')->assertOk();
        $this->get('/index.html')->assertStatus(301);
        $this->get('/perfil')->assertOk();
        $this->get('/ruta-inexistente')->assertNotFound();
        // `/` no debe ser capturada por una ruta de servicios retirados con patrón vacío.
        $this->post('/')->assertStatus(405);
    }
}
