<?php

namespace App\Http\Middleware;

use App\Support\ProblemaApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Convierte los errores JSON de las rutas `/api/v1` al formato RFC 9457 (`application/problem+json`; Fase 17E).
 *
 * Por qué: los controladores y FormRequests del legado devuelven cuerpos de error con formas distintas y el contrato de
 * la v1 es uno solo. Se hace en un middleware (y no tocando cada controlador) para que `/api/*` conserve EXACTAMENTE sus
 * cuerpos actuales. Debe ser el middleware más externo de las rutas v1 (envuelve al grupo `web`), de modo que vea los
 * 419 de CSRF y los 401 de `auth:web`. Lo que ocurre antes de elegir ruta (404/405) y el límite general `throttle:api`
 * lo cubre el manejador de excepciones (App\Exceptions\Handler).
 *
 * No toca respuestas < 400, ni las que no son JSON, ni las que ya son problem+json (es idempotente). Conserva las
 * cabeceras existentes (Retry-After, X-RateLimit-*, Allow...).
 */
class ErroresProblemJson
{
    /**
     * @param  Request  $request  Petición en curso.
     * @param  Closure(Request): Response  $next  Siguiente capa.
     * @return Response La respuesta, reescrita si es un error JSON.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return ProblemaApi::convertir($request, $next($request));
    }
}
