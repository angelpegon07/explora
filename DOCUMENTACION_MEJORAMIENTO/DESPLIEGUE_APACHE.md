# DESPLIEGUE CON APACHE / XAMPP

Cómo publicar el sitio estático y la API Laravel (`laravel/`) **en el mismo origen**. Es obligatorio porque la sesión
es una cookie `SameSite=Lax` con token CSRF y CORS no admite otros orígenes por defecto
(ver `GUIA_CONMUTACION_FRONTEND.md`, punto 1).

## Requisitos
- Apache 2.4 con `mod_rewrite` activo y `AllowOverride All` sobre la raíz del repositorio (los `.htaccess` hacen el trabajo).
- PHP ≥ 8.1, Composer 2 y MySQL 8.
- **DocumentRoot = la raíz del repositorio** (donde está `index.html`), no `laravel/public`.

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
    DocumentRoot "/var/www/explora"          # raíz del repositorio

    SSLEngine on
    SSLCertificateFile    /ruta/certificado.pem
    SSLCertificateKeyFile /ruta/clave.pem

    <Directory "/var/www/explora">
        AllowOverride All                    # necesario para los .htaccess
        Require all granted
    </Directory>

    # Defensa en profundidad: el código de Laravel (.env, vendor, storage) nunca debe ser accesible por URL...
    <Directory "/var/www/explora/laravel">
        Require all denied
    </Directory>
    # ...salvo public/, destino de la reescritura de /api/*.
    <Directory "/var/www/explora/laravel/public">
        Require all granted
    </Directory>
</VirtualHost>
```

En XAMPP: activar `LoadModule rewrite_module` en `httpd.conf`, apuntar `DocumentRoot` y `<Directory>` a la carpeta del
repositorio y poner `AllowOverride All`.

## Qué bloquea cada `.htaccess`

| Archivo | Efecto |
|---|---|
| `.htaccess` (raíz) | Sin listado de directorios ni MultiViews; 404 para `.git` y `.env`; deniega `conexion*.php` y los archivos `sql, gz, zip, md, sh, lock, log, bak`; reescribe `/api/...` a `laravel/public/index.php` |
| `laravel/.htaccess` | `Require all denied`: protege `.env`, `vendor/`, `storage/`, `config/` |
| `laravel/public/.htaccess` | `Require all granted` (permite el destino de la reescritura) y el front controller estándar de Laravel |
| `DOCUMENTACION_MEJORAMIENTO/.htaccess` | `Require all denied`: la carpeta describe vulnerabilidades |
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
curl -I https://explora.example.org/laravel/.env                     # 403 o 404
curl -I https://explora.example.org/.git/HEAD                        # 404
curl -I https://explora.example.org/DOCUMENTACION_MEJORAMIENTO/REGISTRO_CAMBIOS.md  # 403
```

## Por qué no se usa `Alias /api`
Con `Alias /api /ruta/laravel/public`, Apache entrega a Laravel la ruta **sin** el prefijo (`/sesion`), pero las rutas
de `routes/api.php` se registran con el prefijo `api` (`RouteServiceProvider`) y darían 404. La reescritura
`RewriteRule ^api(/|$) laravel/public/index.php` conserva la URL completa.

## Limitación conocida
Servir el sitio **en un subdirectorio** (p. ej. `https://dominio/explora/`) **NO está soportado todavía**: las reglas
asumen que el sitio está en la raíz del dominio y el frontend usará `/api` absoluto.

## Servidor de desarrollo (sin Apache)
Desde la raíz del repositorio (puerto 8080: el 8000 está ocupado en este equipo):

```bash
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 -t . laravel/servidor-desarrollo.php
```

- `PHP_CLI_SERVER_WORKERS=4` evita que una ficha pesada (hasta 48 MB) bloquee el resto de peticiones.
- El router (`laravel/servidor-desarrollo.php`) reproduce las reglas de los `.htaccess`: delega `/api/*` en Laravel, da 410
  a los 7 PHP sustituidos y 404 a código, credenciales y documentación.
- **El chat legado queda desactivado en desarrollo** (404): usa credenciales reales de MySQL escritas en el código.
- Con `EXPLORA_SOLO_BD_PRUEBA=1` rechaza atender `/api` si las bases configuradas no terminan en `_test`.
- Solo para desarrollo; nunca exponerlo a Internet.
