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
 * NO añade `Strict-Transport-Security` a propósito: HSTS solo debe enviarse sobre HTTPS y es difícil de deshacer; se
 * activa en Apache cuando el sitio ya esté en HTTPS.
 *
 * CSP EN MODO INFORME (Fase 17F-C): además se envía `Content-Security-Policy-Report-Only` con la política de {@see self::CSP}
 * (se quita con `CSP_REPORT_ONLY=false`; ver config/explora.php). Es una FASE PREVIA a una CSP estricta: el navegador solo
 * anota en la consola lo que bloquearía, no bloquea nada, y NO se envía la cabecera enforzante `Content-Security-Policy`.
 * Para poder enforzarla de verdad falta:
 *  - quitar los ~80 atributos `on*=` (onclick...) de las vistas y de las cadenas HTML de JS (pasarlos a `addEventListener`);
 *  - sacar los bloques `<script>` y `<style>` en línea (inicio, mapas/tematico, mapas/estilos, juegos/portal) a archivos;
 *  - con eso se podrá quitar `'unsafe-inline'` de `script-src` y `style-src` (hoy imprescindible); y revisar en la consola
 *    del navegador los avisos que genere el modo informe (p. ej. orígenes externos de imágenes o vídeos).
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
     * Política de la cabecera `Content-Security-Policy-Report-Only` (solo informa; no bloquea). Permite recursos del propio
     * origen y, de momento, scripts y estilos en línea (ver el docblock de la clase).
     */
    public const CSP = "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self' data:; connect-src 'self'; media-src 'self'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self'";

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

        // CSP en modo informe: nunca la cabecera enforzante. Tampoco se pisa un valor ya puesto.
        if (config('explora.csp_report_only', true) && ! $respuesta->headers->has('Content-Security-Policy-Report-Only')) {
            $respuesta->headers->set('Content-Security-Policy-Report-Only', self::CSP);
        }

        return $respuesta;
    }
}
