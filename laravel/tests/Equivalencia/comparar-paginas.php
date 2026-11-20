<?php

/**
 * COMPUERTA DE EQUIVALENCIA de la Fase 10: compara el HTML ORIGINAL de cada mapa temático (el archivo estático
 * `public/<pagina>.html` tal como está en HEAD) con el HTML que renderiza ahora Laravel.
 *
 * Uso (desde `laravel/`):
 *
 *     php tests/Equivalencia/comparar-paginas.php [--ver-diferencias] [referencia-git]
 *
 *   - `referencia-git`: commit donde están los HTML originales (por defecto `HEAD`; tras confirmar la Fase 10 hay
 *     que pasar el commit anterior, p. ej. `HEAD~1`).
 *   - `--ver-diferencias`: imprime también cada diferencia ADMITIDA (siempre se imprimen las NO admitidas).
 *
 * Termina con código 0 si todas las diferencias están en la lista de admitidas, 1 si hay alguna no admitida.
 * No necesita servidor: arranca la aplicación Laravel en el propio proceso y atiende `GET /<slug>` con el kernel.
 *
 * Normalización (en AMBOS lados) antes de comparar:
 *   1. Se descartan los comentarios HTML (`<!-- -->`) y, antes, el defecto de vertientes «-->iv>» (texto suelto).
 *   2. Se descartan los <script> SIN `src` (el JS inline) y la etiqueta nueva de js/mapa-tematico.js. Ese JS se extrajo a public/js/mapa-tematico.js y su
 *      comportamiento lo cubren las pruebas de navegador (Playwright), no esta comparación de marcado.
 *   3. Se colapsan espacios y saltos de línea y se quita el blanco entre etiquetas.
 *   4. Las URL absolutas de asset() se vuelven relativas (se quita `http://localhost/`).
 *   5. `iniciarTour('x')` y `iniciarTour("x")`/`'xñ'` se igualan (la vista emite un literal JS seguro con Js::from()).
 *   6. El texto se trocea tras `>`, `{`, `}` y `;` para que cada diferencia sea una línea legible.
 *
 * DIFERENCIAS ADMITIDAS (único contenido que puede diferir, además del JS inline ya descartado):
 *   - coordilleras: <title> corregido a «Mapa de Cordilleras de Colombia» y bloque #final añadido (omisión del original).
 *   - vertientes: el texto suelto «iv>» (se elimina en el paso 1) y un `</div>` sobrante que lo acompañaba
 *     (sin apertura; el navegador lo ignoraba).
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';

$verAdmitidas = in_array('--ver-diferencias', $argv, true);
$referencia = 'HEAD';
foreach (array_slice($argv, 1) as $a) {
    if ($a !== '--ver-diferencias') {
        $referencia = $a;
    }
}
// Solo se aceptan referencias git «normales» (se pasan a un comando de shell).
if (! preg_match('/^[A-Za-z0-9._~^\/-]+$/', $referencia)) {
    fwrite(STDERR, "Referencia git no válida\n");
    exit(2);
}

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$http = $app->make(Illuminate\Contracts\Http\Kernel::class);

/**
 * Normaliza un documento HTML a una lista de «tokens» comparables (ver cabecera, pasos 1 a 6).
 *
 * @param  string  $html  Documento completo.
 * @return string[] Tokens ya recortados y sin vacíos.
 */
function normalizar(string $html): array
{
    $html = str_replace('-->iv>', '-->', $html);
    $html = preg_replace('/<!--.*?-->/s', '', $html);
    // <script> sin atributo src (JS inline); los que llevan src se conservan.
    $html = preg_replace_callback('#<script\b([^>]*)>(.*?)</script>#is', static fn ($m) => preg_match('/\bsrc\s*=/i', $m[1]) ? $m[0] : '', $html);
    // Script nuevo con el JS extraído: no existía en el original (ver paso 2).
    $html = preg_replace('#<script\b[^>]*\bsrc="[^"]*js/mapa-tematico\.js"[^>]*></script>#i', '', $html);
    $html = str_replace('http://localhost/', '', $html);
    // iniciarTour('x') / iniciarTour("x") con escapes \uXXXX: se deja la forma 'x' sin escapes.
    $html = preg_replace_callback('/iniciarTour\((["\'])(.*?)\1\)/u', static function ($m) {
        $texto = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', static fn ($u) => mb_chr(hexdec($u[1])), $m[2]);

        return "iniciarTour('".$texto."')";
    }, $html);
    $html = preg_replace('/\s+/u', ' ', $html);
    $html = preg_replace('/>\s+</', '><', $html);

    // Cada etiqueta completa es un token (sin partirla por dentro); el texto y el CSS entre etiquetas se trocea tras `{`, `}` y `;`.
    $tokens = [];
    foreach (preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $trozo) {
        if ($trozo[0] === '<') {
            $tokens[] = $trozo;
        } else {
            array_push($tokens, ...preg_split('/(?<=[{};])/', $trozo, -1, PREG_SPLIT_NO_EMPTY));
        }
    }

    return array_values(array_filter(array_map('trim', $tokens), static fn ($t) => $t !== ''));
}

/**
 * Diferencias entre dos listas de tokens, en formato de diff clásico («- » solo en el original, «+ » solo en el nuevo).
 *
 * @param  string[]  $a  Tokens del original.
 * @param  string[]  $b  Tokens del renderizado.
 * @return string[] Líneas con prefijo «- » o «+ ».
 */
function diferencias(array $a, array $b): array
{
    $fa = tempnam(sys_get_temp_dir(), 'eqa');
    $fb = tempnam(sys_get_temp_dir(), 'eqb');
    file_put_contents($fa, implode("\n", $a)."\n");
    file_put_contents($fb, implode("\n", $b)."\n");
    $salida = [];
    exec('diff '.escapeshellarg($fa).' '.escapeshellarg($fb), $salida);
    unlink($fa);
    unlink($fb);

    $lineas = [];
    foreach ($salida as $l) {
        if (str_starts_with($l, '< ')) {
            $lineas[] = '- '.substr($l, 2);
        } elseif (str_starts_with($l, '> ')) {
            $lineas[] = '+ '.substr($l, 2);
        }
    }

    return $lineas;
}

/**
 * Quita de una lista de tokens el bloque `<div …id="final"…> … </div>` (cuenta los <div> para hallar su cierre).
 *
 * @param  string[]  $tokens  Tokens normalizados.
 * @return array{0:string[],1:int} Tokens sin el bloque y cuántos tokens tenía (0 si no existía).
 */
function quitarBloqueFinal(array $tokens): array
{
    foreach ($tokens as $inicio => $t) {
        if (str_starts_with($t, '<div') && str_contains($t, 'id="final"')) {
            $profundidad = 0;
            for ($fin = $inicio; $fin < count($tokens); $fin++) {
                if (str_starts_with($tokens[$fin], '<div')) {
                    $profundidad++;
                } elseif (str_starts_with($tokens[$fin], '</div>')) {
                    $profundidad--;
                }
                if ($profundidad === 0) {
                    break;
                }
            }
            $cantidad = $fin - $inicio + 1;
            array_splice($tokens, $inicio, $cantidad);

            return [$tokens, $cantidad];
        }
    }

    return [$tokens, 0];
}

$paginas = array_keys(config('mapas'));
$fallos = 0;

foreach ($paginas as $slug) {
    $original = shell_exec('git show '.escapeshellarg($referencia.':laravel/public/'.$slug.'.html').' 2>&1');
    if ($original === null || ! str_contains($original, '<html')) {
        fwrite(STDERR, "No se pudo leer el original de $slug en $referencia\n");
        exit(2);
    }
    $respuesta = $http->handle(Request::create('http://localhost/'.$slug, 'GET'));
    if ($respuesta->getStatusCode() !== 200) {
        fwrite(STDERR, "$slug: HTTP ".$respuesta->getStatusCode()."\n");
        exit(2);
    }

    // Comprobación extra: en el HTML renderizado TODA URL de recurso (src/href) debe ser absoluta (asset()) o un ancla.
    preg_match_all('/\s(?:src|href)="([^"]*)"/i', $respuesta->getContent(), $urls);
    $relativas = array_filter($urls[1], static fn ($u) => $u !== '' && ! preg_match('/^(https?:\/\/|#|data:|javascript:)/i', $u));
    foreach ($relativas as $u) {
        echo "    URL RELATIVA en el renderizado de $slug: $u\n";
        $fallos++;
    }

    $a = normalizar($original);
    $b = normalizar($respuesta->getContent());
    $admitidas = [];

    // Admitida: coordilleras no tenía #final. Se compara el resto y se informa del bloque añadido.
    $tenia = quitarBloqueFinal($a)[1];
    [$b2, $nuevo] = quitarBloqueFinal($b);
    if ($tenia === 0 && $nuevo > 0) {
        $b = $b2;
        $admitidas[] = "+ (bloque #final añadido: $nuevo tokens; el original no lo tenía)";
    }

    $dif = diferencias($a, $b);
    $noAdmitidas = [];
    $divSobranteUsado = false;
    foreach ($dif as $linea) {
        $esTitulo = $slug === 'coordilleras' && in_array($linea, [
            '- Mapa de Coordilleras de Colombia',
            '+ Mapa de Cordilleras de Colombia',
        ], true);
        // vertientes llevaba un `</div>` de más (6 cierres tras el comentario de volcanes frente a 5 aperturas): era
        // el resto del defecto «-->iv>» y el navegador lo ignoraba. Se admite exactamente UNA línea «- </div>».
        $esDivSobrante = $slug === 'vertientes' && $linea === '- </div>' && ! $divSobranteUsado;
        if ($esDivSobrante) {
            $divSobranteUsado = true;
        }
        if ($esTitulo || $esDivSobrante) {
            $admitidas[] = $linea;
        } else {
            $noAdmitidas[] = $linea;
        }
    }

    printf("%-18s tokens original=%d renderizado=%d  admitidas=%d  NO admitidas=%d\n", $slug, count($a), count($b), count($admitidas), count($noAdmitidas));
    if ($verAdmitidas) {
        foreach ($admitidas as $l) {
            echo "    admitida  $l\n";
        }
    }
    foreach ($noAdmitidas as $l) {
        echo "    NO ADMITIDA  $l\n";
    }
    $fallos += count($noAdmitidas);
}

echo $fallos === 0 ? "\nEQUIVALENCIA OK: solo difieren las diferencias admitidas.\n" : "\nEQUIVALENCIA FALLIDA: $fallos diferencia(s) no admitida(s).\n";
exit($fallos === 0 ? 0 : 1);
