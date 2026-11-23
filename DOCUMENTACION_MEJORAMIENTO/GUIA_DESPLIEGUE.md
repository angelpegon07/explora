# GUÍA DE DESPLIEGUE (definitiva, Fase 16C)

Para quien despliega Explora **por primera vez** en un servidor propio. Sustituye a `PROPUESTA_DESPLIEGUE.md` (que ahora solo apunta aquí) y complementa
`DESPLIEGUE_APACHE.md` (qué bloquea cada `.htaccess`). Los archivos de apoyo están en la carpeta `deploy/` de la raíz del repositorio:

| Archivo | Para qué |
|---|---|
| `deploy/env.produccion.ejemplo` | Plantilla del `.env` de producción (sin secretos). |
| `deploy/apache-vhost.conf.ejemplo` | VirtualHosts de Apache 2.4 (por defecto, HTTP→HTTPS y el sitio) con PHP-FPM. |
| `deploy/php-fpm-pool.conf.ejemplo` | Pool de PHP-FPM. |
| `deploy/php.ini.ejemplo` | Directivas de PHP para producción. |
| `deploy/respaldar.sh` | Respaldo de la base (y de `public/contenido`). |
| `deploy/desplegar.sh` | Despliegue guiado e idempotente (simula por defecto). |
| `deploy/verificar.sh` | Verificación posterior, solo lectura (tabla OK/FALLO). |

## 0. Qué se verificó y qué NO (leer primero)

El **ensayo de la Fase 16C se hizo el 2026-11-23** de punta a punta, en un equipo de desarrollo, con una pila lo más parecida posible a producción
y **sin tocar ningún servidor ni base reales** (la «producción de ensayo» fue la base de sandbox `explora_migtest`).

| Verificado en el ensayo | Versiones |
|---|---|
| Apache + PHP-FPM reales (`httpd` con `mod_proxy_fcgi`; **no** `php -S`), PHP-FPM por TCP **y** por socket UNIX | macOS 13 (Darwin 22.6), httpd 2.4.68, PHP 8.2.31 (fpm-fcgi) |
| Entrega desde un `git clone` limpio, `composer install --no-dev --optimize-autoloader`, `APP_ENV=production`, `APP_DEBUG=false`, cachés `config`/`route`/`view`/`event` | Composer 2.10.0, Laravel 10.50.4 |
| Flujo completo: respaldo → auditoría → mantenimiento (503) → migraciones → cachés → quitar mantenimiento → externalizar imágenes → `FICHAS_MEMORIA=128M` → verificación → marcha atrás (`--revertir`, `migrate:rollback`, restauración del volcado) | MySQL 8.0.41 |
| **Primer despliegue** sobre una base con el esquema original (39 tablas, sin tabla `migrations`) y **actualización** sobre una base ya migrada, con `deploy/desplegar.sh` real | |
| HTTPS con **certificado autofirmado** (`mod_ssl`): redirección 80→443, cookie `Secure`, vhost por defecto, mitigación opcional del chat | |
| `verificar.sh` contra HTTP y HTTPS (104 comprobaciones, 0 fallos); Playwright (`bloqueos`, `recursos`, `mapas`, `sesion-ficha-quiz`: 66 de 70 pasan; las 4 diferencias están explicadas en el anexo B) | Playwright 1.60.0, Chromium |
| Las 33 fichas de departamento responden 200 con `FICHAS_MEMORIA=128M` y `memory_limit = 128M` tras externalizar | |

| **NO verificado** (hágalo en su servidor y anótelo) | Por qué importa |
|---|---|
| **Linux** (Debian/Ubuntu/RHEL: rutas, `a2enmod`, `systemd`, usuario `www-data`, `user`/`group` del pool, SELinux/AppArmor) | El ensayo corrió en macOS con un usuario normal. |
| **nginx**, **`mod_php`**, `ProxyPassMatch` | No se probaron. Las plantillas son solo Apache + PHP-FPM. |
| **Certificado real** (Let's Encrypt u otro), HSTS, renovación | Solo se probó con un autofirmado local. |
| **Usuarios MySQL de mínimo privilegio** (los `GRANT` de §4) | No se pudo crear usuarios en el MySQL del ensayo; salen del análisis del código (qué escribe la aplicación). Pruébelos en su servidor. |
| **OPcache**, logrotate, cron de limpieza, `LOG_CHANNEL=daily` | Sin OPcache en el PHP del ensayo; el resto son prácticas estándar no ejercitadas. |
| **La base y el servidor reales**, sus datos, su `max_allowed_packet`, su binlog, su carga | Nada se desplegó en producción. |
| Navegadores distintos de Chromium; carga concurrente real | |

## 1. Requisitos

| Componente | Versión / detalle |
|---|---|
| Sistema | Linux con Apache 2.4 (el ensayo fue en macOS; ver §0). |
| Apache | 2.4 con los módulos `rewrite`, `headers`, `setenvif`, `deflate`, `filter`, `mime`, `dir`, `alias`, `authz_core`, `proxy`, `proxy_fcgi`, `ssl`, `unixd`, `log_config`. Opcional: `expires`. Debian/Ubuntu: `a2enmod rewrite headers setenvif deflate filter proxy proxy_fcgi ssl`. |
| PHP | **≥ 8.1** (probado con **8.2.31**) como **PHP-FPM**, con las extensiones `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `json`, y `mysqli` + `curl` solo si se conserva el chat legado. |
| Composer | 2.x (solo en el servidor de despliegue; `composer install --no-dev`). |
| MySQL | 8.0 (probado con 8.0.41). Dos bases: `explora` (contenido y resultados) y `pedigital` (usuarios, solo lectura). `max_allowed_packet` ≥ 64 MB (el valor por defecto de MySQL 8; las filas de contenido con imágenes base64 llegan a ~6 MB). |
| Git | Para clonar el código (alternativa: `git archive`). |
| Node | **No hace falta** en el servidor (el sitio no se compila; `npm`/Playwright son solo de desarrollo). |
| Espacio en disco | Código + `vendor/` ≈ **330 MB** (290 MB de `public/` + 36 MB de `vendor/`, sin `.git`; el `.git` de un clon completo suma otros ≈ 450 MB). `public/contenido/` tras externalizar: **≈ 265 MB** (4.009 archivos). Respaldo comprimido de la base: **≈ 280 MB** (las imágenes base64 casi no comprimen). **Reserve ≥ 1,5 GB libres** para el despliegue y al menos otros 300 MB por cada respaldo que conserve. |
| **Binlog de MySQL** | Si `log_bin` está activo (lo está por defecto en MySQL 8), cada `UPDATE` de la externalización pasa por el binlog: puede crecer ≈ 700 MB. Compruebe `SHOW VARIABLES LIKE 'log_bin%'` y el espacio de `/var/lib/mysql` **antes** de externalizar. Además, InnoDB **no devuelve** al disco el espacio de las imágenes quitadas (la base sigue ocupando ≈ 394 MB tras externalizar); `OPTIMIZE TABLE` lo recuperaría pero bloquea cada tabla mientras trabaja (no se probó). |

## 2. Arquitectura final

```
                      Internet
                         │  :80  → 301 a https://dominio/      (vhost HTTP)
                         ▼  :443 (TLS)
          ┌──────────────────────────────────────────────┐
          │ Apache 2.4  (vhost por defecto: 403 a Hosts ajenos)│
          │ DocumentRoot = <raíz>/public    AllowOverride All  │
          │  ├─ estáticos (css, js, img, json, /contenido/…)   │──► sirve Apache directamente
          │  ├─ public/.htaccess: bloqueos + cabeceras + front controller
          │  └─ *.php existentes ──► SetHandler proxy:fcgi ─┐  │
          └───────────────────────────────────────────────┼──┘
                                                          ▼  127.0.0.1:9000 (o socket UNIX)
                                              ┌──────────────────────┐
                                              │ PHP-FPM (pool explora)│
                                              │ Laravel 10 (index.php)│
                                              └──────────┬───────────┘
                         MySQL 8 ◄────────────────────────┘
                         ├─ explora   (SELECT de contenido; INSERT resultados; perfiles_explora)
                         └─ pedigital (SELECT de users; SOLO lectura)

  Fuera del docroot (no alcanzables por HTTP): .env, vendor/, storage/, bootstrap/cache/, app/, config/, database/, tests/, DOCUMENTACION_MEJORAMIENTO/, deploy/
  Se escribe en: storage/**, bootstrap/cache/, public/contenido/   (y nada más)
```

Las rutas dinámicas (`/`, `/perfil`, `/climatico`, `/api/…`, los 410 de los PHP retirados…) las atiende Laravel por el front controller de `public/.htaccess`.
El **chat legado** (`public/mapplic/php/chat.php` y `chat copy.php`) es el único PHP «suelto» que Apache ejecuta (ver §10).

## 3. Preparar el servidor

1. **Usuarios.** Un usuario de despliegue (p. ej. `deploy`, dueño del código) y el usuario del servidor web (`www-data` en Debian/Ubuntu, `apache` en RHEL), que será el de PHP-FPM.
2. **Código desde un checkout limpio** (nunca copiando un árbol de trabajo: allí puede haber `public/ViajaPED/node_modules`, 22 MB que Apache serviría tal cual):
   ```bash
   sudo mkdir -p /var/www && sudo chown deploy: /var/www
   git clone --branch main <URL-del-repositorio> /var/www/explora
   cd /var/www/explora
   ```
   Para actualizar después: `git fetch --tags && git checkout <etiqueta-o-rama>`; `.env`, `storage/`, `bootstrap/cache/` y `public/contenido/` están en `.gitignore` y **se conservan**.
3. **Dependencias de producción:** `composer install --no-dev --optimize-autoloader --no-interaction` (≈ 11 s en el ensayo; solo paquetes estándar; avisa de 5 incidencias de seguridad de Laravel 10 aceptadas, ver §10). Ninguna dependencia de desarrollo la usa el código de producción: `php artisan about`, `route:list` y todas las páginas funcionan tras instalar sin `--dev`.
4. **Configuración de PHP-FPM y de PHP:** copie `deploy/php-fpm-pool.conf.ejemplo` a `/etc/php/8.2/fpm/pool.d/explora.conf` (ajuste `user`, `group`, `listen`, `open_basedir`, rutas de registro) y `deploy/php.ini.ejemplo` a `/etc/php/8.2/fpm/conf.d/90-explora.ini`. Compruebe y recargue: `php-fpm8.2 -t && systemctl reload php8.2-fpm`.
   - `expose_php = Off`, `display_errors = Off`, `cgi.fix_pathinfo = 0` y `memory_limit = 128M` son los valores ensayados. **No** fije `memory_limit` con `php_admin_value` en el pool: la ruta de la ficha sube su límite con `ini_set` (`FICHAS_MEMORIA`) y `php_admin_value` lo impediría.
5. **Apache:** copie `deploy/apache-vhost.conf.ejemplo` a `/etc/apache2/sites-available/explora.conf`, sustituya `explora.example.org`, las rutas `/var/www/explora` y los certificados, y actívelo **el primero** (el vhost por defecto debe cargarse antes que el del sitio: p. ej. `000-explora.conf`). Después `apachectl configtest && systemctl reload apache2`.
   - El `DocumentRoot` es **`<raíz>/public`** y `AllowOverride All` solo ahí.
   - La plantilla incluye `ServerTokens Prod`, `TraceEnable off`, `<FilesMatch "^\.ht">` denegado y compresión `mod_deflate` de texto/JSON.
6. **HTTPS:** certificado válido para el dominio (p. ej. con certbot; no verificado aquí). Active HSTS solo cuando HTTPS sea estable (§10).
7. **`.env`:** `cp deploy/env.produccion.ejemplo .env`, rellene todo lo marcado `CAMBIAR`, `php artisan key:generate --force` (**una sola vez**: regenerarla cierra todas las sesiones) y protéjalo: `chmod 640 .env && sudo chgrp www-data .env`.

### `.env` de producción, comentado

La plantilla completa y comentada está en `deploy/env.produccion.ejemplo`; lo imprescindible:

| Variable | Valor en producción | Por qué |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | Con `local` se aceptan hosts de desarrollo; con `APP_DEBUG=true` se muestran trazas. |
| `APP_KEY` | generada con `key:generate --force` | Cifra sesiones y cookies. |
| `APP_URL` | `https://su-dominio` | Es el **único `Host`** que acepta `TrustHosts` (otro da 400). |
| `TRUSTED_HOSTS` | alias separados por comas (`www.su-dominio`), o vacío | Nombres exactos, sin esquema ni puerto. |
| `SESSION_SECURE_COOKIE` | **`true`** con HTTPS | Con `false` la cookie de sesión viaja también por HTTP. El ensayo en HTTP usó `false` solo por eso; con HTTPS (ensayado) la cookie sale con `secure; httponly; samesite=lax`. |
| `SESSION_DRIVER` / `CACHE_DRIVER` | `file` / `file` | Probado. Nunca `array` en producción. |
| `DB_*` | base `explora`, usuario de la aplicación (§4) | |
| `PEDIGITAL_DB_*` | base `pedigital`, usuario **solo SELECT** (§4) | |
| `FICHAS_MEMORIA` | `512M` al desplegar; `128M` tras externalizar y verificar | Con imágenes base64 la ficha del depto 10 pesa 48 MB. |
| `API_POR_MINUTO` / `RESULTADOS_POR_MINUTO` | `300` / `10` o lo que necesite el colegio | Las aulas comparten IP por NAT: súbalos si ve 429. |
| `LOG_LEVEL` | `warning` | |
| Contraseñas con `#`, espacios o `$` | entre **comillas simples** | Comprobado: sin comillas `ab#cd` se corta a `ab` en silencio y un espacio impide leer todo el `.env`. |

> **Regla de oro:** con `config:cache` activo Laravel **ignora el `.env` y las variables de entorno**. Tras cambiar el `.env` hay que repetir `php artisan config:cache`; y para ejecutar un comando con otro usuario de MySQL por variables de entorno, la caché debe estar vacía (`php artisan config:clear`) — comprobado en el ensayo.

## 4. Usuarios MySQL de mínimo privilegio y rotación de credenciales

> Los `GRANT` salen del análisis del código (qué escribe cada ruta: solo `INSERT` en `resultado_juegos` y `INSERT`/`UPDATE` en `perfiles_explora`; `grep` de `insert/update/delete/save/create` en `app/`) y **no se pudieron ejecutar en el ensayo**. Pruébelos en su servidor con la aplicación en marcha (login, guardar una partida, cambiar avatar).

Cree **tres** usuarios con contraseñas largas y distintas (sustituya `<…>`; las contraseñas **nunca** se escriben en el repositorio ni en un chat):

```sql
-- 1) Usuario de la aplicación (el del .env: DB_USERNAME).
CREATE USER 'explora_app'@'localhost' IDENTIFIED BY '<clave-1>';
GRANT SELECT ON explora.* TO 'explora_app'@'localhost';
GRANT INSERT ON explora.resultado_juegos TO 'explora_app'@'localhost';
-- perfiles_explora la crea la migración: ejecute estos dos GRANT DESPUÉS del primer `migrate`.
GRANT INSERT, UPDATE ON explora.perfiles_explora TO 'explora_app'@'localhost';

-- 2) Lectura de usuarios (PEDIGITAL_DB_USERNAME): SOLO SELECT sobre la base de usuarios.
CREATE USER 'explora_pedigital'@'localhost' IDENTIFIED BY '<clave-2>';
GRANT SELECT ON pedigital.* TO 'explora_pedigital'@'localhost';       -- o solo pedigital.users si no se usa otra tabla

-- 3) Usuario con permisos de esquema/contenido: SOLO para migrar, externalizar imágenes y respaldar. No va en el .env.
CREATE USER 'explora_ddl'@'localhost' IDENTIFIED BY '<clave-3>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES, TRIGGER, SHOW VIEW
    ON explora.* TO 'explora_ddl'@'localhost';
```

- El usuario 3 se pasa a `desplegar.sh` por **variables de entorno** (`export DDL_DB_USERNAME=explora_ddl DDL_DB_PASSWORD=…`), nunca por argumento (se vería con `ps`). Si no lo define, el script usa el del `.env` (que, con los `GRANT` de arriba, **no** podría migrar).
- `respaldar.sh` necesita `SELECT`, `SHOW VIEW` y `TRIGGER`: sirve el usuario 3 o uno de solo lectura con esos tres permisos. Su contraseña va en un archivo `chmod 600` (`MYSQL_DEFAULTS_FILE`), no en la línea de comandos.

**Rotación de credenciales (checklist; sin valores).** Las contraseñas antiguas de MySQL están escritas en `chat.php`/`chat copy.php` **y en el historial de git** (también en la etiqueta `pre-retiro-legado`): borrar archivos no las invalida.
- [ ] Crear los tres usuarios nuevos con contraseñas nuevas y únicas.
- [ ] Poner las nuevas en el `.env` de producción (`DB_*`, `PEDIGITAL_DB_*`) y repetir `php artisan config:cache`.
- [ ] Cambiar la contraseña del usuario/`root` antiguo cuyas credenciales están en el código, o retirarlo.
- [ ] Si se conserva el chat: pasar sus credenciales a un usuario de solo lectura (`SELECT` sobre `explora`) y sacarlas del archivo (variables de entorno del pool de PHP-FPM, que usa `clear_env = yes`).
- [ ] Decidir si se limpia el historial de git (decisión del responsable; no se ha hecho).
- [ ] Verificar: `mysql -u <usuario-antiguo> -p` ya no entra; la aplicación sigue funcionando con los nuevos.

## 5. Procedimiento paso a paso

**Orden crítico:** respaldo → auditoría → mantenimiento → migrar → código/cachés → quitar mantenimiento → externalizar imágenes → `FICHAS_MEMORIA`.
La razón: el código nuevo **escribe** `resultado_juegos.creado_en`, así que las migraciones van **antes** de poner el código en servicio (el código anterior sigue funcionando con ellas aplicadas), y la externalización va **después** porque el código con `FICHAS_MEMORIA=512M` funciona con la base antes y después del cambio.

Los tiempos son los **medidos en el ensayo** (MySQL local, 4.009 imágenes; en producción espere entre 1 y 5 minutos para la externalización según disco y red).

### 5.0 Atajo: `deploy/desplegar.sh`

El script hace los pasos 5.3–5.8 con comprobaciones y confirmaciones. **Por defecto solo simula.**

```bash
cd /var/www/explora
deploy/desplegar.sh                                   # SIMULA: requisitos y lista de lo que haría (no cambia nada)
deploy/desplegar.sh --aplicar --grupo-web www-data    # composer --no-dev, cachés y permisos (no destructivo)
export DDL_DB_USERNAME=explora_ddl DDL_DB_PASSWORD='…'   # (sin escribirlo en el historial: use `read -s`)
deploy/desplegar.sh --aplicar --migrar --respaldo /ruta/respaldo.sql.gz --grupo-web www-data
deploy/desplegar.sh --aplicar --externalizar --respaldo /ruta/respaldo.sql.gz --grupo-web www-data
```

`--migrar` y `--externalizar` exigen `--respaldo` (de menos de 24 h y no vacío) y que escriba **el nombre de la base** para confirmar. Si algo falla con el sitio en mantenimiento, el script **no lo reactiva**: avisa y deja la decisión a la persona (§8). Ejercitado en el ensayo en todos sus modos (simular, aplicar, migrar, externalizar, confirmación errónea, sin respaldo, respaldo vacío, usuario DDL sin permisos, repetición idempotente).

### 5.1 Respaldo (≈ 48 s; 277 MB comprimidos)

```bash
cat > ~/respaldo.cnf <<'EOF'
[client]
user=explora_ddl
password=<clave-3>
host=127.0.0.1
EOF
chmod 600 ~/respaldo.cnf
MYSQL_DEFAULTS_FILE=~/respaldo.cnf deploy/respaldar.sh --base explora --destino /var/respaldos/explora --con-contenido
```
Salida esperada: `OK  Base: /var/respaldos/explora/explora-explora-AAAAMMDD-HHMMSS.sql.gz (39 tablas, 264 MB comprimido, 48 s)`. El script comprueba espacio libre, que el volcado no esté vacío, que el gzip sea íntegro y que termine en «Dump completed». El destino **no puede estar dentro de `public/`** y debe quedar fuera del servidor web. **Pruebe a restaurar el respaldo alguna vez en una base de ensayo** (en el ensayo: 22 s).
Si falla (`Access denied`, base inexistente, disco lleno), el script borra el archivo parcial y sale con código 1: no continúe.

### 5.2 Entregar el código (sin ponerlo en servicio) y crear la configuración

`git fetch --tags && git checkout <versión>`, `composer install --no-dev --optimize-autoloader --no-interaction`, `.env` listo (§3). Después **borre las cachés antiguas**: `php artisan optimize:clear` (una config cacheada de la versión anterior no conoce las claves nuevas e ignora las variables de entorno).

### 5.3 Auditoría previa (solo lectura, < 1 s)

```bash
php artisan explora:auditar-integridad --json > /var/respaldos/explora/auditoria-antes.json
php artisan explora:auditar-integridad | tail -20
```
Salida esperada con los datos actuales: **código 2** y `[BLOQUEANTE] opciones_verfal.pregunta → preguntas.id: 4 fila(s) huérfana(s); ids: 223, 277, 278, 279` más ≈ 10-17 avisos (13 FK de `regiones_*` autorreferenciadas, duplicados 1:1, colaciones mezcladas, índices ausentes si aún no migró). **El código 2 no impide migrar.** Lo que sí importa: que `departamento_descripcion.departamento → list_departamentos.id` **no** tenga huérfanas (si las tiene, la FK no se crea y la migración avisa) y revisar las filas de `resultado_juegos.fecha` «no interpretables».

### 5.4 Modo mantenimiento (comprobar el 503)

```bash
php artisan down --retry=60 --secret=<texto-aleatorio-largo>
curl -s -o /dev/null -w '%{http_code}\n' https://su-dominio/            # 503 (cabecera Retry-After: 60)
curl -s -o /dev/null -w '%{http_code}\n' https://su-dominio/api/sesion  # 503
```
`https://su-dominio/<secreto>` deja entrar a quien lo conozca (redirige con una cookie). Los archivos estáticos siguen sirviéndose (200): es esperable.

### 5.5 Migrar (≈ 0,5 s cada una con los datos reales)

> **Siempre con `--path`, una a una. NUNCA `php artisan migrate` a secas:** Laravel Sanctum trae en `vendor/` la migración `2019_12_14_000001_create_personal_access_tokens_table`, que `migrate:status` muestra como *Pending* y que `migrate` crearía (tabla que Explora no usa). Es normal verla «Pending»: se deja así.
> `migrate --pretend` **no es informativo** con estas migraciones (usan comprobaciones previas y muestran «tabla no existe»): revise su código o ensáyelas en una copia.

```bash
# Con usuario de esquema (config sin cachear): variables SOLO para este comando.
export DB_USERNAME=explora_ddl DB_PASSWORD='…'     # o use desplegar.sh --migrar, que lo hace con DDL_DB_USERNAME
php artisan migrate:status --no-ansi
for m in 2026_11_18_000001_baseline_esquema_explora 2026_11_18_000002_crear_perfiles_explora \
         2026_11_21_000003_ampliar_resultado_juegos_creado_en_e_indices 2026_11_21_000004_agregar_indices_y_fk_de_integridad; do
  php artisan migrate --force --path=database/migrations/$m.php
done
unset DB_USERNAME DB_PASSWORD
```
- **Primer despliegue** (la base original tiene 39 tablas y **no** tiene tabla `migrations`): `migrate:status` responde «Migration table not found» y las **cuatro** migraciones están pendientes; la `baseline` es idempotente (no toca las tablas existentes), `crear_perfiles_explora` crea `perfiles_explora`. Salida: `Creating migration table … DONE`, y las cuatro `DONE`. **Ahora** ejecute los dos `GRANT` sobre `perfiles_explora` de §4.
- **Actualización** (solo las dos de la Fase 16B): `…000003 AVISO: resultado_juegos.creado_en: 5 filas rellenadas, 0 con fecha no interpretable` (≈ 0,1 s) y `…000004 … DONE` (≈ 0,3 s). La 000004 muestra un AVISO si no pudo crear la FK. Con 200.000 filas sintéticas tardó ≈ 22-24 s.
- Verifique: `php artisan explora:auditar-integridad` otra vez: los 5 índices y la FK deben figurar «presente».

### 5.6 Poner el código en servicio y generar las cachés (≈ 1,5 s)

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```
(o `php artisan optimize` + `view:cache`; Laravel solo admite UN comando por llamada). Ejecútelo **sin** `DB_USERNAME`/`DB_PASSWORD` en el entorno: `config.php` guarda lo que haya en ese momento.
Permisos (el usuario de despliegue es el dueño; el del servidor web, el grupo):
```bash
sudo chgrp -R www-data storage bootstrap/cache .env public/contenido 2>/dev/null
chmod -R ug+rwX storage bootstrap/cache && chmod -R o-rwx storage bootstrap/cache   # config.php contiene las contraseñas de MySQL
find storage bootstrap/cache -type d -exec chmod g+s {} +
chmod 640 .env
```
**Escribibles por el usuario del servidor web:** `storage/**`, `bootstrap/cache/` y `public/contenido/`. **Nada más** (código, `vendor/`, `.env`: solo lectura para él). Limpiar cachés: `php artisan optimize:clear`; es seguro con el sitio en marcha (Laravel recompone lo que falte).

### 5.7 Quitar el mantenimiento

```bash
php artisan up        # «Application is now live.»
deploy/verificar.sh https://su-dominio        # §6
```
Y guarde un resultado desde la web (iniciar sesión, jugar una partida): `fecha` y `creado_en` deben quedar escritos (comprobado en el ensayo: `23-11-2026 21:31:44` / `2026-11-23 21:31:44`).

### 5.8 Externalizar las imágenes de las fichas (Fase 16A)

Con el sitio en marcha (cada fila se bloquea solo mientras se actualiza), **respaldo hecho** y espacio comprobado (§1, binlog):
```bash
php artisan explora:externalizar-imagenes                       # simulación (17 s): no escribe nada
```
Cifras esperadas con los datos actuales (**compruébelas; si difieren, pare y averigüe**): `1703 fila(s), 4128 imagen(es), 348,7 MB de base64`, `4009 archivos (256,7 MB); duplicadas 119`, tipos `{"gif":1941,"jpg":3,"png":2184}`, `Filas omitidas por anomalía: 0`.
```bash
php artisan explora:externalizar-imagenes --aplicar --base-real --confirmo-respaldo     # 31 s; memoria pico 69 MB
php artisan explora:externalizar-imagenes --verificar=storage/logs/externalizar-imagenes-<fecha>.json   # 4 s
php artisan explora:externalizar-imagenes | tail -4           # segunda simulación: «0 fila(s)»
```
- `--base-real --confirmo-respaldo` (las **dos** juntas) son obligatorias porque la base no termina en `_test`/`_migtest`; sin ellas la guarda se niega (comprobado también con una base de nombre ajeno y con cada bandera por separado). Con ellas usted declara que el respaldo existe.
- Salida esperada de la verificación: `1703 fila(s), 4128 referencia(s), 4009 archivo(s) distintos comprobados; problemas: 0.` (código de salida 0). El informe JSON (≈ 1,3 MB) queda en `storage/logs/`; su nombre usa la hora **UTC**.
- La carpeta `public/contenido/` (265 MB) **no se versiona y no debe borrarse** en los despliegues siguientes (excluirla de `rsync --delete`); entra en las copias de seguridad junto con la base. Revíselo si ve anomalías (base64 inválido, SVG): esas filas quedan sin tocar.
- Con `desplegar.sh --externalizar` el comando completo (simular, confirmar, aplicar, verificar) va guiado.

### 5.9 Bajar la memoria de las fichas

Solo tras verificar el paso anterior: `FICHAS_MEMORIA=128M` en `.env` y `php artisan config:cache`. Comprobado: las 33 fichas dan 200 con `memory_limit = 128M`; la ficha 10 baja de **48,6 MB a 0,26 MB** (49 KB con gzip) y la 14 de 27,9 MB a 0,5 MB (98 KB con gzip). **No lo haga antes** y recuerde subirlo otra vez a `512M` si revierte las imágenes (§7.3): con 128M y las imágenes en base64, la ficha del depto 10 da **500** (`Allowed memory size exhausted`).

## 6. Verificación posterior

`deploy/verificar.sh https://su-dominio` (solo lectura; añada `--insecure` con un certificado autofirmado; ≈ 28 s y ≈ 104 comprobaciones). Sale con código 1 si algo falla (los AVISO no cuentan) y con 2 si no puede conectar. Resultados esperados (los obtenidos en el ensayo con Apache + PHP-FPM):

| Petición | Esperado | Notas |
|---|---|---|
| `GET /`, `/perfil`, `/climatico`, `/coordilleras`, `/etnias`, `/parques_naturales`, `/vertientes`, `/juegos/`, `/juegos/juegos/puzzle/`, `/lineaTiempo/`, `/ViajaPED/` | 200 | `/` sin `Set-Cookie`; `/perfil` con `explora_session` (`httponly`, `samesite=lax`, y `secure` con HTTPS). |
| `GET /juegos`, `/lineaTiempo`, `/juegos/juegos/puzzle` | 301 | A la forma con barra. |
| `GET /index.html`, `/climatico.html`, `/miPerfil.html`, `/juegos/index.html`, `/lineaTiempo/index.html` | 301 a la ruta nueva | La query se conserva (`/climatico.html?x=1&y=2` → `/climatico?x=1&y=2`). |
| `GET /api/sesion` | 401 JSON | Llega a Laravel por PHP-FPM. `/api/historial` 401; `/api/nada` 404; `GET /api/resultados` 405. |
| `GET /api/departamentos/10/ficha` | 200, **≈ 0,26 MB** | `gzip`: 49 KB. Sin externalizar: 48,6 MB. |
| Los 7 servicios retirados (`/mapplic/php/{iniciarSesion,cambiarAvatar,consultarInfo,buscarPreguntas}.php`, `/juegos/php/{guardar_resultado,consultar_ranking,consultar_historial}.php`) y `POST` vacío | **410** | Lo emite Laravel: si PHP-FPM falla darían 500. |
| `/composer.json`, `/artisan`, `/.env`, `/.git/HEAD`, `/storage/…`, `/vendor/…`, `/bootstrap/cache/…`, `/app/…`, `/config/…`, `/database`, `/tests`, `/DOCUMENTACION_MEJORAMIENTO` | 404 | No existen bajo el docroot. |
| `/composer.lock`, `/README.md`, `x.sql`, `x.map`, `copia.bak`, **`/.htaccess`**, `/.htpasswd`, `/mapplic/php/conexion.php` | 403 | `FilesMatch` de `public/.htaccess`. |
| `/css/`, `/js/`, `/miPerfil/`, `/mapplic/php/`, `/contenido/`, `/contenido/fichas/` | 403 o 404 | Sin listado de directorios (Apache+FPM da 404). |
| `/contenido/x.html` | 404 | Solo png/jpg/gif/webp bajo `/contenido/`. |
| `/index.php/x`, `/index.php/api/sesion` | 404 / 403 | **No** se ejecutan como ruta de Laravel. |
| `TRACE /` | 405 | `TraceEnable off`. `POST /` 405; `DELETE /api/departamentos/10/ficha` 405; `HEAD /` 200. |
| Cabeceras en `/`, en un estático (`/js/explora-api.js`) y en un 404 | `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin` | **Sin `X-Powered-By`** y `Server: Apache` sin versión. |
| `Host: otro.example` en `/`, `/api/sesion` | 403 (vhost por defecto) | Sin vhost por defecto: 400 de Laravel en páginas y API, **pero los estáticos se sirven con cualquier Host (200)**. |
| Imagen `/contenido/fichas/xx/<sha256>.png` | 200, `Content-Type: image/png`, `Cache-Control: public, max-age=31536000, immutable` | |
| Tipos: `.js` `text/javascript`, `.json` `application/json`, `.png`/`.jpg`/`.webp`/`.svg`, `.mp3` `audio/mpeg`, `.woff2` `font/woff2`, `.mp4` `video/mp4` | correctos | |
| `Accept-Encoding: gzip` en JS, JSON y la ficha | `Content-Encoding: gzip` | Con `mod_deflate` de la plantilla. |
| `GET /mapplic/php/chat.php` | **200** (AVISO) | El chat se ejecuta sin protección (§10). 403 solo si aplica la mitigación. |

**Compresión (medida con el sitio real).** Con `mod_deflate` sobre `text/*`, JS, JSON y SVG: ficha 10 externalizada 264 KB → 49 KB, ficha 14 508 KB → 98 KB, `/` 44 KB → 6 KB, `eventos.json` 16 KB → 5 KB. Las imágenes ya están comprimidas (no se incluyen). **Sin externalizar** el gzip casi no ayuda (48,6 MB → 36,5 MB y 3,4 s: la base64 no comprime), otra razón para externalizar. `ViajaPED/data.json` (6,7 MB → 5,0 MB) lleva datos incrustados; mejoraría con minificado/externalizado, pero queda fuera de esta guía.

Además, recorra a mano en un navegador: portada, el mapa con una ficha (Cauca, depto 10, y otra), el visor de imágenes ampliadas, iniciar sesión → quiz → un juego → perfil.

## 7. Marcha atrás por escenarios

Todos se ensayaron en el sandbox salvo donde se indique.

### 7.1 Falla la migración (el sitio sigue en mantenimiento)
1. **No** ponga el código en servicio. `desplegar.sh` deja el sitio en mantenimiento y lo avisa; usted decide.
2. Mire el error. Si fue de permisos (`Access denied … to database`, ensayado) corrija el `GRANT` y repita: las migraciones son idempotentes (las ya aplicadas se omiten).
3. Para deshacer lo aplicado: `php artisan migrate:rollback --force --step=2` (las dos de la 16B, 0,6 s; quitan la FK, los índices y la columna `creado_en`; `fecha` conserva el dato). En un primer despliegue, `--step=4` solo si **no** hay datos en `perfiles_explora` (`down()` solo la borra si está vacía) y no use `rollback` sin `--step`/`--path`.
4. Restauración total (la forma exacta): `gzip -dc respaldo.sql.gz | mysql -u explora_ddl -p explora` (22 s en el ensayo, tras recrear la base vacía).
5. `php artisan up`.

### 7.2 El código nuevo falla (tras `up`)
1. Vuelva al código anterior: `git checkout <etiqueta-anterior>` (la de la migración original es `pre-migracion-laravel`), `composer install --no-dev --optimize-autoloader` y `php artisan optimize:clear && php artisan config:cache && php artisan route:cache`. El código **anterior** funciona con las migraciones ya aplicadas (la columna `creado_en` admite NULL).
2. El código **nuevo** NO funciona con las migraciones deshechas (guardar una partida fallaría): si revierte las migraciones, revierta también el código.
3. Si hace falta, `php artisan down` mientras tanto.

### 7.3 Falla o no gusta la externalización de imágenes
1. **Revertir (21 s; memoria pico 105 MB):** `php artisan explora:externalizar-imagenes --revertir --base-real --confirmo-respaldo`. Vuelve a incrustar en base64 cada imagen leyendo su archivo (comprueba el sha256; una fila cuyo archivo falte o esté alterado no se toca y se reporta). El resultado es **funcionalmente equivalente, no idéntico byte a byte** (el MIME declarado pasa a ser el real).
2. **Obligatorio justo después:** `FICHAS_MEMORIA=512M` en `.env` + `php artisan config:cache` (si estaba en `128M`, la ficha 10 da 500; comprobado). Verifique: ficha 10 vuelve a pesar ≈ 48,6 MB y da 200; la 14, 27,9 MB.
3. Los archivos de `public/contenido/` **no** se borran; tras confirmar que todo está bien pueden eliminarse a mano.
4. La marcha atrás **exacta** es restaurar el volcado del paso 5.1 (las URL desaparecen de la base y los archivos sobran sin dañar nada). Si hubo escrituras en las tablas de contenido entre el volcado y la restauración, se perderían: por eso conviene hacerlo en una ventana tranquila.
5. Si falla a mitad: el comando es **idempotente** y cada fila es una transacción; repítalo y revise el informe.

### 7.4 Falla todo
Restaure el volcado (7.1.4), vuelva al código anterior (7.2) y `php artisan up`.

## 8. Operación diaria

- **Registros:** `storage/logs/laravel.log` (errores de la aplicación; `LOG_LEVEL=warning`), registros de Apache (`ErrorLog`/`CustomLog` de la plantilla) y de PHP-FPM (`php_admin_value[error_log]` del pool). Rótelos con `logrotate` (Laravel no rota `laravel.log` con `LOG_CHANNEL=stack`/`single`; `LOG_CHANNEL=daily` conserva 14 días por defecto — estándar de Laravel, no ejercitado aquí). Los informes de la externalización (`storage/logs/externalizar-imagenes-*.json`, ≈ 1,3 MB) pueden archivarse.
- **Sesiones y caché de archivos:** solo `/perfil`, `/juegos` y la API crean sesión (en `storage/framework/sessions`). Laravel 10 no tiene `session:gc`; limpie con cron: `find /var/www/explora/storage/framework/sessions -type f -mmin +1440 -delete` (no ejercitado).
- **Después de cada despliegue:** recargar PHP-FPM si usa OPcache con `validate_timestamps=0` (`systemctl reload php8.2-fpm`).
- **Respaldos:** `deploy/respaldar.sh` por cron (diario, de noche) y copie `public/contenido/` (con `--con-contenido`) fuera del servidor. Pruebe la restauración cada cierto tiempo.
- **Límites 429:** si un colegio ve «demasiadas peticiones», suba `API_POR_MINUTO` / `RESULTADOS_POR_MINUTO` y repita `config:cache`.
- **Actualizar** = checkout de la nueva versión + §5.2–§5.7 (`deploy/desplegar.sh`); solo repita §5.8 si hay imágenes base64 nuevas (el comando es idempotente).

## 9. Qué cambia respecto a `php -S` (ensayo)

Las diferencias entre el servidor de desarrollo (`servidor-desarrollo.php`) y Apache + PHP-FPM reales están en el anexo B; la única consecuencia operativa es que **Apache ejecuta el chat legado** y devuelve 403 (no 404) para `conexion*.php`.

## 10. Seguridad residual y decisiones pendientes del responsable

1. **Chat legado sin proteger.** Con PHP-FPM bajo Apache, `GET /mapplic/php/chat.php` y `chat%20copy.php` **se ejecutan** (200 JSON): sin sesión, sin límite de uso, con las credenciales de MySQL escritas en el código y llamadas a Ollama de hasta 120 s (consumo de CPU). Comprobado: sin parámetro responde `{"success":false,"mensaje":"Debe enviar el parámetro cadena."}`; con `?cadena=…` y sin Ollama, `{"success":false,"mensaje":"Error conectando con Ollama: Failed to connect to localhost port 11434 …"}`, o sea **filtra un mensaje interno** (host y puerto). El camino siguiente (conexión a MySQL) devolvería `Error conexión MySQL: <mensaje de mysqli>`, que incluye el usuario y el host: **por código, NO ejecutado** (no se le dieron credenciales reales). **Mitigación verificada** (rompe el chat; `mapplic/js/chat.js` los llama): descomentar el bloque `<Directory …/mapplic/php><FilesMatch "^chat.*\.php$">Require all denied` de la plantilla → 403 en ambos, el resto del sitio intacto. Alternativas: retirar los archivos, o limitarlos por IP/autenticación. **Decisión pendiente.**
2. **Credenciales en el historial de git** (y en la etiqueta `pre-retiro-legado`): rotar (§4); decidir si se limpia el historial.
3. **4 filas huérfanas** de `opciones_verfal` (ids 223 → pregunta 590, 277 → 719, 278 → 720, 279 → 721): decide el dueño del contenido si se borran, reasignan o se recrean las preguntas; mientras tanto no se crea esa FK y la auditoría da código 2.
4. **Laravel 10 sin soporte de seguridad** y **5 avisos de seguridad** de Composer ignorados por decisión del responsable: planificar la actualización de versión.
5. **HSTS** (cuando HTTPS sea estable): `Header always set Strict-Transport-Security "max-age=31536000"` (empezar con un valor corto; `includeSubDomains` solo si todos los subdominios usan HTTPS). No se puso en la aplicación a propósito. En el ensayo con HTTPS el script lo marca como AVISO «pendiente de decisión».
6. **CSP:** no aplicada (el sitio usa `onclick=` y bloques `<script>` en línea). Empezar con `Content-Security-Policy-Report-Only` (`default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; frame-ancestors 'self'; object-src 'none'; base-uri 'self'`), revisar los informes y solo después activarla.
7. **`SESSION_SECURE_COOKIE=true` es obligatorio con HTTPS** (el valor por defecto de `config/session.php` es `null`).
8. Otras decisiones abiertas: FK en `preguntas.departamento` (polimórfica), `UNIQUE(departamento)` en las tablas 1:1 (hay duplicados), colaciones mezcladas, `OPTIMIZE TABLE` tras externalizar, OPcache, sitio en subdirectorio (no soportado), Sanctum (`personal_access_tokens`, no usada).

## 11. Lista de comprobación imprimible

**Antes del día del despliegue**
- [ ] Ventana de despliegue acordada y avisada. Respuesta del responsable sobre el chat (§10.1).
- [ ] Servidor con Apache 2.4 + PHP-FPM ≥ 8.1 (extensiones de §1) y MySQL 8; `a2enmod` de los módulos de §1.
- [ ] Espacio libre ≥ 1,5 GB (+ respaldo); `log_bin` y espacio del binlog revisados.
- [ ] Usuarios MySQL nuevos creados (§4) y credenciales antiguas con fecha de rotación.
- [ ] Certificado HTTPS válido instalado; DNS apuntando al servidor.
- [ ] Vhosts instalados (el **por defecto primero**), pool de PHP-FPM y `php.ini` aplicados; `apachectl configtest` y `php-fpm -t` correctos.
- [ ] `.env` de producción: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` propia, `APP_URL` https, `SESSION_SECURE_COOKIE=true`, `FICHAS_MEMORIA=512M`; permisos 640.
- [ ] Código desde un **checkout limpio** (sin `node_modules`, `.env` ni datos); `composer install --no-dev --optimize-autoloader` sin errores.

**El día**
- [ ] `deploy/desplegar.sh` (simulación) sin errores.
- [ ] Respaldo (`deploy/respaldar.sh --con-contenido`): archivo no vacío, «Dump completed», fuera del docroot. Guardado fuera del servidor.
- [ ] `php artisan explora:auditar-integridad`: código 2 solo por las 4 filas huérfanas conocidas; sin huérfanas en `departamento_descripcion`.
- [ ] `php artisan down` y 503 comprobado.
- [ ] Migraciones **una a una con `--path`** (nunca `migrate` a secas); `GRANT` sobre `perfiles_explora` en un primer despliegue.
- [ ] Auditoría posterior: 5 índices y FK «presente».
- [ ] `config:cache`, `route:cache`, `view:cache`, `event:cache` sin `DB_USERNAME`/`DB_PASSWORD` en el entorno; permisos (`storage`, `bootstrap/cache`, `public/contenido` escribibles por el servidor web; `o-rwx`).
- [ ] `php artisan up`. `deploy/verificar.sh https://dominio`: **0 fallos**.
- [ ] Guardar una partida y comprobar `fecha` y `creado_en`; navegador: portada, ficha del depto 10, quiz, juego, perfil.
- [ ] Externalizar: simulación con las cifras esperadas → `--aplicar --base-real --confirmo-respaldo` → `--verificar` con 0 problemas → segunda simulación con 0 filas.
- [ ] Ficha 10 ≈ 0,26 MB; imagen de `/contenido/…` con `immutable`.
- [ ] `FICHAS_MEMORIA=128M` + `config:cache`; las fichas siguen dando 200.
- [ ] `verificar.sh` otra vez: 0 fallos. Respaldo y contenido copiados fuera.

**Después**
- [ ] Credenciales antiguas rotadas (§4). Decisión sobre el chat aplicada o registrada.
- [ ] logrotate y cron de sesiones/respaldos configurados.
- [ ] Anotar fecha, versión (etiqueta/commit) y quién desplegó.

## Anexo A. Tiempos y cifras del ensayo (2026-11-23)

| Paso | Duración / resultado |
|---|---|
| `git clone` + `composer install --no-dev --optimize-autoloader` | 11 s; `vendor/` 36 MB; `public/` 290 MB |
| Volcado de `explora_test` (`mysqldump --single-transaction`) y restauración en el sandbox | 54 s / 22 s |
| `deploy/respaldar.sh` (base de 394 MB) | 48 s; 277 MB comprimidos |
| `explora:auditar-integridad` | 0,6 s (código 2: 4 huérfanas conocidas) |
| `down` → 503; `up` | inmediato; 503 con `Retry-After: 60`, el secreto redirige (302) |
| `migrate` 000003 / 000004 | 0,5 s / 0,6 s |
| Cachés `config`/`route`/`view`/`event` | 0,35 s cada una |
| `externalizar` simulación / aplicar / verificar / revertir | 17 s / 31 s / 4 s / 21 s (pico de memoria 69 MB / 69 MB / — / 105 MB) |
| `migrate:rollback --step=2` | 0,6 s |
| `desplegar.sh --aplicar --migrar` (primer despliegue, 4 migraciones) | 12 s en total |
| `desplegar.sh --aplicar --externalizar` | 62 s en total |
| `verificar.sh` | 28 s; 104 comprobaciones; 0 fallos |
| Fichas 10 / 14 antes → después | 48,6 MB → 0,26 MB / 27,9 MB → 0,51 MB |

## Anexo B. Diferencias entre `php -S` (router de desarrollo) y Apache + PHP-FPM

| Caso | `php -S` + `servidor-desarrollo.php` | Apache + PHP-FPM | Consecuencia |
|---|---|---|---|
| `GET /mapplic/php/chat.php` y `chat copy.php` | 404 (bloqueado) | **200**: se ejecuta | Decisión §10.1; `bloqueos.spec.js` falla 2 pruebas (esperan 404). |
| `GET /mapplic/php/conexion.php` y `conexion_pedigital.php` | 404 | **403** (`FilesMatch ^conexion.*\.php$`) | `bloqueos.spec.js` falla 2 pruebas (esperan 404); ambos son «no se sirve». |
| Carpetas sin índice (`/css/`, `/js/`, `/miPerfil/`, `/mapplic/php/`) | 404 | 404 con PHP-FPM (403 con Apache sin PHP) | Ninguno lista el contenido. |
| `GET /.htaccess` | 404 | **200** con una configuración mínima de Apache; 403 con la regla añadida a `public/.htaccess` (defecto corregido, Fase 16C) | Las configuraciones de distribución ya la traen. |
| `Host` ajeno | 400 (Laravel) | 403 con el vhost por defecto; sin él, 400 solo en páginas/API y los estáticos 200 | Use el vhost por defecto. |
| Compresión | ninguna | `mod_deflate` | La ficha pesa 49 KB con gzip. |
| `/contenido/…` | servido por el router | servido por Apache con `Cache-Control: immutable` (`mod_headers` + `mod_setenvif`) | Igual. |
| Cabeceras de seguridad, 410, 301 con query, `/api/*`, cookies | iguales | iguales | Sin diferencias. |

Las otras 66 pruebas de Playwright (66 de 70 con `EXPLORA_BASE_URL=http://127.0.0.1:<puerto>`) pasan contra Apache + PHP-FPM.

## Anexo C. Defectos encontrados y corregidos en el ensayo

1. `public/.htaccess` no negaba `.htaccess`/`.htpasswd` (con una configuración mínima de Apache `GET /.htaccess` daba 200): añadida `<FilesMatch "^\.ht">` (con prueba en `DespliegueArchivosTest`).
2. Plantilla de Apache: el vhost por defecto `*:80 *:443` con `SSLEngine on` activaba TLS también en el 80 (HTTP daba 400): ahora son dos vhosts. El comentario sobre la guarda `<If "-f …">` describía un efecto («Unable to open primary script») que no se reproduce: corregido.
3. `desplegar.sh`: borraba las cachés **después** de migrar (con la config vieja cacheada se ignoraba el usuario DDL y las claves nuevas), ponía el sitio en mantenimiento aunque no hubiera migraciones, no quitaba `o+r` a `bootstrap/cache/config.php` (contiene contraseñas) y no soportaba un usuario de esquema distinto: corregido.
4. La documentación decía que bastaba `php artisan migrate --force`: aplicaría también la migración de Sanctum (`personal_access_tokens`); ahora siempre `--path`.
5. La marcha atrás de la externalización omitía que, con `FICHAS_MEMORIA=128M`, la ficha 10 revertida da 500.
