<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La ficha de un departamento puede superar 40 MB porque el contenido lleva imágenes embebidas en base64 (DAT-07).
 * Con el límite por defecto de PHP (128 MB) el legado ya falla con el departamento 10 (FUN-11). Se eleva el
 * límite SOLO para esta ruta mientras no se extraigan las imágenes del contenido.
 */
class MemoriaParaFichas
{
    /**
     * Sube `memory_limit` a 512 MB si el actual es menor (y no es ilimitado) y continúa con la petición.
     *
     * @param  Request  $request  Petición entrante.
     * @param  Closure  $next  Siguiente capa del pipeline.
     * @return Response La respuesta de la capa siguiente (este middleware no la modifica).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->aBytes((string) ini_get('memory_limit')) !== -1 && $this->aBytes((string) ini_get('memory_limit')) < 512 * 1024 * 1024) {
            ini_set('memory_limit', '512M');
        }

        return $next($request);
    }

    /**
     * Convierte un valor de php.ini (`128M`, `1G`, `512K`, `-1`) a bytes.
     *
     * @param  string  $v  Valor tal como lo devuelve ini_get().
     */
    private function aBytes(string $v): int
    {
        $n = (int) $v;

        return match (strtoupper(substr(trim($v), -1))) {
            'G' => $n * 1024 ** 3,
            'M' => $n * 1024 ** 2,
            'K' => $n * 1024,
            default => $n,
        };
    }
}
