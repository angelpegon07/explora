#!/usr/bin/env bash
#
# Fase 17F: optimiza las imágenes pesadas de public/.
#
# Qué hace
#   1. WEBP de más de 500 KB: los re-codifica con `cwebp -q 80 -m 6 -alpha_q 90` (mismas dimensiones, conserva la
#      transparencia) y SOLO reemplaza el original si el resultado queda al menos 25 % más pequeño. Los webp ANIMADOS
#      (cabecera con trozo "ANIM") se omiten y se listan: cwebp no sabe re-codificar animaciones.
#   2. PNG de más de 500 KB: crea el .webp hermano con `cwebp -q 82 -m 6 -alpha_q 90`, o con `-lossless` cuando el
#      arte parece línea/pixel-art (pocos colores): se considera así si la versión sin pérdida pesa como mucho un 30 %
#      más que la lossy, porque entonces compensa conservar el dibujo exacto. Solo se crea si queda al menos 30 %
#      más pequeño que el PNG. El PNG NO se borra aquí: quitar sus referencias y borrarlo es un paso aparte.
#   3. Imprime una tabla antes/después y el ahorro total.
#
# Uso
#   scripts/optimizar-imagenes.sh             # simulación: calcula y muestra, no escribe nada
#   scripts/optimizar-imagenes.sh --aplicar   # escribe los cambios
#   scripts/optimizar-imagenes.sh [--aplicar] [ruta]   # ruta a revisar (por defecto public/)
#
# Seguridad e idempotencia: cada conversión se escribe a un temporal y se mueve al destino solo si cumple el umbral;
# una segunda ejecución no encuentra nada que mejorar (un webp ya optimizado no baja otro 25 %; un PNG con hermano
# .webp válido se informa como «ya existe»). Se excluyen librerías de terceros (node_modules, vendor, wordfind-master).
#
# Requiere `cwebp` (libwebp). Funciona con bash 3.2 (macOS) y GNU.

set -u

APLICAR=0
RAIZ=""
for arg in "$@"; do
    case "$arg" in
        --aplicar) APLICAR=1 ;;
        -h|--help) sed -n '2,24p' "$0"; exit 0 ;;
        *) RAIZ="$arg" ;;
    esac
done

AQUI="$(cd "$(dirname "$0")/.." && pwd)"
RAIZ="${RAIZ:-$AQUI/public}"
UMBRAL=$((500 * 1024))      # solo se consideran archivos de más de 500 KB
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

command -v cwebp >/dev/null 2>&1 || { echo "Falta cwebp (libwebp)." >&2; exit 1; }

# Tamaño en bytes (BSD/macOS y GNU).
tam() { stat -f%z "$1" 2>/dev/null || stat -c%s "$1"; }
# KB con un decimal, para la tabla.
kb() { awk -v b="$1" 'BEGIN { printf "%.1f", b / 1024 }'; }
# ¿Es un webp animado? (trozo ANIM en la cabecera).
animado() { head -c 256 "$1" | LC_ALL=C grep -q 'ANIM'; }

TOTAL_ANTES=0
TOTAL_DESPUES=0
N_CAMBIOS=0

fila() { printf '%-9s %-62s %10s %10s %6s\n' "$1" "$2" "$3" "$4" "$5"; }

echo "Modo: $([ "$APLICAR" = 1 ] && echo APLICAR || echo SIMULACIÓN)  ·  Ruta: $RAIZ"
fila ACCIÓN ARCHIVO "ANTES(KB)" "DESPUÉS(KB)" "AHORRO"

# Archivos candidatos (más de 500 KB), sin librerías de terceros.
while IFS= read -r f; do
    antes="$(tam "$f")"
    rel="${f#$AQUI/}"
    ext="${f##*.}"

    if [ "$ext" = "webp" ]; then
        if animado "$f"; then
            fila ANIMADO "$rel" "$(kb "$antes")" "-" "omitido"
            continue
        fi
        tmp="$TMP/salida.webp"
        cwebp -quiet -q 80 -m 6 -alpha_q 90 "$f" -o "$tmp" 2>/dev/null || { fila ERROR "$rel" "$(kb "$antes")" "-" "-"; continue; }
        despues="$(tam "$tmp")"
        # Reemplaza solo si queda al menos 25 % más pequeño: despues <= 75 % de antes.
        if [ $((despues * 100)) -le $((antes * 75)) ]; then
            [ "$APLICAR" = 1 ] && mv "$tmp" "$f"
            fila RECODIF "$rel" "$(kb "$antes")" "$(kb "$despues")" "$(( (antes - despues) * 100 / antes ))%"
            TOTAL_ANTES=$((TOTAL_ANTES + antes)); TOTAL_DESPUES=$((TOTAL_DESPUES + despues)); N_CAMBIOS=$((N_CAMBIOS + 1))
        else
            fila SIN_GANAR "$rel" "$(kb "$antes")" "$(kb "$despues")" "<25%"
        fi
    else
        destino="${f%.png}.webp"
        # NUNCA se sobrescribe un .webp hermano existente: puede tener MÁS resolución que el PNG (caso de maps_vertientes/caribe,
        # donde el .webp medía 6495x6852 y el PNG 2954x3116) y reemplazarlo lo degradaría.
        if [ -e "$destino" ]; then
            fila YA_EXISTE "$rel" "$(kb "$antes")" "$(kb "$(tam "$destino")")" "hermano"
            continue
        fi
        lossy="$TMP/lossy.webp"; sinperd="$TMP/lossless.webp"
        cwebp -quiet -q 82 -m 6 -alpha_q 90 "$f" -o "$lossy" 2>/dev/null || { fila ERROR "$rel" "$(kb "$antes")" "-" "-"; continue; }
        elegido="$lossy"; modo="PNG>WEBP"
        if cwebp -quiet -lossless -m 6 "$f" -o "$sinperd" 2>/dev/null; then
            # Arte lineal / pocos colores: el lossless pesa casi lo mismo que el lossy (o menos).
            if [ $(( $(tam "$sinperd") * 100 )) -le $(( $(tam "$lossy") * 130 )) ]; then
                elegido="$sinperd"; modo="PNG>LOSSL"
            fi
        fi
        despues="$(tam "$elegido")"
        # Se crea solo si queda al menos 30 % más pequeño: despues <= 70 % de antes.
        if [ $((despues * 100)) -le $((antes * 70)) ]; then
            [ "$APLICAR" = 1 ] && mv "$elegido" "$destino"
            fila "$modo" "$rel" "$(kb "$antes")" "$(kb "$despues")" "$(( (antes - despues) * 100 / antes ))%"
            TOTAL_ANTES=$((TOTAL_ANTES + antes)); TOTAL_DESPUES=$((TOTAL_DESPUES + despues)); N_CAMBIOS=$((N_CAMBIOS + 1))
        else
            fila SIN_GANAR "$rel" "$(kb "$antes")" "$(kb "$despues")" "<30%"
        fi
    fi
done < <(find "$RAIZ" -type f \( -name '*.png' -o -name '*.webp' \) -size +"${UMBRAL}"c \
    -not -path '*/node_modules/*' -not -path '*/vendor/*' -not -path '*/wordfind-master/*' | LC_ALL=C sort)

echo
echo "Archivos mejorados: $N_CAMBIOS"
echo "Total antes: $(kb "$TOTAL_ANTES") KB  ·  después: $(kb "$TOTAL_DESPUES") KB  ·  ahorro: $(kb $((TOTAL_ANTES - TOTAL_DESPUES))) KB"
echo "(En PNG convertidos el ahorro se mide contra el PNG, que sigue en disco hasta retirar sus referencias.)"
[ "$APLICAR" = 1 ] || echo "Simulación: no se escribió nada. Use --aplicar para aplicar."
