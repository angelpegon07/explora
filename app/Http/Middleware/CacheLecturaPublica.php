<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Caché de lectura pública para las rutas JSON de solo lectura (ficha y preguntas de un departamento; Fase 17B).
 *
 * El contenido de Explora lo escribe un proceso externo (la base es de solo lectura para la aplicación), así que cambia muy
 * pocas veces. Este middleware evita reconstruir la respuesta (hasta 24 consultas y el JSON) en cada visita:
 *
 *  1. **Caché del servidor.** Guarda el cuerpo de las respuestas 200 en el almacén de caché de Laravel, `explora.cache_lectura_ttl`
 *     segundos. Solo se guardan cuerpos de hasta `explora.cache_lectura_max_bytes` (las fichas con imágenes base64 llegan a
 *     48 MB y llenarían el disco; tras `explora:externalizar-imagenes` caben sin problema).
 *  2. **Caché HTTP.** Toda respuesta 200 lleva `ETag` débil y `Cache-Control: public, max-age=…, must-revalidate`; si el navegador
 *     envía `If-None-Match` con el mismo ETag, se responde 304 sin cuerpo (ahorra ancho de banda aunque el cuerpo no se guarde).
 *
 * Qué NO hace: no cachea errores (404, 429…) ni nada que no sea 200, y se desactiva con `EXPLORA_CACHE_TTL=0`. Para invalidar
 * tras cambiar el contenido: `php artisan explora:limpiar-cache-lectura` (cambia la versión de las claves).
 * Debe ir DESPUÉS del límite de peticiones (`throttle`), para que la caché no permita saltárselo.
 */
class CacheLecturaPublica
{
    /** Clave del contador de versión; al incrementarlo, todas las entradas anteriores dejan de usarse. */
    public const CLAVE_VERSION = 'explora:lectura:version';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ttl = (int) config('explora.cache_lectura_ttl', 0);
        if ($ttl <= 0 || ! $request->isMethodCacheable()) {
            return $next($request);
        }

        $clave = 'explora:lectura:'.(int) Cache::get(self::CLAVE_VERSION, 1).':'.sha1($request->path());
        $guardado = Cache::get($clave);

        if (is_array($guardado)) {
            $respuesta = response($guardado['cuerpo'], 200, ['Content-Type' => 'application/json']);
            $respuesta->headers->set('ETag', $guardado['etag']);
            $respuesta->headers->set('X-Cache', 'HIT');
        } else {
            $respuesta = $next($request);
            if ($respuesta->getStatusCode() !== 200) {
                return $respuesta;
            }
            $cuerpo = (string) $respuesta->getContent();
            $etag = 'W/"'.md5($cuerpo).'"';
            $respuesta->headers->set('ETag', $etag);
            if (strlen($cuerpo) <= (int) config('explora.cache_lectura_max_bytes')) {
                Cache::put($clave, ['cuerpo' => $cuerpo, 'etag' => $etag], $ttl);
            }
            $respuesta->headers->set('X-Cache', 'MISS');
        }

        $respuesta->headers->set('Cache-Control', 'public, max-age='.(int) config('explora.cache_lectura_navegador').', must-revalidate');

        // 304: el navegador ya tiene esta versión.
        if (in_array($respuesta->headers->get('ETag'), array_map('trim', explode(',', (string) $request->headers->get('If-None-Match'))), true)) {
            $respuesta->setNotModified();
        }

        return $respuesta;
    }
}
