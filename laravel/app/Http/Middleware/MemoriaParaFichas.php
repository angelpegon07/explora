<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La ficha de un departamento puede superar 40 MB porque el contenido lleva imágenes embebidas en base64 (DAT-07).
 * Con el límite por defecto de PHP (128 MB) el legado ya falla con el departamento 10 (FUN-11). Se eleva el
 * límite SOLO para esta ruta mientras no se extraigan las imágenes del contenido.
 *
 * Fase 16A: `php artisan explora:externalizar-imagenes` saca esas imágenes a archivos. Medido en el sandbox tras aplicarlo,
 * la ficha más pesada (depto 14) pasa de 27,9 MB a 0,5 MB y el pico de memoria de PHP de 208 MB a 22 MB (con el límite
 * por defecto de 128 MB sobra). El valor es configurable (`FICHAS_MEMORIA`, por defecto 512M) y NO se baja en el código
 * porque el código y los datos se despliegan por separado: bajarlo antes de aplicar el comando en producción rompería la
 * ficha del depto 10 (FUN-11). Tras aplicarlo y verificarlo, basta poner `FICHAS_MEMORIA=128M` en el .env.
 */
class MemoriaParaFichas
{
    /**
     * Sube `memory_limit` al valor de `explora.memoria_fichas` (512M por defecto) si el actual es menor (y no es ilimitado) y
     * continúa con la petición.
     *
     * @param  Request  $request  Petición entrante.
     * @param  Closure  $next  Siguiente capa del pipeline.
     * @return Response La respuesta de la capa siguiente (este middleware no la modifica).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $objetivo = (string) config('explora.memoria_fichas', '512M');
        $actual = $this->aBytes((string) ini_get('memory_limit'));
        if ($actual !== -1 && $actual < $this->aBytes($objetivo)) {
            ini_set('memory_limit', $objetivo);
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
