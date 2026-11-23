#!/usr/bin/env bash
# =====================================================================================================================
# Explora · despliegue GUIADO e IDEMPOTENTE. Guía: DOCUMENTACION_MEJORAMIENTO/GUIA_DESPLIEGUE.md (léala antes de ejecutar nada).
#
# Se ejecuta DENTRO del servidor, sobre la carpeta (--raiz) que sirve Apache, con el código nuevo ya copiado (desde un checkout limpio) y el
# `.env` de producción ya creado. Por defecto NO CAMBIA NADA: es una SIMULACIÓN que comprueba requisitos y enseña los comandos.
#
#   (sin banderas)    --simular: requisitos + lista de lo que haría. Solo lee (migrate:status). No instala, no migra, no toca archivos.
#   --aplicar         Ejecuta los pasos NO destructivos: composer install --no-dev, permisos y cachés de Laravel.
#   --migrar          (con --aplicar) Además: auditoría, modo mantenimiento, migraciones PENDIENTES del repositorio (una a una, con
#                     --path: así NO se aplica la tabla personal_access_tokens que Sanctum trae en vendor/) y quitar el mantenimiento.
#                     DESTRUCTIVO para el esquema: exige --respaldo y que escriba el nombre de la base para confirmar.
#   --externalizar    (con --aplicar) Además: saca las imágenes base64 de las fichas a public/contenido (simulación, confirmación
#                     escribiendo el nombre de la base, aplicar y verificar el informe). Exige --respaldo.
#   --respaldo ARCH   Respaldo reciente (< 24 h) hecho con deploy/respaldar.sh. Obligatorio con --migrar y --externalizar.
#   --raiz DIR        Carpeta de la aplicación (por defecto la raíz de este repositorio).
#   --grupo-web GRP   Grupo del servidor web (www-data, apache...): recibe escritura en storage/, bootstrap/cache/ y public/contenido/.
#   --php BIN / --composer BIN   Binarios (por defecto `php` y `composer`).
#
# Usuario de MySQL con permisos de esquema/contenido (recomendado: el usuario de la aplicación solo tiene SELECT/INSERT/UPDATE de lo suyo):
#   export DDL_DB_USERNAME=...  DDL_DB_PASSWORD=...   (variables de entorno; nunca como argumento: se verían con `ps`). Solo se usan
#   para migrate y explora:externalizar-imagenes. La caché de configuración se borra antes y se vuelve a generar después SIN ellas,
#   porque con `config:cache` activo Laravel ignora las variables de entorno y `bootstrap/cache/config.php` guardaría esa contraseña.
#
# Qué NO hace nunca: git push/pull/commit, tocar bases sin --migrar/--externalizar, borrar datos, ni escribir credenciales. Si algo falla
# con el sitio en mantenimiento, NO lo reactiva solo: avisa y deja la decisión (marcha atrás) a la persona. Es idempotente: repetirlo
# solo aplica lo pendiente (las migraciones ya hechas y las imágenes ya externalizadas no se repiten).
# =====================================================================================================================
set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"
APLICAR=0; MIGRAR=0; EXTERNALIZAR=0; RESPALDO=""; GRUPO_WEB=""
EN_MANTENIMIENTO=0

fallo() { echo "ERROR: $*" >&2; exit 1; }
paso() { echo; echo "== $* =="; }
aviso() { echo "AVISO: $*"; }
ok() { echo "  OK  $*"; }

while [ $# -gt 0 ]; do
    case "$1" in
        --aplicar) APLICAR=1; shift ;;
        --simular) APLICAR=0; shift ;;
        --migrar) MIGRAR=1; shift ;;
        --externalizar) EXTERNALIZAR=1; shift ;;
        --respaldo) RESPALDO="${2:-}"; shift 2 ;;
        --raiz) RAIZ="${2:-}"; shift 2 ;;
        --grupo-web) GRUPO_WEB="${2:-}"; shift 2 ;;
        --php) PHP="${2:-}"; shift 2 ;;
        --composer) COMPOSER="${2:-}"; shift 2 ;;
        -h|--help) sed -n '2,/^set -euo/p' "$0" | sed '$d' | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) fallo "opción desconocida: $1 (use --help)" ;;
    esac
done
[ -d "$RAIZ" ] || fallo "la carpeta --raiz no existe."
RAIZ="$(cd "$RAIZ" && pwd)"
cd "$RAIZ"

# Las banderas destructivas solo tienen sentido al aplicar: sin --aplicar todo es simulación (así no se ejecutan por accidente).
if [ "$APLICAR" -eq 0 ] && { [ "$MIGRAR" -eq 1 ] || [ "$EXTERNALIZAR" -eq 1 ]; }; then
    echo "Modo SIMULACIÓN: --migrar/--externalizar se muestran pero NO se ejecutan (añada --aplicar para ejecutarlos)."
fi
if [ "$APLICAR" -eq 1 ]; then echo "Modo APLICAR sobre $RAIZ"; else echo "Modo SIMULAR (no se cambia nada) sobre $RAIZ"; fi

# Ejecuta (o solo muestra, en simulación) un comando. El comando se pasa como argumentos, sin eval.
ejecutar() {
    if [ "$APLICAR" -eq 1 ]; then echo "  \$ $*"; "$@"; else echo "  [SIMULADO] $*"; fi
}

# Lee UNA clave del .env sin imprimirla (quita comillas y comentarios al final). Vacío si no existe.
leer_env() {
    [ -f .env ] || return 0
    { grep -E "^$1=" .env || true; } | head -n 1 | cut -d= -f2- | sed -E 's/[[:space:]]+#.*$//; s/^["'"'"']//; s/["'"'"']$//'
}

# Confirmación interactiva: hay que escribir EXACTAMENTE el texto pedido (el nombre de la base). Cualquier otra cosa aborta.
confirmar() {
    local texto="$1" resp
    echo
    echo "  >>> $2"
    printf '  Escriba «%s» para continuar (cualquier otra cosa cancela): ' "$texto"
    read -r resp || resp=""
    [ "$resp" = "$texto" ] || fallo "confirmación incorrecta: se cancela sin cambios."
}

# Si algo falla con el sitio en mantenimiento, NO se reactiva solo: podría ser un sitio a medias.
al_salir() {
    local codigo=$?
    if [ "$codigo" -ne 0 ] && [ "$EN_MANTENIMIENTO" -eq 1 ]; then
        echo >&2
        echo "!! EL SITIO SIGUE EN MANTENIMIENTO (código de salida $codigo). Decida: corregir y repetir este script, o volver atrás" >&2
        echo "!! (GUIA_DESPLIEGUE.md, «Marcha atrás»). Para reactivarlo a mano:  $PHP artisan up" >&2
    fi
}
trap al_salir EXIT

# ---------------------------------------------------------------------------------------------------------------------
paso "1. Requisitos (solo lectura)"
command -v "$PHP" >/dev/null || fallo "no encuentro PHP ($PHP)."
PHP_VER="$("$PHP" -r 'echo PHP_VERSION;')"
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.1.0", ">=") ? 0 : 1);' || fallo "se necesita PHP >= 8.1 (hay $PHP_VER)."
ok "PHP $PHP_VER"
MODULOS="$("$PHP" -m | tr 'A-Z' 'a-z')"
for ext in pdo_mysql mbstring openssl tokenizer xml ctype fileinfo json; do
    echo "$MODULOS" | grep -qx "$ext" || fallo "falta la extensión de PHP: $ext."
done
ok "extensiones de PHP: pdo_mysql mbstring openssl tokenizer xml ctype fileinfo"
command -v "$COMPOSER" >/dev/null || fallo "no encuentro composer ($COMPOSER)."
ok "composer disponible"
[ -f artisan ] && [ -f composer.json ] && [ -f public/index.php ] && [ -f public/.htaccess ] || fallo "$RAIZ no parece la raíz de Explora (faltan artisan, composer.json o public/)."
ok "estructura del repositorio"

[ -f .env ] || fallo "no existe .env: cópielo de deploy/env.produccion.ejemplo y rellénelo (GUIA_DESPLIEGUE.md)."
[ "$(leer_env APP_ENV)" = "production" ] || fallo "APP_ENV debe ser production en .env."
[ "$(leer_env APP_DEBUG)" = "false" ] || fallo "APP_DEBUG debe ser false en .env."
[ -n "$(leer_env APP_KEY)" ] || fallo "APP_KEY está vacía: genérela UNA vez con  $PHP artisan key:generate --force  (no la regenere en cada despliegue: cierra las sesiones)."
case "$(leer_env APP_URL)" in http://*|https://*) ok ".env: APP_ENV=production, APP_DEBUG=false, APP_KEY y APP_URL definidos" ;; *) fallo "APP_URL debe ser una URL con http(s)://." ;; esac
case "$(leer_env APP_URL)" in https://*) [ "$(leer_env SESSION_SECURE_COOKIE)" = "true" ] || fallo "con APP_URL https, SESSION_SECURE_COOKIE debe ser true." ;; *) aviso "APP_URL no es https: solo es válido en un ensayo." ;; esac
case "$(leer_env CACHE_DRIVER)$(leer_env SESSION_DRIVER)" in *array*) fallo "SESSION_DRIVER/CACHE_DRIVER no pueden ser array en producción." ;; esac
BASE_DATOS="$(leer_env DB_DATABASE)"
[ -n "$BASE_DATOS" ] || fallo "DB_DATABASE vacío en .env."
ok "base de datos configurada: $BASE_DATOS"
# .env legible solo por su dueño y el grupo (contiene credenciales).
PERM_ENV="$(stat -c '%a' .env 2>/dev/null || stat -f '%Lp' .env)"
case "$PERM_ENV" in *[0-7]0|*[0-7]40|*[0-7]00) ok ".env con permisos $PERM_ENV" ;; *) aviso ".env tiene permisos $PERM_ENV: debería ser 640 o 600 (chmod 640 .env)." ;; esac

# Cosas que Apache serviría tal cual si quedaran dentro del documento raíz.
[ ! -e public/.env ] && [ ! -e public/.git ] || fallo "hay un .env o .git dentro de public/: bórrelo."
if [ -d public/ViajaPED/node_modules ]; then fallo "existe public/ViajaPED/node_modules (22 MB, se serviría por HTTP). Despliegue desde un checkout limpio o bórrelo."; fi
ok "public/ limpio (sin .env, .git ni node_modules)"

# ---------------------------------------------------------------------------------------------------------------------
paso "2. Respaldo previo"
if [ "$MIGRAR" -eq 1 ] || [ "$EXTERNALIZAR" -eq 1 ]; then
    [ -n "$RESPALDO" ] || fallo "--migrar/--externalizar exigen --respaldo ARCHIVO (hágalo con deploy/respaldar.sh)."
    [ -f "$RESPALDO" ] || fallo "el respaldo indicado no existe."
    [ "$(wc -c < "$RESPALDO" | tr -d ' ')" -gt 1024 ] || fallo "el respaldo está vacío o es demasiado pequeño."
    [ -n "$(find "$RESPALDO" -mmin -1440 2>/dev/null)" ] || fallo "el respaldo tiene más de 24 h: haga uno nuevo."
    case "$RESPALDO" in *.gz) gzip -t "$RESPALDO" || fallo "el respaldo gzip está corrupto." ;; esac
    ok "respaldo reciente y no vacío"
else
    echo "  (no hace falta sin --migrar ni --externalizar; con ellos es obligatorio)"
fi

# ---------------------------------------------------------------------------------------------------------------------
paso "3. Dependencias de producción (composer install --no-dev)"
ejecutar "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction

# A partir de aquí hace falta vendor/ (en simulación sobre un árbol sin instalar, se omite lo que necesita artisan).
HAY_VENDOR=0; [ -f vendor/autoload.php ] && HAY_VENDOR=1
artisan() { "$PHP" artisan "$@"; }
# artisan con el usuario de esquema (DDL_DB_*) si se definió; si no, con el del .env. Las variables solo viven en ESE proceso.
artisan_ddl() {
    if [ -n "${DDL_DB_USERNAME:-}" ]; then
        DB_USERNAME="$DDL_DB_USERNAME" DB_PASSWORD="${DDL_DB_PASSWORD:-}" "$PHP" artisan "$@"
    else
        "$PHP" artisan "$@"
    fi
}

# Cachés viejas FUERA antes de usar el código nuevo: (1) una config cacheada de la versión anterior no conoce las claves nuevas y
# (2) con config cacheada Laravel ignora DDL_DB_* y migraría con el usuario equivocado (comprobado en el ensayo de la Fase 16C).
# Es seguro con el sitio en marcha: Laravel recompone lo que falte en la siguiente petición.
if [ "$HAY_VENDOR" -eq 1 ] || [ "$APLICAR" -eq 1 ]; then
    paso "3b. Borrar cachés antiguas de Laravel"
    ejecutar artisan optimize:clear
fi

# ---------------------------------------------------------------------------------------------------------------------
paso "4. Migraciones pendientes del repositorio"
PENDIENTES=()
if [ "$HAY_VENDOR" -eq 1 ]; then
    # En un PRIMER despliegue la tabla `migrations` aún no existe: migrate:status falla con «Migration table not found» y TODAS las
    # migraciones están pendientes (`migrate` crea esa tabla sola). Cualquier otro fallo (credenciales, base caída) sí detiene el script.
    ESTADO="$(artisan migrate:status --no-ansi 2>&1)" || true
    if echo "$ESTADO" | grep -q "Migration table not found"; then
        aviso "la tabla migrations no existe: es el primer despliegue; TODAS las migraciones están pendientes (la baseline es idempotente)."
        ESTADO=""
    elif ! echo "$ESTADO" | grep -q "Migration name"; then
        fallo "no pude leer migrate:status (¿credenciales de la base en .env?): $(echo "$ESTADO" | tail -n 1 | cut -c1-160)"
    fi
    for f in database/migrations/*.php; do
        n="$(basename "$f" .php)"
        if echo "$ESTADO" | grep -E "$n" | grep -q "Ran"; then ok "ya aplicada: $n"; else PENDIENTES+=("$f"); echo "  PENDIENTE: $n"; fi
    done
    echo "  (Nota: «migrate --pretend» no muestra el SQL real de estas migraciones —las que usan comprobaciones previas devuelven"
    echo "   «tabla no existe» en modo simulado—. Revise el código de cada migración o ensáyelas en una copia.)"
else
    echo "  (sin vendor/ todavía no se puede consultar migrate:status; tras el composer install se mostrará)"
fi

if [ "$MIGRAR" -eq 1 ] && [ "$HAY_VENDOR" -eq 1 ] && [ "${#PENDIENTES[@]}" -eq 0 ]; then
    paso "5. Migrar"
    echo "  Nada pendiente: no se pone el sitio en mantenimiento ni se toca la base."
elif [ "$MIGRAR" -eq 1 ]; then
    paso "5. Migrar (auditoría, mantenimiento, migraciones, cachés, quitar mantenimiento)"
    if [ "$APLICAR" -eq 1 ]; then
        [ "$HAY_VENDOR" -eq 1 ] || fallo "falta vendor/: el composer install no terminó."
        confirmar "$BASE_DATOS" "Se va a poner el sitio en MANTENIMIENTO y a MIGRAR el esquema de la base '$BASE_DATOS' (${#PENDIENTES[@]} migración(es) pendiente(s)). Respaldo: $(basename "$RESPALDO")"
        echo "  Auditoría previa (solo lectura; el código 2 es esperable mientras haya filas huérfanas conocidas):"
        artisan explora:auditar-integridad | tail -n 12 || echo "  (la auditoría terminó con código $? — revise el resumen antes de seguir)"
        SECRETO="$(od -An -N12 -tx1 /dev/urandom | tr -d ' \n')"
        artisan down --retry=60 --secret="$SECRETO"
        EN_MANTENIMIENTO=1
        echo "  Para entrar durante el mantenimiento: <APP_URL>/$SECRETO  (anótelo; no se guarda en ningún archivo del repositorio)"
        for f in ${PENDIENTES[@]+"${PENDIENTES[@]}"}; do ejecutar artisan_ddl migrate --force --path="$f"; done
    else
        echo "  [SIMULADO] auditoría, php artisan down --retry=60 --secret=<aleatorio>, y por cada migración pendiente:"
        for f in ${PENDIENTES[@]+"${PENDIENTES[@]}"}; do echo "  [SIMULADO] $PHP artisan migrate --force --path=$f"; done
    fi
else
    if [ "${#PENDIENTES[@]}" -gt 0 ]; then aviso "hay ${#PENDIENTES[@]} migración(es) pendiente(s): el código nuevo puede necesitarlas (p. ej. resultado_juegos.creado_en). Use --migrar."; fi
fi

# ---------------------------------------------------------------------------------------------------------------------
paso "6. Cachés de Laravel y permisos"
permisos() {
    # Carpetas que Laravel debe poder escribir; todo lo demás queda de solo lectura para el servidor web.
    ejecutar mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    ejecutar chmod -R ug+rwX storage bootstrap/cache
    if [ -d public/contenido ]; then ejecutar chmod -R u+rwX,g+rX,o+rX public/contenido; fi
    ejecutar chmod 640 .env
    if [ -n "$GRUPO_WEB" ]; then
        ejecutar chgrp -R "$GRUPO_WEB" storage bootstrap/cache .env
        if [ -d public/contenido ]; then ejecutar chgrp -R "$GRUPO_WEB" public/contenido; fi
        ejecutar find storage bootstrap/cache -type d -exec chmod g+s {} +
    else
        echo "  (sin --grupo-web: ajuste a mano el grupo de storage/ y bootstrap/cache/ para el usuario del servidor web)"
    fi
}
permisos_privados() {
    # Los archivos de storage/ y las cachés (config.php guarda las contraseñas de MySQL en claro) NO deben ser legibles por «otros».
    # Solo con --grupo-web: sin un grupo que conserve el acceso, quitar «otros» podría dejar fuera al usuario del servidor web.
    if [ -n "$GRUPO_WEB" ]; then
        ejecutar chmod -R o-rwx storage bootstrap/cache
    else
        aviso "sin --grupo-web no se quitan los permisos de «otros» sobre storage/ y bootstrap/cache/: hágalo a mano (chmod -R o-rwx), config.php contiene las contraseñas."
    fi
}
if [ "$HAY_VENDOR" -eq 1 ] || [ "$APLICAR" -eq 1 ]; then
    ejecutar artisan config:cache
    ejecutar artisan route:cache
    ejecutar artisan view:cache
    ejecutar artisan event:cache
else
    echo "  [SIMULADO] artisan config:cache · route:cache · view:cache · event:cache"
fi
permisos
permisos_privados

if [ "$EN_MANTENIMIENTO" -eq 1 ]; then
    paso "7. Quitar el mantenimiento"
    artisan up
    EN_MANTENIMIENTO=0
fi

# ---------------------------------------------------------------------------------------------------------------------
if [ "$EXTERNALIZAR" -eq 1 ]; then
    paso "8. Externalizar las imágenes de las fichas (explora:externalizar-imagenes)"
    if [ "$APLICAR" -eq 1 ]; then
        [ "$HAY_VENDOR" -eq 1 ] || fallo "falta vendor/."
        # Con usuario de esquema/contenido: la config cacheada (paso 6) fijaría el usuario de la aplicación, que solo tiene SELECT.
        if [ -n "${DDL_DB_USERNAME:-}" ]; then artisan config:clear; fi
        echo "  Simulación (no escribe nada):"
        artisan_ddl explora:externalizar-imagenes | tail -n 5
        confirmar "$BASE_DATOS" "Se van a ESCRIBIR ~4.000 archivos en public/contenido y a MODIFICAR el contenido de la base '$BASE_DATOS'. Respaldo: $(basename "$RESPALDO")"
        # --base-real y --confirmo-respaldo son la declaración explícita de que el respaldo existe (la guarda del comando los exige
        # cuando la base no acaba en _test/_migtest); aquí solo se llega tras --respaldo validado y la confirmación de arriba.
        SALIDA="$(artisan_ddl explora:externalizar-imagenes --aplicar --base-real --confirmo-respaldo | tee /dev/stderr)"
        INFORME="$(echo "$SALIDA" | sed -n 's/^Informe: //p' | tail -n 1)"
        [ -n "$INFORME" ] && [ -f "$INFORME" ] || fallo "no encuentro el informe de la externalización."
        artisan_ddl explora:externalizar-imagenes --verificar="$INFORME" || fallo "la verificación del informe FALLÓ: revise y, si hace falta, --revertir (GUIA_DESPLIEGUE.md)."
        ok "externalización verificada: $INFORME"
        artisan_ddl explora:externalizar-imagenes | tail -n 4 | head -n 1
        # Se vuelve a cachear la configuración SIN las credenciales del usuario de esquema (config:clear la había quitado).
        if [ -n "${DDL_DB_USERNAME:-}" ]; then artisan config:cache; fi
        permisos
        permisos_privados
    else
        echo "  [SIMULADO] explora:externalizar-imagenes (simulación) → confirmación → --aplicar --base-real --confirmo-respaldo → --verificar=<informe>"
    fi
fi

paso "Resultado"
if [ "$APLICAR" -eq 1 ]; then
    echo "Terminado. Verifique ahora con:  deploy/verificar.sh https://SU-DOMINIO"
    if [ "$EXTERNALIZAR" -eq 1 ]; then echo "Si la externalización salió bien, puede bajar FICHAS_MEMORIA a 128M en .env y repetir  $PHP artisan config:cache."; fi
else
    echo "Simulación completada: nada se cambió. Para ejecutar los pasos no destructivos: --aplicar. Para migrar/externalizar: --aplicar --migrar --externalizar --respaldo ARCHIVO."
fi
