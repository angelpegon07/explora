<?php

/**
 * COMPUERTA DE EQUIVALENCIA (Fases 10, 11 y 12): compara el HTML ORIGINAL de cada página migrada a Blade (los archivos
 * estáticos que ya no existen en el árbol de trabajo, recuperados de git) con el HTML que renderiza ahora Laravel.
 *
 * Páginas comparadas y su REFERENCIA por defecto (el commit donde vive su HTML original):
 *   - `index` (portada, ruta `/`)           -> 0c60734 (Fase 10; el último commit con `public/index.html`).
 *   - cinco mapas temáticos (`/<slug>`)     -> 0c7c594 (Fase 9; el último commit con `public/<slug>.html`).
 *   - `juegos` (portal, ruta `/juegos`, SIN sesión) -> bb4b68b (Fase 11; el último commit con `public/juegos/index.html`).
 * Cada original se lee con `git show <referencia>:laravel/public/<archivo>` (`index.html`, `<slug>.html`, `juegos/index.html`).
 *
 * El PERFIL (`/perfil`) NO se compara por texto: el original era un formulario vacío que rellenaba el JS y la vista
 * actual pinta el formulario (con sesión) o solo un aviso (sin ella), así que no hay HTML equivalente. En su lugar hay
 * una comprobación ESTRUCTURAL (ver `comprobarPerfil()` al final): todos los `id` del original existen en el render con
 * sesión, las funciones de los `onclick` siguen definidas en los JS, y el aviso sin sesión usa el mismo texto que el JS.
 * Esa parte necesita las bases de prueba (el perfil lee `pedigital_test`): el script las fuerza antes de arrancar Laravel.
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
 *   5b. `<meta charset>` se iguala en minúsculas (el portal original escribía «UTF-8»).
 *   5c. (solo el portal) las URL RELATIVAS del original (`css/x`, `../js/y`, `location.href = 'juegos/z'`) se resuelven
 *       contra su carpeta (`juegos/`), porque el original colgaba de `/juegos/index.html` y ahora se sirve en `/juegos`.
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
 *   - Fase 12, las seis páginas del mapa: el enlace del menú circular a los juegos pasa de `juegos/index.html` a `juegos`
 *     (la URL `/juegos/index.html` redirige con 301 a `/juegos`).
 *   - Fase 12, portal anónimo: el script de año (`juegos/js/anio-pie.js`, antes inline); el enlace «Mi Perfil» apunta a
 *     `perfil` en vez de `miPerfil.html`; y el estado inicial de la barra lo pinta el servidor: sin sesión `#btn-iniciar`
 *     sale con `display: block` (antes `none` y lo mostraba el JS). También cambia de sitio el <meta viewport> (el
 *     layout base lo escribe tras el <title>; el original, antes).
 */

use App\Models\Pedigital\Usuario;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

// Bases de PRUEBA antes de arrancar Laravel (la comprobación del perfil lee usuarios): se fuerzan en todas las vías
// por las que Laravel lee el entorno, igual que tests/Browser/fixtures.php. Las demás páginas no usan la base.
foreach (['DB_DATABASE' => 'explora_test', 'PEDIGITAL_DB_DATABASE' => 'pedigital_test'] as $clave => $valor) {
    putenv("$clave=$valor");
    $_ENV[$clave] = $valor;
    $_SERVER[$clave] = $valor;
}

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$http = $app->make(Illuminate\Contracts\Http\Kernel::class);

$verAdmitidas = in_array('--ver-diferencias', $argv, true);

/** Referencia git por defecto de cada página (ver cabecera): la portada en la Fase 10, los mapas en la Fase 9. */
const REFERENCIA_INDEX = '0c60734';
const REFERENCIA_MAPAS = '0c7c594';
const REFERENCIA_JUEGOS = 'bb4b68b';

// Páginas a comparar: nombre => [ruta que se pide a Laravel, archivo original en laravel/public/, referencia git,
// carpeta contra la que se resuelven las URL relativas del original (null = ninguna: el original ya colgaba de la raíz)].
$paginas = ['index' => ['/', 'index.html', REFERENCIA_INDEX, null]];
foreach (array_keys(config('mapas')) as $slug) {
    $paginas[$slug] = ['/'.$slug, $slug.'.html', REFERENCIA_MAPAS, null];
}
$paginas['juegos'] = ['/juegos', 'juegos/index.html', REFERENCIA_JUEGOS, 'juegos/'];

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
 * Resuelve una URL relativa del HTML ORIGINAL contra la carpeta de la que colgaba (paso 5c de la cabecera).
 * Solo se contempla un nivel de `../` (es lo que usa el portal). Se dejan igual las URL absolutas, las que empiezan por
 * `/`, los anclas, `javascript:` y `data:`.
 *
 * @param  string  $url  Valor del atributo o de `location.href`.
 * @param  string  $base  Carpeta del original relativa a la raíz del sitio, con barra final (p. ej. `juegos/`).
 * @return string URL relativa a la raíz del sitio.
 */
function resolverRelativa(string $url, string $base): string
{
    if ($url === '' || preg_match('#^(https?:|//|/|\#|data:|javascript:|mailto:)#i', $url)) {
        return $url;
    }

    return str_starts_with($url, '../') ? substr($url, 3) : $base.$url;
}

/**
 * Normaliza un documento HTML a una lista de «tokens» comparables (ver cabecera, pasos 1 a 6).
 *
 * @param  string  $html  Documento completo.
 * @param  string|null  $baseOriginal  Carpeta contra la que se resuelven las URL relativas (solo para el ORIGINAL del portal).
 * @return string[] Tokens ya recortados y sin vacíos.
 */
function normalizar(string $html, ?string $baseOriginal = null): array
{
    if ($baseOriginal !== null) {
        $html = preg_replace_callback('/\b(src|href)="([^"]*)"/i', static fn ($m) => $m[1].'="'.resolverRelativa($m[2], $baseOriginal).'"', $html);
        $html = preg_replace_callback("/location\.href = '([^']*)'/", static fn ($m) => "location.href = '".resolverRelativa($m[1], $baseOriginal)."'", $html);
    }
    $html = preg_replace('/<meta charset="[^"]*">/i', '<meta charset="utf-8">', $html);
    $html = str_replace('-->iv>', '-->', $html);
    $html = preg_replace('/<!--.*?-->/s', '', $html);
    // <script> sin atributo src (JS inline); los que llevan src se conservan.
    $html = preg_replace_callback('#<script\b([^>]*)>(.*?)</script>#is', static fn ($m) => preg_match('/\bsrc\s*=/i', $m[1]) ? $m[0] : '', $html);
    // Scripts nuevos con el JS extraído: no existían en el original (ver paso 2).
    $html = preg_replace('#<script\b[^>]*\bsrc="[^"]*js/(?:mapa-comun|mapa-tematico|inicio|anio-pie)\.js"[^>]*></script>#i', '', $html);
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

/**
 * Comprobación ESTRUCTURAL de `/perfil` (ver cabecera): no hay HTML original comparable por texto.
 *
 * Con sesión (usuario 1 de `pedigital_test`, puesto directamente en el guard): cada `id` del `miPerfil.html` original
 * (commit bb4b68b) debe existir en el render; cada función de un `onclick` del original debe seguir definida en los JS de
 * `public/miPerfil/`; el número de opciones de avatar debe ser `config('explora.avatares_max')`.
 * Sin sesión: el aviso usa el MISMO texto y el mismo enlace que `mostrarAvisoSinSesion()` de script.js, y no hay ni
 * formulario ni historial ni datos.
 *
 * @return int Número de fallos (0 = correcto).
 */
function comprobarPerfil($app, $http): int
{
    $fallos = 0;
    $original = (string) shell_exec('git show '.escapeshellarg(REFERENCIA_JUEGOS.':laravel/public/miPerfil.html').' 2>&1');
    if (! str_contains($original, '<html')) {
        fwrite(STDERR, 'No se pudo leer el original del perfil (miPerfil.html) en '.REFERENCIA_JUEGOS."\n");
        exit(2);
    }
    $js = (string) file_get_contents(__DIR__.'/../../public/miPerfil/script.js').(string) file_get_contents(__DIR__.'/../../public/miPerfil/cerrar-sesion.js');

    $pedir = static function () use ($http): string {
        $r = $http->handle(Request::create('http://localhost/perfil', 'GET'));
        if ($r->getStatusCode() !== 200) {
            fwrite(STDERR, 'perfil: HTTP '.$r->getStatusCode()."\n");
            exit(2);
        }

        return $r->getContent();
    };
    $informar = static function (string $mensaje) use (&$fallos): void {
        echo "    NO ADMITIDA  perfil: $mensaje\n";
        $fallos++;
    };

    // Con sesión.
    $app['auth']->guard('web')->setUser(Usuario::findOrFail(1));
    $con = $pedir();
    $app['auth']->forgetGuards();
    preg_match_all('/\bid="([^"]+)"/', $original, $ids);
    $faltan = array_filter(array_unique($ids[1]), static fn ($id) => ! str_contains($con, 'id="'.$id.'"'));
    foreach ($faltan as $id) {
        $informar("falta el id=\"$id\" del original en el render con sesión");
    }
    preg_match_all('/onclick="(?:return )?([A-Za-z_][A-Za-z0-9_]*)\(/', $original, $funciones);
    foreach (array_unique($funciones[1]) as $f) {
        if (! str_contains($js, 'function '.$f.'(')) {
            $informar("la función $f() del onclick original ya no está definida en public/miPerfil/*.js");
        }
    }
    $opciones = preg_match_all('/data-avatar="\d+"/', $con);
    if ($opciones !== (int) config('explora.avatares_max')) {
        $informar("opciones de avatar $opciones, se esperaban ".config('explora.avatares_max'));
    }

    // Sin sesión.
    $sin = $pedir();
    foreach (['Debe iniciar sesión para ver su perfil. ', 'Ir al inicio'] as $texto) {
        if (! str_contains($sin, $texto) || ! str_contains($js, $texto)) {
            $informar("el aviso sin sesión no usa el mismo texto que el JS: «$texto»");
        }
    }
    foreach (['id="aviso-sesion"', 'role="alert"', 'href="/"'] as $trozo) {
        if (! str_contains($sin, $trozo)) {
            $informar("el aviso sin sesión no contiene $trozo");
        }
    }
    foreach (['id="nombre"', 'id="usuario"', 'id="grado"', 'id="historial-body"', 'id="avatar-preview"', 'id="modal-avatar"', 'miPerfil/script.js'] as $trozo) {
        if (str_contains($sin, $trozo)) {
            $informar("el render sin sesión NO debería contener $trozo");
        }
    }

    // Toda URL de recurso, absoluta o raíz-relativa (misma regla que las demás páginas).
    foreach (['con sesión' => $con, 'sin sesión' => $sin] as $caso => $html) {
        preg_match_all('/\s(?:src|href)="([^"]*)"/i', $html, $urls);
        foreach (array_filter($urls[1], static fn ($u) => $u !== '' && ! preg_match('/^(https?:\/\/|#|data:|javascript:|\/(?!\/))/i', $u)) as $u) {
            $informar("URL relativa en el render $caso: $u");
        }
    }

    printf("%-18s estructura: ids originales=%d  funciones onclick=%d  avatares=%d  NO admitidas=%d\n", 'perfil', count(array_unique($ids[1])), count(array_unique($funciones[1])), $opciones, $fallos);

    return $fallos;
}

$fallos = 0;

foreach ($paginas as $slug => [$rutaPagina, $archivoOriginal, $referencia, $baseOriginal]) {
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
    // (Las que empiezan por «/», como el enlace «Volver» a la portada, son absolutas respecto al sitio: valen desde cualquier ruta.)
    $relativas = array_filter($urls[1], static fn ($u) => $u !== '' && ! preg_match('/^(https?:\/\/|#|data:|javascript:|\/(?!\/))/i', $u));
    foreach ($relativas as $u) {
        echo "    URL RELATIVA en el renderizado de $slug: $u\n";
        $fallos++;
    }

    $a = normalizar($original, $baseOriginal);
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
        '- </div>' => $slug === 'juegos' ? 0 : 1 + ($anidado ? 1 : 0) + ($slug === 'vertientes' ? 1 : 0) + ($slug === 'index' ? 1 : 0),
        '- <div class="modal-body quizmodal">' => $anidado ? 1 : 0,
    ];

    // Fase 12: diferencias EXACTAS admitidas, con su cupo. Primero las de las seis páginas del mapa (enlace del menú
    // circular a los juegos) y luego las del portal (ver cabecera).
    $exactas = [];
    if ($slug !== 'juegos') {
        $exactas['- <a id="btn_menu_juegos" href="juegos/index.html" target="_blank" class="menu-item itemm2">'] = 1;
        $exactas['+ <a id="btn_menu_juegos" href="juegos" target="_blank" class="menu-item itemm2">'] = 1;
    } else {
        $exactas['- <a href="miPerfil.html" style="display: none;" id="btn-perfil" class="btn btn-outline-warning">'] = 1;
        $exactas['+ <a href="perfil" style="display: none;" id="btn-perfil" class="btn btn-outline-warning">'] = 1;
        $exactas['- <a href="#" onclick="mostrarModalIniciarSesion(2)" type="button" id="btn-iniciar" style="display: none;" class="btn btn-outline-warning">'] = 1;
        $exactas['+ <a href="#" onclick="mostrarModalIniciarSesion(2)" type="button" id="btn-iniciar" style="display: block;" class="btn btn-outline-warning">'] = 1;
        // El layout base escribe <title> antes que <meta viewport> (el orden de las páginas del mapa); el original, al revés.
        $exactas['- <meta name="viewport" content="width=device-width, initial-scale=1.0">'] = 1;
        $exactas['+ <meta name="viewport" content="width=device-width, initial-scale=1.0">'] = 1;
    }
    foreach ($dif as $linea) {
        if (($exactas[$linea] ?? 0) > 0) {
            $exactas[$linea]--;
            $admitidas[] = $linea;

            continue;
        }
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
    foreach ($exactas as $linea => $restante) {
        if ($restante > 0) {
            $noAdmitidas[] = "(se esperaba la diferencia admitida: $linea)";
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

$fallos += comprobarPerfil($app, $http);

echo $fallos === 0 ? "\nEQUIVALENCIA OK: solo difieren las diferencias admitidas.\n" : "\nEQUIVALENCIA FALLIDA: $fallos diferencia(s) no admitida(s).\n";
exit($fallos === 0 ? 0 : 1);
