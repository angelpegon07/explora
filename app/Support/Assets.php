<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Versionado de assets (Fase 17F, bloque A2): añade `?v=<hash>` a las URL de los CSS y JS propios.
 *
 * POR QUÉ: las vistas cargaban `asset('css/style.css')` con una ruta fija. Tras un despliegue, el navegador (o un
 * proxy) puede seguir sirviendo la copia vieja del archivo. Al añadir un hash del CONTENIDO a la URL, esta cambia
 * solo cuando el archivo cambia, de modo que se puede cachear por un año (`immutable`, ver deploy/apache-vhost.conf.ejemplo)
 * y aun así los cambios llegan al instante. No hay paso de compilación: el hash se calcula al vuelo.
 *
 * COSTE: se memoiza en dos niveles. Dentro de la petición, en una propiedad estática; entre peticiones, con
 * Cache::remember (TTL de 1 día) bajo una clave que incluye ruta, fecha de modificación y tamaño, así que si el
 * archivo cambia la clave cambia y el hash se recalcula sin esperar al TTL.
 *
 * SEGURIDAD: la ruta debe ser un literal de la vista, nunca entrada del usuario. Aun así se resuelve con realpath
 * y se exige que quede dentro de public_path(); una ruta con `..` hacia fuera, un directorio o un archivo
 * inexistente devuelven la URL sin versión. Esta clase nunca lanza excepciones: en el peor caso, degrada a asset().
 */
final class Assets
{
    /** Largo (en caracteres hexadecimales) del hash que se añade a la URL. */
    private const LARGO_HASH = 10;

    /** Segundos de vida del hash en la caché compartida (1 día). */
    private const TTL = 86400;

    /**
     * Memo por petición: ruta pedida => hash (o null si no es versionable). Se memoiza el HASH y no la URL, porque la raíz
     * de `asset()` puede cambiar entre peticiones del mismo proceso (pruebas, ASSET_URL, esquema).
     *
     * @var array<string,string|null>
     */
    private static array $memo = [];

    /**
     * URL pública de un archivo de public/ con `?v=<10 hex del md5 del contenido>`.
     *
     * Si el archivo no existe, no es un archivo, o queda fuera de public/ (path traversal), devuelve `asset($ruta)`
     * sin versión. Nunca lanza excepciones.
     *
     * @param  string  $ruta  Ruta relativa a public/ (p. ej. `css/style.css`); debe ser un literal de la vista.
     * @return string URL absoluta o relativa según `asset()`, con `?v=` cuando el archivo es válido.
     */
    public static function url(string $ruta): string
    {
        $url = asset($ruta);

        if (! array_key_exists($ruta, self::$memo)) {
            try {
                self::$memo[$ruta] = self::hash($ruta);
            } catch (\Throwable) {
                // Degrada a la URL sin versión: un fallo de caché o de disco no debe romper la página.
                self::$memo[$ruta] = null;
            }
        }

        return self::$memo[$ruta] === null ? $url : $url.'?v='.self::$memo[$ruta];
    }

    /**
     * Hash (10 hex del md5 del contenido) del archivo, o null si no es un archivo válido bajo public/.
     *
     * @param  string  $ruta  Ruta relativa a public/.
     * @return string|null El hash, o null si el archivo no existe o está fuera de public/.
     */
    private static function hash(string $ruta): ?string
    {
        $base = realpath(public_path());
        $real = realpath(public_path(ltrim($ruta, '/')));

        if ($base === false || $real === false || ! str_starts_with($real, $base.DIRECTORY_SEPARATOR) || ! is_file($real)) {
            return null;
        }

        $mtime = (int) filemtime($real);
        $tamano = (int) filesize($real);

        return Cache::remember(
            'assets.v.'.sha1($real.'|'.$mtime.'|'.$tamano),
            self::TTL,
            static fn (): string => substr((string) md5_file($real), 0, self::LARGO_HASH),
        );
    }

    /**
     * Vacía el memo por petición (solo para pruebas, que cambian el contenido de un archivo dentro de la misma petición).
     */
    public static function olvidar(): void
    {
        self::$memo = [];
    }
}
