<?php

/**
 * Router del servidor de DESARROLLO de Explora (sitio estático + API Laravel en el mismo origen).
 *
 * SOLO PARA DESARROLLO. En producción se usa Apache con DocumentRoot en `laravel/public` (ver
 * DOCUMENTACION_MEJORAMIENTO/DESPLIEGUE_APACHE.md). Este archivo reproduce en lo posible las reglas de
 * `laravel/public/.htaccess` para que el sitio y la API compartan origen (la sesión es una cookie SameSite=Lax con CSRF).
 *
 * Desde la Fase 9 el DOCUMENTO RAÍZ es `laravel/public/` y contiene todo el sitio estático. `laravel/.env`, `vendor/`,
 * `storage/` y la documentación quedan fuera del docroot, así que ya no hace falta un bloqueo por nombre de carpeta.
 *
 * Cómo arrancarlo (desde `laravel/`; el 8000 puede estar ocupado, se sugiere el 8080):
 *
 *     PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 -t public servidor-desarrollo.php
 *
 * Conviene `PHP_CLI_SERVER_WORKERS=4`: el servidor integrado atiende una petición a la vez y las fichas JSON pesan
 * hasta 48 MB, de modo que una descarga lenta bloquearía el resto de la página.
 *
 * Orden de decisiones (el primero que aplica responde):
 *  1. Ruta inválida (byte nulo, `..` o `\`)              -> 400.
 *  2. `/api` o `/api/...`                                -> Laravel (laravel/public/index.php).
 *     (Las demás rutas que no existen en disco también llegan a Laravel; ver el punto 6.)
 *  3. Segmentos ocultos (`.git`, `.env`, `.htaccess`…)   -> 404 (defensa en profundidad: no deberían existir).
 *  4. Cualquier segmento acabado en .php/.phtml/.phar/.phpN -> el router responde él mismo, NUNCA `false`: si
 *     devolviera `false`, `php -S` ejecutaría el PHP legado (incluso con PATH_INFO, p. ej. `/x.php/a.css`).
 *     Los 7 scripts sustituidos por la API dan 410 JSON; cualquier otro PHP da 404. En DESARROLLO el chat
 *     (`mapplic/php/chat.php` y `chat copy.php`) queda DESACTIVADO a propósito: usa credenciales reales de MySQL
 *     escritas en el código y no debe ejecutarse desde este servidor.
 *  5. Extensiones sensibles (sql, gz, zip, md, sh, lock, log, bak) -> 404.
 *  6. Archivo existente dentro de la raíz -> `return false` y `php -S` lo sirve. Carpetas: la RAÍZ `/` la atiende
 *     Laravel (desde la Fase 11 la portada es la vista `inicio`; ya no existe `public/index.html`, igual que en Apache,
 *     donde `DirectoryIndex index.php index.html` entrega `/` al front controller). Las demás carpetas: sin index.html
 *     -> 404; con index.html se sirve ESE archivo desde el propio router (`/juegos/`…). Una carpeta pedida sin «/»
 *     final se redirige (301).
 *  7. Ruta que NO existe como archivo ni carpeta (y que pasó los filtros anteriores) -> Laravel, igual que el
 *     `RewriteRule ^ index.php` de `public/.htaccess` en Apache. Así funcionan las páginas Blade (`/climatico`),
 *     sus redirecciones (`/climatico.html` -> 301) y el 404 propio de Laravel para lo desconocido. Una ruta con «/»
 *     final que no es carpeta se redirige (301) sin esa barra, como la regla «Trailing Slashes» de Apache.
 *
 * Protección de bases de datos: con `EXPLORA_SOLO_BD_PRUEBA=1` el router se niega (500) a atender CUALQUIER ruta que
 * llegue a Laravel (`/api` y las páginas) si `DB_DATABASE` o `PEDIGITAL_DB_DATABASE` no acaban en `_test`: las páginas
 * Blade arrancan la sesión y podrían tocar la base. Así las pruebas automáticas nunca tocan datos reales.
 *
 * Las respuestas de error no revelan rutas del sistema.
 */

/**
 * Responde con un texto breve y termina la petición.
 *
 * @param  int  $codigo  Código de estado HTTP.
 * @param  string  $texto  Mensaje corto, sin rutas del sistema.
 * @return true Siempre devuelve true: indica a `php -S` que la petición ya fue atendida.
 */
function explora_responder_texto(int $codigo, string $texto): bool
{
    http_response_code($codigo);
    header('Content-Type: text/plain; charset=utf-8');
    echo $texto;

    return true;
}

/**
 * Responde con el JSON de error del servicio retirado (410) que reemplaza a un PHP legado sustituido por la API.
 *
 * @return true Siempre true (petición atendida).
 */
function explora_responder_retirado(): bool
{
    http_response_code(410);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'mensaje' => 'Servicio retirado'], JSON_UNESCAPED_UNICODE);

    return true;
}

/**
 * Lee el valor de una variable desde el entorno del proceso o, si no está, desde laravel/.env.
 *
 * Solo se consultan las claves que se piden (nunca se vuelca ni se registra el contenido del archivo) y el valor
 * se usa únicamente para comprobar su sufijo.
 *
 * @param  string  $clave  Nombre de la variable (p. ej. `DB_DATABASE`).
 * @return string|null Valor encontrado, o null si no está definida en ningún sitio.
 */
function explora_leer_variable(string $clave): ?string
{
    $valor = getenv($clave);
    if ($valor !== false) {
        return $valor;
    }

    $env = __DIR__.'/.env';
    if (! is_readable($env)) {
        return null;
    }
    foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
        if (preg_match('/^\s*'.preg_quote($clave, '/').'\s*=\s*(.*)$/', $linea, $m)) {
            // Quita comentarios al final y comillas simples o dobles alrededor del valor.
            $v = trim(preg_replace('/\s+#.*$/', '', $m[1]));

            return trim($v, "\"'");
        }
    }

    return null;
}

/**
 * Entrega la petición actual al front controller de Laravel (`public/index.php`), tras aplicar la guarda de bases de prueba.
 *
 * No modifica REQUEST_URI: Laravel debe ver la ruta completa (p. ej. `/api/sesion` o `/climatico`).
 *
 * @param  bool  $esApi  true si la ruta es de la API (el error de la guarda se responde en JSON; si no, en texto).
 * @return true Siempre true (petición atendida).
 */
function explora_delegar_en_laravel(bool $esApi): bool
{
    if (getenv('EXPLORA_SOLO_BD_PRUEBA') === '1') {
        foreach (['DB_DATABASE', 'PEDIGITAL_DB_DATABASE'] as $clave) {
            if (! str_ends_with((string) explora_leer_variable($clave), '_test')) {
                if (! $esApi) {
                    return explora_responder_texto(500, 'Servidor configurado solo para bases de prueba');
                }
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'mensaje' => 'Servidor configurado solo para bases de prueba'], JSON_UNESCAPED_UNICODE);

                return true;
            }
        }
    }

    $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/public/index.php';
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
    chdir(__DIR__.'/public');
    require __DIR__.'/public/index.php';

    return true;
}

// EXPLORA_RAIZ_SITIO permite apuntar a otra raíz con el mismo router; por defecto, el documento raíz laravel/public.
$raiz = realpath(getenv('EXPLORA_RAIZ_SITIO') ?: __DIR__.'/public');
$ruta = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// 1) Rutas con byte nulo, «..» o barra invertida: intento de salir de la raíz o de confundir al servidor.
if (str_contains($ruta, "\0") || str_contains($ruta, '..') || str_contains($ruta, '\\')) {
    return explora_responder_texto(400, 'Solicitud no válida');
}

// 2) La API se delega en Laravel (con la guarda de bases de prueba).
if (preg_match('#^/api(/|$)#', $ruta)) {
    return explora_delegar_en_laravel(true);
}

$segmentos = array_values(array_filter(explode('/', $ruta), static fn (string $s): bool => $s !== ''));
$minusculas = array_map('strtolower', $segmentos);

// 3) Segmentos ocultos (`.git`, `.env`, `.htaccess`…). Las carpetas privadas (laravel/, documentación, node_modules)
//    ya no están bajo el docroot, de modo que dan 404 por inexistentes (lo confirma ServidorDesarrolloTest).
foreach ($minusculas as $s) {
    if (str_starts_with($s, '.')) {
        return explora_responder_texto(404, 'No encontrado');
    }
}

// 4) Cualquier segmento ejecutable por PHP: se responde aquí y nunca se devuelve false.
foreach ($minusculas as $s) {
    if (preg_match('/\.(php|phtml|phar|php[0-9])$/', $s)) {
        $retirados = [
            'mapplic/php/iniciarsesion.php', 'mapplic/php/cambiaravatar.php',
            'mapplic/php/consultarinfo.php', 'mapplic/php/buscarpreguntas.php',
            'juegos/php/guardar_resultado.php', 'juegos/php/consultar_ranking.php',
            'juegos/php/consultar_historial.php',
        ];

        return in_array(implode('/', $minusculas), $retirados, true)
            ? explora_responder_retirado()
            : explora_responder_texto(404, 'No encontrado');
    }
}

// 5) Archivos que nunca deben descargarse.
if ($minusculas !== [] && preg_match('/\.(sql|gz|zip|md|sh|lock|log|bak)$/', end($minusculas))) {
    return explora_responder_texto(404, 'No encontrado');
}

// 6) Debe existir dentro de la raíz; las carpetas solo se sirven si tienen index.html.
$real = realpath($raiz.$ruta);
if ($real === false) {
    // 7) No existe en disco: como Apache, se envía al front controller de Laravel (páginas Blade, redirecciones, 404 de Laravel).
    if ($ruta !== '/' && str_ends_with($ruta, '/')) {
        // Regla «Redirect Trailing Slashes» de .htaccess: se quita la barra final. Se reconstruye desde los segmentos
        // codificados (nunca se refleja la ruta cruda) y se conserva la cadena de consulta.
        $consulta = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
        http_response_code(301);
        header('Location: /'.implode('/', array_map('rawurlencode', $segmentos)).($consulta !== '' ? '?'.preg_replace('/[\r\n]/', '', $consulta) : ''));

        return true;
    }

    return explora_delegar_en_laravel(false);
}
if ($real !== $raiz && ! str_starts_with($real, $raiz.DIRECTORY_SEPARATOR)) {
    // Existe pero fuera de la raíz (enlace simbólico): nunca se sirve ni se delega.
    return explora_responder_texto(404, 'No encontrado');
}
if ($real === $raiz) {
    // Raíz del sitio: la portada es la ruta `/` de Laravel (con la guarda de bases de prueba). Cualquier otro
    // contenido del docroot se sirve como archivo; `/index.html` ya no existe en disco y Laravel lo redirige a `/`.
    return explora_delegar_en_laravel(false);
}
if (is_dir($real)) {
    $indice = $real.DIRECTORY_SEPARATOR.'index.html';
    if (! is_file($indice)) {
        return explora_responder_texto(404, 'No encontrado');
    }
    // Carpeta sin «/» final: se redirige para que las rutas relativas de la página (../css/…) resuelvan bien.
    if ($real !== $raiz && ! str_ends_with($ruta, '/')) {
        http_response_code(301);
        // Se reconstruye desde los segmentos codificados (nunca se refleja la ruta cruda: sin inyección de cabeceras).
        header('Location: /'.implode('/', array_map('rawurlencode', $segmentos)).'/');

        return true;
    }
    // Se sirve index.html a mano (ver punto 6 de la cabecera): `return false` dejaría que `php -S` prefiriera index.php.
    header('Content-Type: text/html; charset=utf-8');
    readfile($indice);

    return true;
}

return false;
