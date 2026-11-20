#!/usr/bin/env bash
# Sirve una COPIA del PHP legado contra las bases *_test (nunca contra BD reales).
# No modifica los archivos del repositorio (los conexion*.php originales quedan intactos).
# Uso: tests/Legacy/serve.sh [puerto]   (lee LEGACY_DB_* de laravel/.env)
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
# Documento raíz del sitio (Fase 9): el PHP legado vive ahora bajo laravel/public/
ROOT="$(cd "$HERE/../../public" && pwd)"
PORT="${1:-8081}"
# Solo se cargan las variables LEGACY_DB_* (no se ejecuta .env como shell ni se exportan otros secretos)
for v in LEGACY_DB_HOST LEGACY_DB_USER LEGACY_DB_PASS; do
  val="$(grep -E "^${v}=" "$HERE/../../.env" | head -1 | cut -d= -f2-)"
  [ -n "$val" ] || { echo "Falta $v en laravel/.env" >&2; exit 1; }
  export "$v=$val"
done
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/mapplic" "$TMP/juegos"
cp -R "$ROOT/mapplic/php" "$TMP/mapplic/php"
cp -R "$ROOT/juegos/php" "$TMP/juegos/php"
cp "$HERE/harness/conexion.php" "$TMP/mapplic/php/conexion.php"
cp "$HERE/harness/conexion.php" "$TMP/juegos/php/conexion.php"
cp "$HERE/harness/conexion_pedigital.php" "$TMP/mapplic/php/conexion_pedigital.php"
# chat.php instancia mysqli con credenciales propias: se redirige a la BD de prueba (solo en la copia)
sed -i.bak -E 's/new mysqli\("localhost", *"root", *"[^"]*", *"explora"\)/new mysqli(getenv("LEGACY_DB_HOST"), getenv("LEGACY_DB_USER"), getenv("LEGACY_DB_PASS"), "explora_test")/' "$TMP/mapplic/php/chat.php"
rm -f "$TMP/mapplic/php/"*.bak
# Archivos de copia/respaldo con credenciales propias: no se sirven
rm -f "$TMP/mapplic/php/chat copy.php"
# Defensa final: ninguna conexión mysqli de la copia puede ser distinta de las de prueba
if grep -rEn 'new mysqli' "$TMP" | grep -vE 'getenv\(' ; then
  echo "La copia contiene una conexión mysqli no redirigida a *_test; se aborta." >&2; exit 1
fi
grep -q 'explora_test' "$TMP/mapplic/php/chat.php" || { echo "No se pudo redirigir chat.php" >&2; exit 1; }
echo "PHP legado (copia) en http://127.0.0.1:$PORT contra explora_test/pedigital_test"
# memory_limit alto: el legado agota los 128 MB por defecto con la ficha del departamento 10 (FUN-11) y aquí debe
# servir de «oráculo» de comparación. El fallo con el límite por defecto está documentado en la matriz.
php -d memory_limit="${LEGACY_MEMORY:-1G}" -S "127.0.0.1:$PORT" -t "$TMP"
