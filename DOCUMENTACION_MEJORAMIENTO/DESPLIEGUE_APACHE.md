# DESPLIEGUE CON APACHE / XAMPP

Cómo publicar el sitio estático y la API Laravel (`laravel/`) **en el mismo origen**. Es obligatorio porque la sesión
es una cookie `SameSite=Lax` con token CSRF y CORS no admite otros orígenes por defecto
(ver `GUIA_CONMUTACION_FRONTEND.md`, punto 1).

## Requisitos
- Apache 2.4 con `mod_rewrite` activo y `AllowOverride All` **solo sobre `laravel/public`** (el `.htaccess` de esa carpeta hace el trabajo).
- PHP ≥ 8.1, Composer 2 y MySQL 8.
- **DocumentRoot = `laravel/public`** (desde la Fase 9 contiene `index.html`, el resto del sitio estático y el front controller
  `index.php`). `laravel/.env`, `vendor/`, `storage/` y `DOCUMENTACION_MEJORAMIENTO/` quedan FUERA del docroot: ya no hace falta
  ocultarlos con `.htaccess` en la raíz del repositorio ni depender de `AllowOverride` fuera de `public/`.

## Pasos
1. Copiar el repositorio al servidor y, dentro de `laravel/`: `composer install --no-dev --optimize-autoloader`.
2. `cp .env.example .env`, completar credenciales y ejecutar `php artisan key:generate`.
3. En `.env` de producción: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` (con HTTPS) y, si hace
   falta, `API_POR_MINUTO` (300 por defecto: las aulas comparten IP por NAT).
4. Habilitar HTTPS (certificado válido) y redirigir HTTP a HTTPS.

## Ejemplo de VirtualHost

```apache
<VirtualHost *:443>
    ServerName explora.example.org
    DocumentRoot "/var/www/explora/laravel/public"   # único documento raíz

    SSLEngine on
    SSLCertificateFile    /ruta/certificado.pem
    SSLCertificateKeyFile /ruta/clave.pem

    <Directory "/var/www/explora/laravel/public">
        AllowOverride All                    # necesario para el .htaccess de public/ (mod_rewrite)
        Require all granted
    </Directory>
    # Conceder AllowOverride All solo en laravel/public. Los .htaccess de laravel/ y de la documentación se
    # eliminaron en la revisión de la Fase 9: con AllowOverride All en una carpeta padre, un `Require all denied`
    # allí bloqueaba todo el sitio (probado con Apache 2.4.68) y, con la carpeta fuera del docroot, era redundante.
</VirtualHost>
```

En XAMPP: activar `LoadModule rewrite_module` en `httpd.conf`, apuntar `DocumentRoot` y `<Directory>` a la carpeta del
repositorio **`laravel/public`** y poner `AllowOverride All` solo ahí (y `AllowOverride None` en las carpetas superiores).

## Qué bloquea cada `.htaccess`

| Archivo | Efecto |
|---|---|
| `laravel/public/.htaccess` | Sin listado de directorios ni MultiViews; `DirectoryIndex index.html index.php` (`/` sirve la portada estática); 404 para `.git` y `.env`; deniega `conexion*.php` y los archivos `sql, gz, zip, md, sh, lock, log, bak`; front controller de Laravel (todo lo que no sea archivo ni carpeta, como `/api/...`, va a `index.php` con la ruta completa) |
| `laravel/.htaccess` y `DOCUMENTACION_MEJORAMIENTO/.htaccess` | **Eliminados** (revisión de la Fase 9): quedan fuera del docroot y un `Require all denied` en una carpeta padre rompía el sitio si se concedía `AllowOverride All` allí |
| (`.htaccess` de la raíz del repositorio) | **Eliminado en la Fase 9**: sus reglas se fusionaron en `laravel/public/.htaccess` |
| `mapplic/php/.htaccess` | 410 para `iniciarSesion`, `cambiarAvatar`, `consultarInfo`, `buscarPreguntas` (403 de respaldo sin mod_rewrite). **No** bloquea `chat.php` |
| `juegos/php/.htaccess` | 410 para `guardar_resultado`, `consultar_ranking`, `consultar_historial` (403 de respaldo) |

El chat legado (`mapplic/php/chat.php`, `chat copy.php`) se deja como está por decisión del responsable.

## Comprobación con curl

```bash
curl -I https://explora.example.org/index.html                       # 200
curl -I https://explora.example.org/api/sesion                       # 401 (JSON): llega a Laravel
curl -I https://explora.example.org/mapplic/php/consultarInfo.php    # 410
curl -I https://explora.example.org/juegos/php/guardar_resultado.php # 410
curl -I https://explora.example.org/mapplic/php/conexion.php         # 403
curl -I https://explora.example.org/                                 # 200 (index.html, no el front controller)
curl -I https://explora.example.org/laravel/.env                     # 404 (no existe bajo el docroot)
curl -I https://explora.example.org/.git/HEAD                        # 404
curl -I https://explora.example.org/DOCUMENTACION_MEJORAMIENTO/REGISTRO_CAMBIOS.md  # 404 (no existe bajo el docroot)
```

Prueba con Apache local sin PHP (Fase 9, Apache 2.4.68, sin mod_php): `/` y `/index.html` 200 (HTML), `/mapplic/php/consultarInfo.php`
y `CONSULTARINFO.php` 410, `/juegos/php/guardar_resultado.php` 410, `conexion.php` 403, `/.git/HEAD` y `/.env` 404, `x.sql` y
`composer.lock` 403, `/css/` 403 (sin listado), `/juegos` 301 a `/juegos/`, y `/api/sesion` reescrito a `index.php`. La ejecución
de PHP (401 JSON de la API) no se pudo probar ahí por falta de módulo PHP; sí con `php -S`.

## Por qué no hace falta `Alias /api`
Como el docroot ya es `laravel/public`, `/api/...` no es un archivo y el front controller (`RewriteRule ^ index.php`) lo recibe
con la ruta **completa** (con el prefijo `api` que esperan las rutas de `routes/api.php`). Antes de la Fase 9 una regla
`RewriteRule ^api(/|$) laravel/public/index.php` en la raíz del repositorio cumplía esa función.

## Limitación conocida
Servir el sitio **en un subdirectorio** (p. ej. `https://dominio/explora/`) **NO está soportado todavía**: las reglas
asumen que el sitio está en la raíz del dominio y el frontend usará `/api` absoluto.

## Servidor de desarrollo (sin Apache)
Desde `laravel/` (puerto 8080: el 8000 está ocupado en este equipo):

```bash
cd laravel && PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 -t public servidor-desarrollo.php
```

- `PHP_CLI_SERVER_WORKERS=4` evita que una ficha pesada (hasta 48 MB) bloquee el resto de peticiones.
- El router (`laravel/servidor-desarrollo.php`) reproduce las reglas de los `.htaccess`: delega `/api/*` en Laravel, da 410
  a los 7 PHP sustituidos y 404 a código, credenciales y volcados. Sirve `index.html` en `/` y en las carpetas (`php -S` preferiría `public/index.php`).
- **El chat legado queda desactivado en desarrollo** (404): usa credenciales reales de MySQL escritas en el código.
- Con `EXPLORA_SOLO_BD_PRUEBA=1` rechaza atender `/api` si las bases configuradas no terminan en `_test`.
- Solo para desarrollo; nunca exponerlo a Internet.

## Verificación de la regla `/juegos` (Fase 12, probada con Apache 2.4.68 sin PHP)
`/juegos` es una carpeta real; la regla `RewriteRule ^juegos/?$ index.php` la envía a Laravel. Con Apache real: `/juegos/` →
200 (front controller), `/juegos` → 301 a `/juegos/` (la redirección de `mod_dir` por carpeta sin barra llega antes; es un salto
extra inofensivo) y `/juegos/css/styles.css`, `/juegos/juegos/puzzle/index.html` → 200 estáticos; `/juegos/js/` y `/miPerfil/` no
se listan (404). Comprobado por el líder; la respuesta JSON de la API bajo Apache sigue sin probarse (sin `mod_php`).
