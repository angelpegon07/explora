<?php

use App\Http\Controllers\InicioController;
use App\Http\Controllers\MapaTematicoController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PortalJuegosController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

/*
|--------------------------------------------------------------------------
| Portada (Fase 11)
|--------------------------------------------------------------------------
|
| `/` es el mapa general (vista `inicio`). El antiguo `public/index.html` se eliminó: su URL, que usan marcadores,
| enlaces externos y la propia aplicación, redirige con 301 a `/` y conserva la cadena de consulta (el fragmento
| `#…` del «deeplinking» de Mapplic lo mantiene el navegador).
*/
Route::get('/', InicioController::class)->name('inicio');

Route::get('/index.html', static function (Request $request) {
    $consulta = $request->getQueryString();

    return redirect()->to('/'.($consulta !== null && $consulta !== '' ? '?'.$consulta : ''), 301);
});

/*
|--------------------------------------------------------------------------
| Mapas temáticos (Fase 10)
|--------------------------------------------------------------------------
|
| Los slugs válidos salen de config/mapas.php (una entrada por mapa): para añadir un mapa basta añadirlo allí.
| `where()` restringe el parámetro a esa lista cerrada, así que `/cualquier-otra-cosa` sigue dando el 404 normal
| de Laravel y el patrón nunca se arma con datos de la petición.
|
| Los HTML estáticos antiguos se eliminaron (un archivo real gana siempre a una ruta); las URL `/{slug}.html`
| —que siguen usando seleccionarTipoMapa() de general.js, compartido con index.html, y los marcadores de los
| usuarios— redirigen con 301 a la URL nueva.
*/
$patronMapas = implode('|', array_map(static fn (string $slug): string => preg_quote($slug, '#'), array_keys(config('mapas'))));

Route::get('/{slug}', MapaTematicoController::class)
    ->where('slug', $patronMapas)
    ->name('mapa');

Route::get('/{slug}.html', static function (Request $request, string $slug) {
    // Se conserva la cadena de consulta; el fragmento (#…, usado por el «deeplinking» de Mapplic) lo mantiene el navegador.
    $consulta = $request->getQueryString();

    return redirect()->to('/'.$slug.($consulta !== null && $consulta !== '' ? '?'.$consulta : ''), 301);
})->where('slug', $patronMapas);

/*
|--------------------------------------------------------------------------
| Perfil y portal de juegos (Fase 12)
|--------------------------------------------------------------------------
|
| Ambas páginas se pintan en el servidor con la sesión de Laravel (grupo `web`, la misma cookie que `/api/sesion`).
|
| OJO con las carpetas: `public/miPerfil/` y `public/juegos/` son CARPETAS reales (guardan script.js, style.css, css/,
| js/…) y ni Apache (`RewriteCond !-d`) ni el router de desarrollo envían carpetas a Laravel. Por eso:
|  - el perfil vive en `/perfil` (no en `/miPerfil`, que chocaría con su carpeta);
|  - `/juegos` y `/juegos/` (y SOLO esas dos) se envían a Laravel con una regla explícita en `public/.htaccess` y su
|    equivalente en `servidor-desarrollo.php`. Laravel casa `/juegos/` con esta ruta sin redirigir.
|
| Los archivos estáticos antiguos se eliminaron (un archivo real gana siempre a una ruta); sus URL —que siguen
| escritas en marcadores y en ~20 enlaces `../../index.html` de las páginas de cada juego— redirigen con 301.
*/
Route::get('/perfil', PerfilController::class)->name('perfil');
Route::get('/juegos', PortalJuegosController::class)->name('juegos');

Route::get('/miPerfil.html', static function (Request $request) {
    $consulta = $request->getQueryString();

    return redirect()->to('/perfil'.($consulta !== null && $consulta !== '' ? '?'.$consulta : ''), 301);
});

Route::get('/juegos/index.html', static function (Request $request) {
    $consulta = $request->getQueryString();

    return redirect()->to('/juegos'.($consulta !== null && $consulta !== '' ? '?'.$consulta : ''), 301);
});
