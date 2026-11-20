<?php

/**
 * COMPUERTA DE EQUIVALENCIA (Fases 10 y 11): compara el HTML ORIGINAL de cada página migrada a Blade (los archivos
 * estáticos que ya no existen en el árbol de trabajo, recuperados de git) con el HTML que renderiza ahora Laravel.
 *
 * Páginas comparadas y su REFERENCIA por defecto (el commit donde vive su HTML original):
 *   - `index` (portada, ruta `/`)           -> 0c60734 (Fase 10; el último commit con `public/index.html`).
 *   - cinco mapas temáticos (`/<slug>`)     -> 0c7c594 (Fase 9; el último commit con `public/<slug>.html`).
 * Cada original se lee con `git show <referencia>:laravel/public/<pagina>.html`.
 *
 * Uso (desde `laravel/`):
 *
 *     php tests/Equivalencia/comparar-paginas.php [--ver-diferencias] [--ref=<pagina>:<commit>]...
 *
 *   - `--ref=<pagina>:<commit>`: cambia la referencia de UNA página (repetible; `<pagina>` es `index` o el slug del mapa),
 *     p. ej. `--ref=index:0c60734 --ref=etnias:0c7c594`. `--ref=*:<commit>` la cambia en todas.
 *   - `--ver-diferencias`: imprime también cada diferencia ADMITIDA (siempre se imprimen las NO admitidas).
 *
 * Termina con código 0 si todas las diferencias están en la lista de admitidas, 1 si hay alguna no admitida
 * y 2 si no se pudo leer un original o una página no responde 200.
 * No necesita servidor: arranca la aplicación Laravel en el propio proceso y atiende `GET <ruta>` con el kernel.
 *
 * Normalización (en AMBOS lados) antes de comparar:
 *   1. Se descartan los comentarios HTML (`<!-- -->`, incluido el bloque condicional de Internet Explorer de la portada) y,
 *      antes, el defecto de vertientes «-->iv>» (texto suelto).
 *   2. Se descartan los <script> SIN `src` (el JS inline) y las etiquetas nuevas de js/mapa-comun.js, js/mapa-tematico.js e
 *      js/inicio.js. Ese JS se extrajo a archivos y su comportamiento lo cubren las pruebas de navegador (Playwright), no
 *      esta comparación de marcado.
 *   3. Se colapsan espacios y saltos de línea, se quita el blanco entre etiquetas y el espacio antes de `/>` (la portada
 *      escribía `<link ... />` y el layout compartido `<link .../>`: igual para el navegador).
 *   4. Las URL absolutas de asset() se vuelven relativas (se quita `http://localhost/`).
 *   5. `iniciarTour('x')` y `iniciarTour("x")`/`'xñ'` se igualan (la vista emite un literal JS seguro con Js::from()).
 *   6. El texto se trocea tras `>`, `{`, `}` y `;` para que cada diferencia sea una línea legible.
 *
 * DIFERENCIAS ADMITIDAS (único contenido que puede diferir, además del JS inline ya descartado). Cualquier otra
 * diferencia es un FALLO:
 *   - JS inline extraído a public/js/*.js (pasos 2) y URL absolutas con asset() (paso 4).
 *   - Bloque condicional de IE de la portada (html5shiv / mapplic-ie.css, código muerto de IE < 9): descartado (paso 1).
 *   - `</div>` sobrantes del original (sin apertura; el navegador los ignoraba): UNO tras #contenedorVideo en las seis
 *     páginas y, solo en la portada, otro tras el cierre del modal de tipo de mapa (el comentario de volcanes dejaba un
 *     cierre de más). Se descubrió al comparar la portada: el original tenía DOS, no uno.
 *   - `.modal-body.quizmodal` anidado de coordilleras, etnias, parques_naturales y vertientes, ahora unificado: se admiten
 *     exactamente la apertura sin estilo `- <div class="modal-body quizmodal">` y un `- </div>` de cierre, en esas cuatro.
 *   - coordilleras: <title> corregido a «Mapa de Cordilleras de Colombia» y bloque #final añadido (omisión del original).
 *   - vertientes: el texto suelto «iv>» (se elimina en el paso 1) y un `</div>` sobrante que lo acompañaba
 *     (sin apertura; el navegador lo ignoraba).
 *   - `iniciarTour(...)` normalizado (paso 5).
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$http = $app->make(Illuminate\Contracts\Http\Kernel::class);

$verAdmitidas = in_array('--ver-diferencias', $argv, true);

/** Referencia git por defecto de cada página (ver cabecera): la portada en la Fase 10, los mapas en la Fase 9. */
const REFERENCIA_INDEX = '0c60734';
const REFERENCIA_MAPAS = '0c7c594';

// Páginas a comparar: nombre => [ruta que se pide a Laravel, archivo original en laravel/public/, referencia git].
$paginas = ['index' => ['/', 'index.html', REFERENCIA_INDEX]];
foreach (array_keys(config('mapas')) as $slug) {
    $paginas[$slug] = ['/'.$slug, $slug.'.html', REFERENCIA_MAPAS];
}

foreach (array_slice($argv, 1) as $a) {
    if ($a === '--ver-diferencias') {
        continue;
    }
    if (! preg_match('/^--ref=([A-Za-z0-9_*]+):([A-Za-z0-9._~^\/-]+)$/', $a, $m)) {
        // Solo se aceptan referencias git «normales» (se pasan a un comando de shell).
        fwrite(STDERR, "Argumento no válido: $a\n");
        exit(2);
    }
    foreach ($paginas as $nombre => $datos) {
        if ($m[1] === '*' || $m[1] === $nombre) {
            $paginas[$nombre][2] = $m[2];
        }
    }
    if ($m[1] !== '*' && ! isset($paginas[$m[1]])) {
        fwrite(STDERR, "Página desconocida en $a\n");
        exit(2);
    }
}

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
    // Scripts nuevos con el JS extraído: no existían en el original (ver paso 2).
    $html = preg_replace('#<script\b[^>]*\bsrc="[^"]*js/(?:mapa-comun|mapa-tematico|inicio)\.js"[^>]*></script>#i', '', $html);
    $html = str_replace('http://localhost/', '', $html);
    // iniciarTour('x') / iniciarTour("x") con escapes \uXXXX: se deja la forma 'x' sin escapes.
    $html = preg_replace_callback('/iniciarTour\((["\'])(.*?)\1\)/u', static function ($m) {
        $texto = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', static fn ($u) => mb_chr(hexdec($u[1])), $m[2]);

        return "iniciarTour('".$texto."')";
    }, $html);
    $html = preg_replace('/\s+/u', ' ', $html);
    $html = preg_replace('/>\s+</', '><', $html);
    $html = preg_replace('/\s+\/>/', '/>', $html);

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

$fallos = 0;

foreach ($paginas as $slug => [$rutaPagina, $archivoOriginal, $referencia]) {
    $original = shell_exec('git show '.escapeshellarg($referencia.':laravel/public/'.$archivoOriginal).' 2>&1');
    if ($original === null || ! str_contains($original, '<html')) {
        fwrite(STDERR, "No se pudo leer el original de $slug ($archivoOriginal) en $referencia\n");
        exit(2);
    }
    $respuesta = $http->handle(Request::create('http://localhost'.$rutaPagina, 'GET'));
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

    // Cupos de diferencias «- …» admitidas (líneas que solo estaban en el original), por página:
    //  - `- </div>`: el sobrante tras #contenedorVideo (las seis páginas) + el cierre del `.quizmodal` anidado (los cuatro
    //    mapas que lo tenían) + el sobrante propio de vertientes (resto de «-->iv>») + el de la portada tras el modal de
    //    tipo de mapa (comentario de volcanes).
    //  - `- <div class="modal-body quizmodal">`: la apertura sin estilo del `.quizmodal` anidado (cuatro mapas).
    $anidado = in_array($slug, ['coordilleras', 'etnias', 'parques_naturales', 'vertientes'], true);
    $cupos = [
        '- </div>' => 1 + ($anidado ? 1 : 0) + ($slug === 'vertientes' ? 1 : 0) + ($slug === 'index' ? 1 : 0),
        '- <div class="modal-body quizmodal">' => $anidado ? 1 : 0,
    ];
    foreach ($dif as $linea) {
        $esTitulo = $slug === 'coordilleras' && in_array($linea, [
            '- Mapa de Coordilleras de Colombia',
            '+ Mapa de Cordilleras de Colombia',
        ], true);
        $enCupo = ($cupos[$linea] ?? 0) > 0;
        if ($enCupo) {
            $cupos[$linea]--;
        }
        if ($esTitulo || $enCupo) {
            $admitidas[] = $linea;
        } else {
            $noAdmitidas[] = $linea;
        }
    }
    // Un cupo sin consumir también es un aviso: la diferencia esperada no apareció (el original o la vista cambiaron).
    foreach ($cupos as $linea => $restante) {
        if ($restante > 0) {
            $noAdmitidas[] = "(se esperaba $restante vez/veces la diferencia admitida: $linea)";
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
