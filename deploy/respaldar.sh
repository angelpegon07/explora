#!/usr/bin/env bash
# =====================================================================================================================
# Explora · respaldo de la base de datos (y, opcionalmente, de public/contenido). Guía: GUIA_DESPLIEGUE.md, «Respaldo».
#
# Es la marcha atrás completa de cualquier migración de esquema o de contenido: hágalo SIEMPRE antes de desplegar.
#
# Qué hace (solo lee la base; escribe únicamente en la carpeta de destino):
#   1. Comprueba que existen mysqldump y gzip y que las credenciales llegan POR ARCHIVO O ENTORNO (nunca por la línea de comandos,
#      donde cualquier usuario del equipo las vería con `ps`).
#   2. Comprueba el espacio libre en el destino (estimación = tamaño de la base; mínimo configurable).
#   3. mysqldump --single-transaction (copia consistente sin bloquear el sitio) -> gzip -> archivo con fecha.
#   4. Comprueba que el volcado NO está vacío, que el gzip es íntegro y que termina con la marca «Dump completed».
#   5. Con --con-contenido copia también public/contenido/ (las imágenes externalizadas) a un .tar.gz.
#
# Credenciales (UNA de las dos formas):
#   a) MYSQL_DEFAULTS_FILE=/ruta/respaldo.cnf   archivo (chmod 600) con:  [client]\n user=...\n password=...\n host=127.0.0.1
#   b) MYSQL_USUARIO=...  y  MYSQL_PWD=...      variables de entorno (MYSQL_PWD la lee mysqldump sin mostrarla en `ps`)
# Otras variables: DB_HOST (127.0.0.1), DB_PORT (3306), MYSQLDUMP (ruta del binario si no está en el PATH).
#
# Uso:  deploy/respaldar.sh --base NOMBRE [--destino CARPETA] [--con-contenido] [--minimo-mb N] [--raiz CARPETA]
# =====================================================================================================================
set -euo pipefail

BASE=""
DESTINO=""
CON_CONTENIDO=0
MINIMO_MB=1024
# Raíz del repositorio = carpeta superior de la de este script (sirve aunque se llame desde otro directorio).
RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

uso() { sed -n '2,/^set -euo/p' "$0" | sed '$d' | sed 's/^# \{0,1\}//'; }
fallo() { echo "ERROR: $*" >&2; exit 1; }

while [ $# -gt 0 ]; do
    case "$1" in
        --base) BASE="${2:-}"; shift 2 ;;
        --destino) DESTINO="${2:-}"; shift 2 ;;
        --con-contenido) CON_CONTENIDO=1; shift ;;
        --minimo-mb) MINIMO_MB="${2:-}"; shift 2 ;;
        --raiz) RAIZ="${2:-}"; shift 2 ;;
        -h|--help) uso; exit 0 ;;
        *) fallo "opción desconocida: $1 (use --help)" ;;
    esac
done

[ -n "$BASE" ] || fallo "falta --base NOMBRE (la base a respaldar)."
case "$BASE" in *[!A-Za-z0-9_]*) fallo "el nombre de base solo admite letras, números y guion bajo." ;; esac
case "$MINIMO_MB" in ''|*[!0-9]*) fallo "--minimo-mb debe ser un entero." ;; esac
RAIZ="$(cd "$RAIZ" && pwd)"

# El destino por defecto queda FUERA del repositorio (junto a él): un volcado dentro de public/ se serviría por HTTP.
[ -n "$DESTINO" ] || DESTINO="$(dirname "$RAIZ")/respaldos-explora"
mkdir -p "$DESTINO"
DESTINO="$(cd "$DESTINO" && pwd)"
case "$DESTINO/" in
    # (se retira la carpeta vacía que acaba de crear `mkdir -p` para no dejar rastro dentro de public/)
    "$RAIZ/public/"*) rmdir "$DESTINO" 2>/dev/null || true; fallo "el destino no puede estar dentro de public/ (se serviría por HTTP): $DESTINO" ;;
esac

MYSQLDUMP="${MYSQLDUMP:-$(command -v mysqldump || true)}"
[ -n "$MYSQLDUMP" ] && [ -x "$MYSQLDUMP" ] || fallo "no encuentro mysqldump (instale el cliente de MySQL o defina MYSQLDUMP=/ruta/mysqldump)."
command -v gzip >/dev/null || fallo "no encuentro gzip."

# Credenciales: archivo de opciones o variables de entorno; jamás argumentos con la contraseña.
ARGS=()
if [ -n "${MYSQL_DEFAULTS_FILE:-}" ]; then
    [ -r "$MYSQL_DEFAULTS_FILE" ] || fallo "no puedo leer MYSQL_DEFAULTS_FILE."
    # --defaults-extra-file DEBE ser la primera opción de mysqldump.
    ARGS+=("--defaults-extra-file=$MYSQL_DEFAULTS_FILE")
elif [ -n "${MYSQL_USUARIO:-}" ] && [ -n "${MYSQL_PWD:-}" ]; then
    ARGS+=("--user=$MYSQL_USUARIO")
else
    fallo "defina MYSQL_DEFAULTS_FILE (recomendado) o MYSQL_USUARIO + MYSQL_PWD. No se aceptan contraseñas por argumento."
fi
ARGS+=("--host=${DB_HOST:-127.0.0.1}" "--port=${DB_PORT:-3306}")

# Espacio libre en el destino (KB -> MB). Estimación del tamaño de la base si hay cliente mysql; si no, el mínimo configurado.
LIBRE_MB=$(( $(df -Pk "$DESTINO" | awk 'NR==2 {print $4}') / 1024 ))
NECESARIO_MB=$MINIMO_MB
CLIENTE="${MYSQL_CLIENTE:-$(command -v mysql || true)}"
if [ -n "$CLIENTE" ] && [ -x "$CLIENTE" ]; then
    # Los mismos argumentos de conexión sirven al cliente (comparten archivo de opciones y MYSQL_PWD).
    TAM_BASE_MB=$("$CLIENTE" "${ARGS[@]}" -N -e "SELECT COALESCE(CEIL(SUM(data_length+index_length)/1048576),0) FROM information_schema.tables WHERE table_schema='$BASE'" 2>/dev/null || echo 0)
    case "$TAM_BASE_MB" in ''|*[!0-9]*) TAM_BASE_MB=0 ;; esac
    # El volcado SQL pesa ~ lo que la base; comprimido, bastante menos: se exige el tamaño de la base como holgura.
    [ "$TAM_BASE_MB" -gt "$NECESARIO_MB" ] && NECESARIO_MB=$TAM_BASE_MB
fi
echo "Espacio libre en el destino: ${LIBRE_MB} MB · necesario (estimado): ${NECESARIO_MB} MB"
[ "$LIBRE_MB" -ge "$NECESARIO_MB" ] || fallo "espacio insuficiente en $DESTINO (libre ${LIBRE_MB} MB, necesario ${NECESARIO_MB} MB)."

SELLO="$(date +%Y%m%d-%H%M%S)"
ARCHIVO="$DESTINO/explora-${BASE}-${SELLO}.sql.gz"
# Los respaldos contienen datos personales de estudiantes: solo legibles por su dueño.
umask 077

echo "Respaldando la base '$BASE' (mysqldump --single-transaction)..."
INICIO=$(date +%s)
# `pipefail` hace fallar el pipeline si mysqldump falla aunque gzip termine bien. --no-tablespaces: no exige el privilegio PROCESS.
if ! "$MYSQLDUMP" "${ARGS[@]}" --single-transaction --no-tablespaces "$BASE" | gzip -c > "$ARCHIVO"; then
    rm -f "$ARCHIVO"
    fallo "mysqldump falló; se borró el archivo parcial."
fi

# Verificación: tamaño razonable, gzip íntegro y marca final de mysqldump (si el volcado se cortó, no aparece).
TAM=$(wc -c < "$ARCHIVO" | tr -d ' ')
[ "$TAM" -gt 1024 ] || { rm -f "$ARCHIVO"; fallo "el volcado está vacío o es sospechosamente pequeño ($TAM bytes)."; }
gzip -t "$ARCHIVO" || { rm -f "$ARCHIVO"; fallo "el gzip está corrupto."; }
gzip -dc "$ARCHIVO" | tail -n 5 | grep -q "Dump completed" || { rm -f "$ARCHIVO"; fallo "el volcado no termina con «Dump completed» (se cortó)."; }
# Cuenta las tablas del volcado (lee el flujo completo; con `head` el SIGPIPE haría fallar el pipeline bajo pipefail).
TABLAS=$(gzip -dc "$ARCHIVO" | grep -c '^CREATE TABLE' || true)
[ "$TABLAS" -gt 0 ] || { rm -f "$ARCHIVO"; fallo "el volcado no contiene ninguna tabla (¿base vacía o nombre equivocado?)."; }
echo "OK  Base: $ARCHIVO ($TABLAS tablas, $((TAM / 1048576)) MB comprimido, $(( $(date +%s) - INICIO )) s)"

if [ "$CON_CONTENIDO" -eq 1 ]; then
    if [ -d "$RAIZ/public/contenido" ]; then
        TAR="$DESTINO/explora-contenido-${SELLO}.tar.gz"
        tar -czf "$TAR" -C "$RAIZ/public" contenido
        tar -tzf "$TAR" >/dev/null || { rm -f "$TAR"; fallo "el tar de contenido está corrupto."; }
        echo "OK  Contenido: $TAR ($(( $(wc -c < "$TAR" | tr -d ' ') / 1048576 )) MB)"
    else
        echo "AVISO: no existe $RAIZ/public/contenido (todavía no se externalizaron imágenes): nada que copiar."
    fi
fi
echo "Guarde estos archivos FUERA del servidor web y pruebe a restaurarlos alguna vez en una base de ensayo."
