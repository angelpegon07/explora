<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marca como obsoletas las rutas `/api/...` sin versión (Fase 17E).
 *
 * Añade a la respuesta `Deprecation: true` y `Link: </api/v1/...>; rel="successor-version"` (RFC 8594/9745) apuntando a
 * la misma ruta de la v1, para que los clientes sepan a dónde migrar. No cambia el estado, el cuerpo ni otras cabeceras:
 * el comportamiento legado queda intacto.
 */
class AvisoDeprecacionApi
{
    /**
     * @param  Request  $request  Petición en curso.
     * @param  Closure(Request): Response  $next  Siguiente capa.
     * @return Response La misma respuesta con las cabeceras de deprecación.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        // `api/ranking` -> `/api/v1/ranking` (solo la ruta; sin query).
        $sucesora = '/api/v1/'.ltrim(substr($request->path(), strlen('api')), '/');
        $respuesta->headers->set('Deprecation', 'true');
        $respuesta->headers->set('Link', '<'.$sucesora.'>; rel="successor-version"');

        return $respuesta;
    }
}
