# DESPLIEGUE CON APACHE / XAMPP

Cómo publicar el sitio estático y la API Laravel (la aplicación vive en la raíz del repositorio) **en el mismo origen**. Es obligatorio porque la sesión
es una cookie `SameSite=Lax` con token CSRF y CORS no admite otros orígenes por defecto
(ver `GUIA_CONMUTACION_FRONTEND.md`, punto 1).

## Requisitos
- Apache 2.4 con `mod_rewrite` activo y `AllowOverride All` **solo sobre `public`** (el `.htaccess` de esa carpeta hace el trabajo).
- PHP ≥ 8.1, Composer 2 y MySQL 8.
- **DocumentRoot = `public`** (contiene los archivos estáticos del sitio —CSS, JS, imágenes, JSON— y el front controller
  `index.php`; desde la Fase 15 ya no queda ninguna página `index.html`: las páginas propias son vistas Blade, y el único HTML estático es `corrector.html`). `.env`, `vendor/`, `storage/` y `DOCUMENTACION_MEJORAMIENTO/` quedan FUERA del docroot: ya no hace falta
  ocultarlos con `.htaccess` en la raíz del repositorio ni depender de `AllowOverride` fuera de `public/`.

## Pasos
1. Copiar el repositorio al servidor y, desde la raíz del repositorio: `composer install --no-dev --optimize-autoloader`.
2. `cp .env.example .env`, completar credenciales y ejecutar `php artisan key:generate`.
3. En `.env` de producción: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con el dominio real (`https://explora.example.org`;
   además fija el único `Host` que acepta Laravel: cualquier otro da 400), `TRUSTED_HOSTS` si hay alias (p. ej. `www.explora.example.org`),
   `SESSION_SECURE_COOKIE=true` (con HTTPS) y, si hace falta, `API_POR_MINUTO` (300 por defecto: las aulas comparten IP por NAT).
   Ver la **Lista de comprobación de despliegue seguro** al final de este documento.
4. Habilitar HTTPS (certificado válido) y redirigir HTTP a HTTPS.

## Ejemplo de VirtualHost

```apache
<VirtualHost *:443>
    ServerName explora.example.org
    DocumentRoot "/var/www/explora/public"   # único documento raíz

    SSLEngine on
    SSLCertificateFile    /ruta/certificado.pem
    SSLCertificateKeyFile /ruta/clave.pem

    <Directory "/var/www/explora/public">
        AllowOverride All                    # necesario para el .htaccess de public/ (mod_rewrite)
        Require all granted
    </Directory>
    # Conceder AllowOverride All solo en public. Los .htaccess de la raíz del repositorio y de la documentación se
    # eliminaron en la revisión de la Fase 9: con AllowOverride All en una carpeta padre, un `Require all denied`
    # allí bloqueaba todo el sitio (probado con Apache 2.4.68) y, con la carpeta fuera del docroot, era redundante.
</VirtualHost>
```

En XAMPP: activar `LoadModule rewrite_module` en `httpd.conf`, apuntar `DocumentRoot` y `<Directory>` a la carpeta del
repositorio **`public`** y poner `AllowOverride All` solo ahí (y `AllowOverride None` en las carpetas superiores).

## Qué bloquea cada `.htaccess`

| Archivo | Efecto |
|---|---|
| `public/.htaccess` | Sin listado de directorios ni MultiViews; `DirectoryIndex index.php index.html` (`/` la atiende Laravel: la portada es la vista `inicio`); 404 para `.git` y `.env`; deniega `conexion*.php` y los archivos `sql, gz, zip, md, sh, lock, log, bak, map` (los `.map` son source maps de Bootstrap, que ninguna página necesita); con `mod_headers` añade `X-Content-Type-Options`, `X-Frame-Options` y `Referrer-Policy` y quita `X-Powered-By` (también en los archivos estáticos); front controller de Laravel (todo lo que no sea archivo ni carpeta, como `/api/...`, va a `index.php` con la ruta completa) |
| `.htaccess` de la antigua carpeta `laravel/` (hoy raíz del repositorio) y `DOCUMENTACION_MEJORAMIENTO/.htaccess` | **Eliminados** (revisión de la Fase 9): quedan fuera del docroot y un `Require all denied` en una carpeta padre rompía el sitio si se concedía `AllowOverride All` allí |
| (`.htaccess` de la raíz del repositorio) | **Eliminado en la Fase 9**: sus reglas se fusionaron en `public/.htaccess` |
| `mapplic/php/.htaccess` y `juegos/php/.htaccess` | **Eliminados en la Fase 15**: los 7 scripts PHP que bloqueaban se borraron del repositorio (etiqueta `pre-retiro-legado`) y la carpeta `juegos/php/` ya no existe. Su 410 pasó a **Laravel** (ver abajo) |

**Los 7 PHP retirados dan 410 desde Laravel (Fase 15).** `/mapplic/php/{iniciarSesion,cambiarAvatar,consultarInfo,buscarPreguntas}.php` y
`/juegos/php/{guardar_resultado,consultar_ranking,consultar_historial}.php` ya no existen como archivo, así que la regla genérica de
`public/.htaccess` (`RewriteRule ^ index.php`) las entrega al front controller y la ruta `servicio-retirado` de `routes/web.php`
responde `410 Gone` con `{"status":"error","mensaje":"Servicio retirado"}` (cualquier método, sin distinguir mayúsculas, sin cookies). La lista
sale de `config/servicios_retirados.php`. **Esto exige que PHP/Laravel funcione**: si el front controller falla, esas URL darían 500
y no 410. `public/.htaccess` mantiene denegado `^conexion.*\.php$` como defensa en profundidad (ya no existe ningún `conexion*.php`).

**Chat legado SIN protección (riesgo aceptado por el responsable).** `mapplic/php/chat.php` y `chat copy.php` se dejan como están: ya no hay
`.htaccess` en esa carpeta (nunca bloqueó el chat) y **bajo Apache se ejecutan como cualquier `.php`**, con sus credenciales de MySQL escritas
en el código y sin sesión ni límite de uso. Solo el router de desarrollo los bloquea (404). Si se despliega a un servidor accesible desde
Internet, conviene retirar esos dos archivos o bloquearlos a nivel de servidor mientras el chat siga aplazado (decisión del responsable). Ver el
punto correspondiente de la **Lista de comprobación de despliegue seguro**.

**`lineaTiempo` y `ViajaPED` (Fase 15).** Son carpetas reales con vista Blade: `RewriteRule ^(lineaTiempo|ViajaPED)/?$ index.php [L]` envía a Laravel
`/lineaTiempo/` y `/ViajaPED/` (los archivos de cada carpeta se sirven directamente; la forma sin barra y `index.html` redirigen con 301 a la canónica con barra).

## Comprobación con curl

```bash
curl -I https://explora.example.org/index.html                       # 301 a / (la portada es una ruta de Laravel; esta URL solo redirige)
curl -I https://explora.example.org/api/sesion                       # 401 (JSON): llega a Laravel
curl -I https://explora.example.org/mapplic/php/consultarInfo.php    # 410
curl -I https://explora.example.org/juegos/php/guardar_resultado.php # 410
curl -I https://explora.example.org/mapplic/php/conexion.php         # 404 (el archivo ya no existe; 403 si reapareciera, por .htaccess)
curl -I https://explora.example.org/lineaTiempo/                     # 200 (Blade); /lineaTiempo -> 301 a /lineaTiempo/
curl -I https://explora.example.org/juegos/juegos/puzzle/index.html  # 301 a /juegos/juegos/puzzle/ (el index.html ya no existe)
curl -I https://explora.example.org/css/                             # 403 (sin listado de directorios: -Indexes)
curl -I -H 'Host: otro.example' https://IP-DEL-SERVIDOR/             # 400 (Host no confiable; con vhost por defecto que rechace Hosts ajenos, ni llega a Laravel)
curl -I https://explora.example.org/                                 # 200 (lo atiende Laravel, vista `inicio`; sin Set-Cookie)
curl -I https://explora.example.org/.env                             # 404 (no existe bajo el docroot)
curl -I https://explora.example.org/.git/HEAD                        # 404
curl -I https://explora.example.org/DOCUMENTACION_MEJORAMIENTO/REGISTRO_CAMBIOS.md  # 404 (no existe bajo el docroot)
```

**Prueba histórica de la Fase 9** (ya NO describe el estado actual: entonces `/` y `/index.html` eran archivos estáticos; hoy `/` la atiende Laravel
y `/index.html` da 301). Apache local sin PHP (Fase 9, Apache 2.4.68, sin mod_php): `/` y `/index.html` 200 (HTML), `/mapplic/php/consultarInfo.php`
y `CONSULTARINFO.php` 410, `/juegos/php/guardar_resultado.php` 410, `conexion.php` 403, `/.git/HEAD` y `/.env` 404, `x.sql` y
`composer.lock` 403, `/css/` 403 (sin listado), `/juegos` 301 a `/juegos/`, y `/api/sesion` reescrito a `index.php`. La ejecución
de PHP (401 JSON de la API) no se pudo probar ahí por falta de módulo PHP; sí con `php -S`.

Prueba de la Fase 15 con Apache local sin PHP (2.4.68): solo se puede comprobar la REESCRITURA (sin PHP, `index.php` se devuelve como texto). `/lineaTiempo/`,
`/lineaTiempo`, `/ViajaPED/`, `/ViajaPED` y `/lineaTiempo/index.html` llegan a `index.php`; `/lineaTiempo/eventos.json` y `/ViajaPED/data.json` se sirven
como `application/json`; `/lineaTiempo/img/` y `/ViajaPED/epocas/` dan 403 (sin listado); las 7 rutas retiradas (también `INICIARSESION.PHP` en mayúsculas)
llegan a `index.php`; `/mapplic/php/chat.php` lo sirve el propio archivo (no pasa por Laravel) y `conexion.php` da 403. El 410 final lo emite Laravel y se
verificó con `php -S`/`artisan serve` y con PHPUnit (`ServiciosRetiradosTest`). Nota: en macOS (sistema de archivos sin distinción de mayúsculas)
`/lineatiempo/` da 403 por existir la carpeta; en Linux, 404.

## Por qué no hace falta `Alias /api`
Como el docroot ya es `public`, `/api/...` no es un archivo y el front controller (`RewriteRule ^ index.php`) lo recibe
con la ruta **completa** (con el prefijo `api` que esperan las rutas de `routes/api.php`). Antes de la Fase 9 una regla
`RewriteRule ^api(/|$) laravel/public/index.php` (con la aplicación aún en la subcarpeta `laravel/`) cumplía esa función.

## Limitación conocida
Servir el sitio **en un subdirectorio** (p. ej. `https://dominio/explora/`) **NO está soportado todavía**: las reglas
asumen que el sitio está en la raíz del dominio y el frontend usará `/api` absoluto.

## Servidor de desarrollo (sin Apache)
Desde la raíz del repositorio (puerto 8080: el 8000 está ocupado en este equipo):

```bash
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 -t public servidor-desarrollo.php
```

- `PHP_CLI_SERVER_WORKERS=4` evita que una ficha pesada (hasta 48 MB) bloquee el resto de peticiones.
- El router (`servidor-desarrollo.php`) reproduce las reglas de los `.htaccess`: delega `/api/*` y todo lo que no existe en disco en Laravel
  (la raíz `/` también la atiende Laravel), da 410 a los 7 PHP retirados (misma lista que las rutas 410 de Laravel), 404 a código, credenciales, volcados y
  `*.map`, y 400 a rutas con `..`, `\` o `%2F` (barra codificada, como Apache por defecto). Una carpeta con `index.html` se sirve a mano (`php -S` preferiría `public/index.php`).
- **El chat legado queda desactivado en desarrollo** (404): usa credenciales reales de MySQL escritas en el código.
- Con `EXPLORA_SOLO_BD_PRUEBA=1` la guarda `_test` rechaza atender CUALQUIER ruta que llegue a Laravel (`/api` y las páginas Blade) si las bases configuradas no terminan en `_test`.
- Solo para desarrollo; nunca exponerlo a Internet.

## Verificación de la regla `/juegos` (Fase 12, probada con Apache 2.4.68 sin PHP)
`/juegos` es una carpeta real; la regla `RewriteRule ^juegos/?$ index.php` la envía a Laravel. Con Apache real: `/juegos/` →
200 (front controller), `/juegos` → 301 a `/juegos/` (la redirección de `mod_dir` por carpeta sin barra llega antes; es un salto
extra inofensivo) y `/juegos/css/styles.css` → 200 estático; `/juegos/juegos/puzzle/index.html` → 301 a `/juegos/juegos/puzzle/` (desde la Fase 13 ese archivo ya no
existe y lo redirige Laravel; entonces, con el archivo, daba 200); `/juegos/js/` y `/miPerfil/` no se listan: **403 en Apache** (`-Indexes`; el router de
desarrollo da 404). Comprobado por el líder; la respuesta JSON de la API bajo Apache sigue sin probarse (sin `mod_php`).

## Lista de comprobación de despliegue seguro

Revisión posterior a la Fase 15 (verificación independiente de seguridad y calidad). Marcar cada punto antes de abrir el sitio a Internet.

**Laravel (`.env`)**
- [ ] `APP_ENV=production` y `APP_DEBUG=false` (con `local` se aceptan hosts de desarrollo y, con `APP_DEBUG=true`, se muestran trazas).
- [ ] `APP_URL` con el dominio REAL y `https://`. Es el único `Host` que acepta el middleware `TrustHosts` (cualquier otro da 400; si `APP_URL` no tiene un host válido y no hay `TRUSTED_HOSTS`, NO se restringe nada). Sin esto, la cabecera `Host` controlaba las URL absolutas (redirecciones y `asset()`).
- [ ] `TRUSTED_HOSTS` solo si hay alias (`www.explora.example.org`), nombres exactos separados por comas.
- [ ] `SESSION_SECURE_COOKIE=true` con HTTPS (en `config/session.php` el valor por defecto es `null`, es decir, la cookie NO se marca `Secure`). Mantener `HttpOnly` y `SameSite=Lax`.
- [ ] `APP_KEY` nueva, credenciales de MySQL nuevas y de mínimo privilegio (ver `PROPUESTA_DESPLIEGUE.md`).

**Apache / PHP**
- [ ] Un VirtualHost POR DEFECTO (el primero cargado, o `<VirtualHost _default_:80 _default_:443>`) que rechace Hosts ajenos (`Require all denied` o redirección fija al dominio): así una petición con `Host: otro.example` ni llega a la aplicación.
- [ ] `expose_php = Off` en `php.ini` (la `.htaccess` ya hace `Header unset X-Powered-By`, pero no cubre peticiones que no pasen por ella).
- [ ] `ServerTokens Prod` y `ServerSignature Off` (no revelar la versión de Apache) y `TraceEnable off`.
- [ ] `mod_headers` activo: sin él no se aplica el bloque de cabeceras de `public/.htaccess` (los archivos estáticos quedarían sin ellas).
- [ ] HSTS, una vez HTTPS funcione de forma estable: `Header always set Strict-Transport-Security "max-age=31536000"` (empezar con un `max-age` corto; añadir `includeSubDomains` solo si todos los subdominios usan HTTPS). No se añade en la aplicación a propósito.
- [ ] CSP: recomendación, NO aplicada. El sitio usa `onclick=` y bloques `<script>` en línea, por lo que una CSP estricta lo rompería. Empezar con `Content-Security-Policy-Report-Only` (p. ej. `default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'`), revisar los informes y solo después pasarla a `Content-Security-Policy`.
- [ ] Vigilar el crecimiento de `storage/framework/sessions` solo en `/perfil`, `/juegos` y la API: el resto de páginas ya no crean sesión (verificado). Aun así, con `SESSION_DRIVER=file` conviene una limpieza periódica (`session:gc` no existe en Laravel 10; basta con el sorteo de `config/session.php`).

**Código desplegado**
- [ ] Desplegar desde un CHECKOUT LIMPIO (`git clone`/`git archive` de la rama `migracion-blade` ya revisada), NUNCA copiando el árbol de trabajo: en algunos equipos hay `public/ViajaPED/node_modules` (22 MB, no versionado, ignorado por `.gitignore`) que Apache serviría tal cual desde el docroot. Si existe, borrarlo antes de publicar.
- [ ] Comprobar tras desplegar: `curl -I` a `/` (sin `Set-Cookie`, con `X-Frame-Options` y compañía), `/perfil` (con `Set-Cookie`), `/api/sesion` (401 JSON) y una petición con `-H 'Host: otro.example'` (400).

**Riesgo conocido: `chat.php` y `chat copy.php` (decisión del responsable, NO aplicada)**
- `public/mapplic/php/chat.php` y `chat copy.php` son ejecutables bajo Apache, sin autenticación ni límite de uso, con las credenciales de MySQL escritas en el código (y presentes en el historial de git: hay que rotarlas) y con llamadas a Ollama de hasta 120 s (consumo de CPU). El router de desarrollo los bloquea (404); Apache NO.
- Opción de mitigación inmediata en `public/.htaccess`: `<Files "chat*.php">` + `Require all denied` + `</Files>`. **ROMPE el chat** (`mapplic/js/chat.js` los llama), así que solo se aplica si se decide desactivarlo hasta migrarlo. Alternativas: retirar los archivos del despliegue, o limitarlos por IP/autenticación en el servidor.
