<?php

use App\Http\Controllers\InicioController;
use App\Http\Controllers\JuegoController;
use App\Http\Controllers\MapaTematicoController;
use App\Http\Controllers\PaginaEstaticaController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PortalJuegosController;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

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
| Rutas SIN sesión ni cookies (verificación posterior a la Fase 15)
|--------------------------------------------------------------------------
|
| Todo este archivo cuelga del grupo `web` (cookies cifradas, sesión, errores de sesión, CSRF). Las páginas que NO pintan
| datos del usuario no necesitan nada de eso, y mantenerlo tenía costes reales: cada GET anónimo creaba un archivo en
| `storage/framework/sessions` (un script podía llenar el disco/los inodos) y emitía `Set-Cookie` (`explora_session` y
| `XSRF-TOKEN`), con lo que ningún proxy/CDN podía cachear la página. Por eso se las excluye del grupo con
| `withoutMiddleware()` (en Laravel 10 funciona también con middleware que vienen de un GRUPO: se comprueba por clase).
|
|  - SIN sesión: la portada `/`, los 5 mapas, los 7 juegos, `/lineaTiempo/`, `/ViajaPED/` y TODAS las redirecciones 301.
|  - CON sesión (no están en estos grupos): `/perfil` y `/juegos` (pintan el estado de sesión del usuario) y toda la API.
|
| El login por JavaScript sigue funcionando en las páginas sin sesión: la cookie CSRF (`XSRF-TOKEN`) y la de sesión las
| entrega `GET /api/sesion` (ruta de la API, que sí tiene sesión), que `js/explora-api.js` pide al cargar cada página.
|
| `$sinSesion` es la MISMA lista que usa el grupo `web` de app/Http/Kernel.php (sin SubstituteBindings, que sí se conserva).
*/
$sinSesion = [
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    VerifyCsrfToken::class,
];

/*
|--------------------------------------------------------------------------
| Listas de config vacías (verificación posterior a la Fase 15)
|--------------------------------------------------------------------------
|
| Cada grupo de rutas basado en una lista de config (`mapas`, `juegos`, `paginas_estaticas`, `servicios_retirados`) se
| registra SOLO si la lista no está vacía: con una lista vacía el patrón `where()` queda vacío y Symfony lanza «Routing
| requirement cannot be empty», lo que rompería TODAS las rutas (incluido `route:list`). Sin elementos, simplemente no hay
| esas URL (404). Lo vigila tests/Feature/RutasListasVaciasTest.
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
Route::withoutMiddleware($sinSesion)->group(static function (): void {
    Route::get('/', InicioController::class)->name('inicio');

    Route::get('/index.html', static function (Request $request) {
        $consulta = $request->getQueryString();

        return redirect()->to('/'.($consulta !== null && $consulta !== '' ? '?'.$consulta : ''), 301);
    });
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

if ($patronMapas !== '') {
    Route::withoutMiddleware($sinSesion)->group(static function () use ($patronMapas): void {
        Route::get('/{slug}', MapaTematicoController::class)
            ->where('slug', $patronMapas)
            ->name('mapa');

        Route::get('/{slug}.html', static function (Request $request, string $slug) {
            // Se conserva la cadena de consulta; el fragmento (#…, usado por el «deeplinking» de Mapplic) lo mantiene el navegador.
            $consulta = $request->getQueryString();

            return redirect()->to('/'.$slug.($consulta !== null && $consulta !== '' ? '?'.$consulta : ''), 301);
        })->where('slug', $patronMapas);
    });
}

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

// Las redirecciones de las URL antiguas NO usan sesión (ver «Rutas SIN sesión» arriba), a diferencia de las páginas a las que apuntan.
Route::withoutMiddleware($sinSesion)->group(static function (): void {
    Route::get('/miPerfil.html', static function (Request $request) {
        $consulta = $request->getQueryString();

        return redirect()->to('/perfil'.($consulta !== null && $consulta !== '' ? '?'.$consulta : ''), 301);
    });

    Route::get('/juegos/index.html', static function (Request $request) {
        $consulta = $request->getQueryString();

        return redirect()->to('/juegos'.($consulta !== null && $consulta !== '' ? '?'.$consulta : ''), 301);
    });
});

/*
|--------------------------------------------------------------------------
| Páginas de los 7 juegos (Fase 13)
|--------------------------------------------------------------------------
|
| URL canónica: `/juegos/juegos/{slug}/` (CON barra final: las rutas relativas de cada juego dependen de ella). Los slugs
| válidos salen de config/juegos.php y `where()` los fija como lista cerrada, así que cualquier otro nombre da el 404
| normal de Laravel. El controlador sirve la vista con barra y redirige (301) la forma sin barra.
|
| Igual que con `/juegos`, esas carpetas son CARPETAS reales (guardan JS, CSS, JSON, sonidos…) y ni Apache ni el router
| de desarrollo las envían a Laravel por defecto: `public/.htaccess` y `servidor-desarrollo.php` tienen una regla
| explícita para esos slugs (y solo para ellos; los archivos estáticos de la carpeta siguen sirviéndose directamente).
|
| Los `index.html` antiguos se eliminaron; sus URL (enlaces guardados) redirigen con 301 a la forma canónica.
*/
$patronJuegos = implode('|', array_map(static fn (string $slug): string => preg_quote($slug, '#'), config('juegos')));

if ($patronJuegos !== '') {
    // Sin sesión: las páginas de juego no pintan datos del usuario (el JS pide la sesión a /api/sesion).
    Route::withoutMiddleware($sinSesion)->group(static function () use ($patronJuegos): void {
        Route::get('/juegos/juegos/{slug}', JuegoController::class)
            ->where('slug', $patronJuegos)
            ->name('juego');

        Route::get('/juegos/juegos/{slug}/index.html', [JuegoController::class, 'redirigirACanonica'])
            ->where('slug', $patronJuegos);
    });
}

/*
|--------------------------------------------------------------------------
| Línea de tiempo y ViajaPED (Fase 15)
|--------------------------------------------------------------------------
|
| Últimas páginas propias que seguían siendo HTML estático: `/lineaTiempo/` y `/ViajaPED/` (respetando mayúsculas). Misma
| mecánica que los juegos (Fase 13): son CARPETAS reales con sus assets y rutas relativas (`fetch('eventos.json')`,
| `data.json`, `img/…`, `url(img/fondo.png)`), por lo que la URL canónica lleva SIEMPRE barra final, la forma sin barra
| redirige (301) y `public/.htaccess` y `servidor-desarrollo.php` envían a Laravel exactamente esa URL (los archivos de la
| carpeta se siguen sirviendo directamente). Los slugs salen de config/paginas_estaticas.php y `where()` los fija como
| lista cerrada: cualquier otro nombre da el 404 normal de Laravel. El `index.html` antiguo se eliminó y redirige (301).
*/
$patronPaginasEstaticas = implode('|', array_map(static fn (string $slug): string => preg_quote($slug, '#'), config('paginas_estaticas')));

// OJO: el parámetro se llama `pagina` y no `slug` A PROPÓSITO: Laravel indexa las rutas por su URI literal (`{slug}`) y una
// segunda `/{slug}` (la de los mapas temáticos, arriba) la PISARÍA en silencio.
if ($patronPaginasEstaticas !== '') {
    Route::withoutMiddleware($sinSesion)->group(static function () use ($patronPaginasEstaticas): void {
        Route::get('/{pagina}', PaginaEstaticaController::class)
            ->where('pagina', $patronPaginasEstaticas)
            ->name('pagina-estatica');

        Route::get('/{pagina}/index.html', [PaginaEstaticaController::class, 'redirigirACanonica'])
            ->where('pagina', $patronPaginasEstaticas);
    });
}

/*
|--------------------------------------------------------------------------
| PHP legado retirado: 410 Gone (Fase 15)
|--------------------------------------------------------------------------
|
| Los 7 scripts PHP que sustituyó la API (`/mapplic/php/{iniciarSesion,cambiarAvatar,consultarInfo,buscarPreguntas}.php` y
| `/juegos/php/{guardar_resultado,consultar_ranking,consultar_historial}.php`) se eliminaron del repositorio. Como ya no
| existen como archivo, Apache enviaría esas URL al 404 de Laravel; esta ruta mantiene para los clientes antiguos el mismo
| `410 Gone` + JSON que daba la regla de los `.htaccess` retirados y que sigue dando el router de desarrollo.
|
|  - La lista sale de config/servicios_retirados.php (una sola fuente de verdad, también la lee el router de desarrollo).
|  - `(?i:…)` hace la comparación insensible a mayúsculas, como el `[NC]` de antes: `/MAPPLIC/PHP/CONSULTARINFO.PHP` también da 410.
|  - `Route::any` cubre todos los métodos HTTP (GET, POST, PUT, PATCH, DELETE, OPTIONS; HEAD sin cuerpo).
|  - Se desactivan los middlewares del grupo `web`: sin sesión ni cookies (nada de `Set-Cookie`) y, sobre todo, sin CSRF, que
|    convertiría un POST antiguo en un 419. Es una respuesta fija, sin datos de la petición, sin cabeceras ni trazas internas.
|  - `mapplic/php/chat.php` y `chat copy.php` NO están en la lista: esta ruta no los toca (Apache sirve el archivo; el
|    router de desarrollo, que los desactiva a propósito, responde 404).
*/
$patronRetirados = implode('|', array_map(static fn (string $ruta): string => preg_quote($ruta, '#'), config('servicios_retirados')));

if ($patronRetirados !== '') {
    Route::any('/{servicio}', static fn () => response()->json(['status' => 'error', 'mensaje' => 'Servicio retirado'], 410, [], JSON_UNESCAPED_UNICODE))
        ->where('servicio', '(?i:'.$patronRetirados.')')
        ->withoutMiddleware($sinSesion)
        ->name('servicio-retirado');
}
