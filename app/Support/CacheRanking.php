<?php

namespace App\Support;

use App\Http\Middleware\CacheLecturaPublica;
use Illuminate\Support\Facades\Cache;

/**
 * Claves y versión de la caché del servidor del ranking (Fase 17E).
 *
 * El ranking se consulta justo después de cada partida (terminar_juego.js hace POST /api/resultados y enseguida
 * GET /api/ranking), así que una caché con solo TTL mostraría un ranking sin la partida recién guardada. Se usa el mismo
 * patrón de versión que {@see CacheLecturaPublica::CLAVE_VERSION}: las claves incluyen un contador y
 * guardar un resultado lo incrementa ({@see self::olvidar()}), de modo que todas las entradas anteriores quedan huérfanas
 * (caducan solas por su TTL) sin tener que enumerarlas ni borrarlas una a una.
 *
 * Esta clase solo centraliza la lógica para que el controlador de lectura y el de escritura no la dupliquen.
 */
class CacheRanking
{
    /** Clave del contador de versión; al incrementarlo, todas las entradas anteriores dejan de usarse. */
    public const CLAVE_VERSION = 'explora:ranking:version';

    /**
     * Construye la clave de una combinación de juego, nivel y categoría.
     *
     * Debe recibir SOLO valores ya validados (nunca la query string cruda). La categoría es texto libre (hasta 100
     * caracteres), por eso va dentro de un sha1: la clave queda de longitud fija y sin caracteres raros para el almacén.
     *
     * @param  string  $juego  Juego validado.
     * @param  string  $nivel  Nivel validado.
     * @param  string  $categoria  Categoría validada.
     * @return string Clave con la versión vigente.
     */
    public static function clave(string $juego, string $nivel, string $categoria): string
    {
        return 'explora:ranking:'.self::version().':'.sha1(json_encode([$juego, $nivel, $categoria], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Versión vigente del contador (1 si aún no existe).
     *
     * @return int Número de versión.
     */
    public static function version(): int
    {
        return (int) Cache::get(self::CLAVE_VERSION, 1);
    }

    /**
     * Invalida TODO el ranking cacheado subiendo la versión. Se llama tras guardar una partida con éxito.
     *
     * Se usa `forever` y no `increment` porque `increment` no funciona sobre una clave inexistente en todos los
     * controladores de caché; la carrera entre dos escrituras simultáneas solo puede repetir un número de versión, y
     * ambas lo dejan distinto del anterior, que es lo único que importa.
     */
    public static function olvidar(): void
    {
        Cache::forever(self::CLAVE_VERSION, self::version() + 1);
    }
}
