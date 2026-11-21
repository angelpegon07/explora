<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Añade cabeceras de seguridad básicas a TODA respuesta generada por Laravel (middleware global): páginas, API,
 * redirecciones y errores (404, 400, 410, 419...).
 *
 * Cabeceras (valores deliberadamente conservadores, que no rompen ninguna página del sitio):
 *  - `X-Content-Type-Options: nosniff`: el navegador respeta el Content-Type (no «adivina» que un JSON o un texto es un script).
 *  - `X-Frame-Options: SAMEORIGIN`: solo el propio sitio puede incrustar sus páginas en un `<iframe>` (anti-clickjacking).
 *    Se comprobó que ninguna página incrusta orígenes ajenos que dependan de esto ni es incrustada por otro origen.
 *  - `Referrer-Policy: strict-origin-when-cross-origin`: no se filtran rutas ni consultas a otros sitios.
 *
 * NO pisa un valor que ya venga puesto (un controlador puede necesitar otro más estricto o más laxo).
 *
 * NO añade `Strict-Transport-Security` ni `Content-Security-Policy` a propósito:
 *  - HSTS solo debe enviarse sobre HTTPS y es difícil de deshacer; se activa en Apache cuando el sitio ya esté en HTTPS.
 *  - La CSP exige inventariar scripts y estilos en línea (el sitio usa `onclick=` y bloques `<script>`): se recomienda
 *    desplegarla en Apache primero como `Content-Security-Policy-Report-Only`. Ver DESPLIEGUE_APACHE.md.
 *
 * Los archivos estáticos que sirve Apache sin pasar por PHP reciben las mismas cabeceras desde `public/.htaccess`
 * (bloque `mod_headers`); los dos juegos de valores deben mantenerse iguales (lo vigila CabecerasSeguridadTest).
 *
 * Pruebas: tests/Feature/CabecerasSeguridadTest.php
 */
class CabecerasSeguridad
{
    /**
     * Cabeceras que se añaden y su valor. Misma lista que `public/.htaccess`.
     *
     * @var array<string, string>
     */
    public const CABECERAS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    /**
     * Deja pasar la petición y completa las cabeceras de la respuesta.
     *
     * @param  Request  $request  Petición entrante.
     * @param  Closure(Request): Response  $next  Siguiente middleware.
     * @return Response La misma respuesta con las cabeceras añadidas.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        foreach (self::CABECERAS as $nombre => $valor) {
            // Sin pisar lo que ya haya puesto el controlador.
            if (! $respuesta->headers->has($nombre)) {
                $respuesta->headers->set($nombre, $valor);
            }
        }

        return $respuesta;
    }
}
