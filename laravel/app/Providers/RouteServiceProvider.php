<?php

namespace App\Providers;

use App\Support\NormalizaLogin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * Registra los limitadores de peticiones (`login` y `api`) y carga routes/api.php (prefijo `/api`) y routes/web.php.
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        /*
         * Límite del inicio de sesión (SEC-04): 5 intentos por minuto por pareja usuario+IP —frena adivinar la
         * clave de una cuenta— y 30 por minuto por IP —frena probar muchas cuentas desde un mismo origen—.
         * Al excederlo se responde 429 con el mismo formato JSON del resto de la sesión.
         */
        RateLimiter::for('login', function (Request $request) {
            $demasiados = fn (Request $r, array $cabeceras) => response()->json([
                'success' => false,
                'mensaje' => 'Demasiados intentos. Espere un momento e inténtelo de nuevo.',
            ], 429, $cabeceras, JSON_UNESCAPED_UNICODE);

            // La clave se arma con datos del cliente. Se normaliza (minúsculas y sin acentos) porque la base trata
            // «juan», «JUAN» y «júan» como la misma cuenta; así no se puede repartir los intentos entre variantes.
            // Si `usuario` no es texto (p. ej. un arreglo) se usa una cadena vacía, para no provocar un error 500 al
            // convertirlo (el formulario lo rechazará con 422 más adelante).
            $login = $request->input('usuario');
            $login = is_string($login) ? NormalizaLogin::clave($login) : '';

            return [
                Limit::perMinute(5)->by($login.'|'.$request->ip())->response($demasiados),
                Limit::perMinute(30)->by($request->ip())->response($demasiados),
            ];
        });

        /*
         * Límite de partidas guardadas (FUN-09, modo invitado): `explora.resultados_por_minuto` (10) por minuto, por
         * usuario si hay sesión y por IP si es invitado. Frena el relleno masivo del ranking.
         */
        RateLimiter::for('resultados', function (Request $request) {
            $usuario = $request->user('web');

            return Limit::perMinute(config('explora.resultados_por_minuto'))
                ->by($usuario ? 'u'.$usuario->getAuthIdentifier() : 'ip'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
