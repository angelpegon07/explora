<?php

namespace App\Support;

/**
 * Funciones PURAS (sin base de datos ni disco) para localizar y analizar imágenes embebidas en el HTML de las fichas
 * (`<img src="data:image/...;base64,...">`) y las URL que las sustituyen. Las usa el comando
 * `explora:externalizar-imagenes` (DAT-07 / REN-03 / FUN-11); están aparte para poder probarlas sin tocar nada.
 *
 * Decisiones de diseño (el porqué):
 *  - Se busca con `stripos`/`strpos` y se mira SOLO una ventana corta antes de cada coincidencia, en lugar de una
 *    expresión regular sobre todo el texto: las filas llegan a ~19 MB y una regex con cuantificadores perezosos sobre
 *    ellas es lenta y puede agotar `pcre.backtrack_limit`.
 *  - Solo se reconoce el valor del atributo `src` de una etiqueta `<img>` entre comillas. Cualquier otra aparición de
 *    `data:image/` (en prosa, en un `style`, en otra etiqueta) se reporta y se deja intacta.
 *  - Los métodos devuelven DESPLAZAMIENTOS dentro del texto, no copias del base64, para no duplicar en memoria filas
 *    enormes; quien llama extrae solo lo que necesita.
 */
final class ImagenesEmbebidas
{
    /** Tipos de imagen que se externalizan: extensión => MIME real. Todo lo demás (p. ej. SVG) se deja en la base. */
    public const TIPOS = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];

    /** Alfabeto base64 estándar (sin relleno). */
    private const ALFABETO_B64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

    /** Bytes que se miran hacia atrás desde el `src` para reconocer `<img ... src="`. Cubre etiquetas con muchos atributos. */
    private const VENTANA_CONTEXTO = 4096;

    /**
     * Detecta el tipo REAL de una imagen por sus «magic bytes» (el MIME declarado en el `data:` miente: en los datos
     * medidos, 1.941 GIF se declaraban `image/png`).
     *
     * @param  string  $bin  Contenido binario ya decodificado.
     * @return string|null Extensión (`png`, `jpg`, `gif`, `webp`) o null si no es uno de los tipos admitidos.
     */
    public static function tipoReal(string $bin): ?string
    {
        if (str_starts_with($bin, "\x89PNG\r\n\x1a\n")) {
            return 'png';
        }
        if (str_starts_with($bin, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($bin, 'GIF87a') || str_starts_with($bin, 'GIF89a')) {
            return 'gif';
        }
        if (strlen($bin) >= 12 && substr($bin, 0, 4) === 'RIFF' && substr($bin, 8, 4) === 'WEBP') {
            return 'webp';
        }

        return null;
    }

    /**
     * Decodifica base64 de forma ESTRICTA: solo alfabeto estándar, relleno `=` únicamente al final (0 a 2), longitud
     * múltiplo de 4 y no vacío. No tolera espacios ni saltos (en los datos medidos no existen): lo que no cumple se
     * considera dañado y la fila no se toca.
     *
     * @param  string  $b64  Texto base64.
     * @return string|null Binario, o null si el base64 no es válido.
     */
    public static function decodificarEstricto(string $b64): ?string
    {
        $largo = strlen($b64);
        if ($largo === 0 || $largo % 4 !== 0) {
            return null;
        }
        $sinRelleno = rtrim($b64, '=');
        if ($largo - strlen($sinRelleno) > 2 || strspn($sinRelleno, self::ALFABETO_B64) !== strlen($sinRelleno)) {
            return null;
        }
        $bin = base64_decode($b64, true);

        return ($bin === false || $bin === '') ? null : $bin;
    }

    /**
     * Si en la posición `$pos` empieza el valor de un atributo `src` de una etiqueta `<img>` entre comillas, devuelve la
     * comilla de apertura; si no, null.
     *
     * @param  string  $texto  HTML completo.
     * @param  int  $pos  Posición del primer carácter del valor (justo después de la comilla de apertura).
     * @return string|null `"` o `'`.
     */
    private static function comillaDeSrcEnImg(string $texto, int $pos): ?string
    {
        $inicio = max(0, $pos - self::VENTANA_CONTEXTO);
        $previo = substr($texto, $inicio, $pos - $inicio);
        // `(?<![\w:-])` evita confundir `data-src=` con `src=`; `[^<>]*?` impide salir de la etiqueta; `\z` ancla al final.
        if (preg_match('/<img\s[^<>]*?(?<![\w:-])src\s*=\s*(["\'])\z/is', $previo, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Localiza todas las apariciones de `data:image/` y las clasifica.
     *
     * @param  string  $texto  HTML de una celda.
     * @return array{uris: array<int, array{inicio: int, fin: int, b64_inicio: int, mime: string}>, desconocidas: array<int, string>}
     *                                                                                                                                `uris`: URI `data:image/<mime>;base64,<datos>` que son el `src` de un `<img>`; `inicio`/`fin` delimitan SOLO el valor
     *                                                                                                                                (sin comillas), `b64_inicio` es donde empieza el base64 y `mime` el MIME declarado en minúsculas.
     *                                                                                                                                `desconocidas`: motivos de las apariciones fuera de ese patrón (no se tocan).
     */
    public static function localizarDataUris(string $texto): array
    {
        $uris = [];
        $desconocidas = [];
        $pos = 0;
        while (($p = stripos($texto, 'data:image/', $pos)) !== false) {
            $pos = $p + 11;
            $comilla = self::comillaDeSrcEnImg($texto, $p);
            if ($comilla === null) {
                $desconocidas[] = 'fuera de <img src>';

                continue;
            }
            $fin = strpos($texto, $comilla, $p);
            if ($fin === false) {
                $desconocidas[] = 'comilla de cierre ausente';

                continue;
            }
            if (preg_match('/^data:image\/([A-Za-z0-9.+-]{1,40});base64,/i', substr($texto, $p, 80), $h) !== 1) {
                $desconocidas[] = 'no es base64';
                $pos = $fin;

                continue;
            }
            $uris[] = [
                'inicio' => $p,
                'fin' => $fin,
                'b64_inicio' => $p + strlen($h[0]),
                'mime' => 'image/'.strtolower($h[1]),
            ];
            $pos = $fin;
        }

        return ['uris' => $uris, 'desconocidas' => $desconocidas];
    }

    /**
     * Localiza las URL externalizadas (`<urlBase>/<2 hex>/<sha256>.<ext>`) que son el `src` de un `<img>`; es la operación
     * inversa de {@see self::localizarDataUris()} y la usa `--revertir`.
     *
     * @param  string  $texto  HTML de una celda.
     * @param  string  $urlBase  Prefijo configurado (`/contenido/fichas`), sin barra final.
     * @return array<int, array{inicio: int, fin: int, hash: string, ext: string}>
     */
    public static function localizarUrls(string $texto, string $urlBase): array
    {
        $res = [];
        $prefijo = $urlBase.'/';
        $patron = '#^'.preg_quote($prefijo, '#').'([0-9a-f]{2})/([0-9a-f]{64})\.(png|jpg|gif|webp)$#';
        $pos = 0;
        while (($p = strpos($texto, $prefijo, $pos)) !== false) {
            $pos = $p + strlen($prefijo);
            $comilla = self::comillaDeSrcEnImg($texto, $p);
            if ($comilla === null) {
                continue;
            }
            $fin = strpos($texto, $comilla, $p);
            if ($fin === false) {
                continue;
            }
            if (preg_match($patron, substr($texto, $p, $fin - $p), $m) === 1 && $m[1] === substr($m[2], 0, 2)) {
                $res[] = ['inicio' => $p, 'fin' => $fin, 'hash' => $m[2], 'ext' => $m[3]];
            }
            $pos = $fin;
        }

        return $res;
    }

    /**
     * Sustituye tramos del texto sin tocar nada más: el resto queda byte a byte igual.
     *
     * @param  string  $texto  Texto original.
     * @param  array<int, array{inicio: int, fin: int, nuevo: string}>  $cambios  Tramos a reemplazar, en orden creciente y sin solaparse.
     */
    public static function reemplazarTramos(string $texto, array $cambios): string
    {
        $partes = [];
        $cursor = 0;
        foreach ($cambios as $c) {
            $partes[] = substr($texto, $cursor, $c['inicio'] - $cursor);
            $partes[] = $c['nuevo'];
            $cursor = $c['fin'];
        }
        $partes[] = substr($texto, $cursor);

        return implode('', $partes);
    }
}
