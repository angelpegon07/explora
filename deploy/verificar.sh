#!/usr/bin/env bash
# =====================================================================================================================
# Explora · verificación posterior al despliegue. SOLO LECTURA. Guía: GUIA_DESPLIEGUE.md, «Verificación posterior».
#
# Lanza peticiones de lectura (GET/HEAD/OPTIONS/TRACE, y un POST VACÍO a una URL retirada que responde 410 sin leer nada) contra
# BASE_URL y muestra una tabla OK / FALLO / AVISO / OMITIDO con el resultado esperado. No envía credenciales, no guarda resultados,
# no cambia avatares: no escribe datos. Código de salida 1 si hay algún FALLO (los AVISO no cuentan).
#
# Uso:  deploy/verificar.sh [BASE_URL] [--insecure] [--sin-color]
#       BASE_URL por defecto: $BASE_URL o http://127.0.0.1 . Ejemplo:  deploy/verificar.sh https://explora.example.org
# Cada comprobación usa un máximo de 30 s. El grupo `api` admite 300 peticiones/min por IP: este script hace unas 130, de las que solo ~20 son del grupo `api`.
# =====================================================================================================================
set -euo pipefail

URL="${BASE_URL:-http://127.0.0.1}"
INSEGURO=()
COLOR=1
for a in "$@"; do
    case "$a" in
        --insecure) INSEGURO=(--insecure) ;;
        --sin-color) COLOR=0 ;;
        -h|--help) sed -n '2,/^set -euo/p' "$0" | sed '$d' | sed 's/^# \{0,1\}//'; exit 0 ;;
        http://*|https://*) URL="$a" ;;
        *) echo "ERROR: argumento desconocido: $a" >&2; exit 2 ;;
    esac
done
URL="${URL%/}"
command -v curl >/dev/null || { echo "ERROR: hace falta curl." >&2; exit 2; }
[ -t 1 ] || COLOR=0

TOTAL=0; FALLOS=0; AVISOS=0; OMITIDOS=0
verde() { [ "$COLOR" -eq 1 ] && printf '\033[32m%s\033[0m' "$1" || printf '%s' "$1"; }
rojo() { [ "$COLOR" -eq 1 ] && printf '\033[31m%s\033[0m' "$1" || printf '%s' "$1"; }
ambar() { [ "$COLOR" -eq 1 ] && printf '\033[33m%s\033[0m' "$1" || printf '%s' "$1"; }

# curl con tiempo máximo y sin seguir redirecciones (las redirecciones SON lo que se comprueba).
c() { curl -s --max-time 30 ${INSEGURO[@]+"${INSEGURO[@]}"} "$@"; }

# resultado ESTADO "descripción" "esperado" "obtenido"
resultado() {
    TOTAL=$((TOTAL + 1))
    case "$1" in
        OK) printf '%s  ' "$(verde ' OK  ')" ;;
        FALLO) FALLOS=$((FALLOS + 1)); printf '%s  ' "$(rojo 'FALLO')" ;;
        AVISO) AVISOS=$((AVISOS + 1)); printf '%s  ' "$(ambar 'AVISO')" ;;
        OMITIDO) OMITIDOS=$((OMITIDOS + 1)); printf '%s  ' ' --  ' ;;
    esac
    printf '%-58s esperado: %-18s obtenido: %s\n' "$2" "$3" "$4"
}

# codigo METODO RUTA [Host] -> imprime el código HTTP
codigo() {
    local m="$1" r="$2" host="${3:-}"
    if [ -n "$host" ]; then c -o /dev/null -w '%{http_code}' -X "$m" -H "Host: $host" "$URL$r" || true
    else c -o /dev/null -w '%{http_code}' -X "$m" "$URL$r" || true; fi
}

# esperar METODO RUTA "cód1|cód2" [descripción]  -> OK si el código está entre los aceptados
esperar() {
    local m="$1" r="$2" ok="$3" desc="${4:-$1 $2}" got
    got=$(codigo "$m" "$r")
    case "|$ok|" in *"|$got|"*) resultado OK "$desc" "$ok" "$got" ;; *) resultado FALLO "$desc" "$ok" "$got" ;; esac
}

# cabeceras RUTA -> cabeceras (en minúsculas, sin \r) de un GET
cabeceras() { { c -D - -o /dev/null "$@" || true; } | tr -d '\r' | tr 'A-Z' 'a-z'; }

# Si el servidor no responde, no tiene sentido seguir (código 2 = no se pudo verificar, distinto de 1 = hay fallos).
c -o /dev/null "$URL/" || { echo "ERROR: no puedo conectar con $URL (¿servidor caído, URL o certificado incorrectos? Para un certificado autofirmado use --insecure)." >&2; exit 2; }

# Un 503 en la portada casi siempre es el modo mantenimiento (`php artisan down`) olvidado: casi todo lo demás fallará por eso.
if [ "$(c -o /dev/null -w '%{http_code}' "$URL/" || true)" = "503" ]; then
    echo "AVISO: la portada responde 503. ¿El sitio sigue en mantenimiento? (php artisan up). Los fallos siguientes serán consecuencia de eso." >&2
fi

echo "Verificando $URL  ($(date '+%Y-%m-%d %H:%M:%S'))"
echo "------------------------------------------------------------------------------------------------------------"

echo "# Páginas y rutas de Laravel"
for r in / /perfil /climatico /coordilleras /etnias /parques_naturales /vertientes /juegos/ /juegos/juegos/puzzle/ /lineaTiempo/ /ViajaPED/; do
    esperar GET "$r" 200
done
esperar GET /juegos 301 "GET /juegos (la carpeta redirige a /juegos/)"
esperar GET /lineaTiempo 301
esperar GET /juegos/juegos/puzzle 301
esperar GET /nada-que-no-existe 404

echo "# Redirecciones 301 de las URL antiguas (con Location y conservando la query)"
for par in "/index.html|/" "/climatico.html|/climatico" "/miPerfil.html|/perfil" "/juegos/index.html|/juegos" "/lineaTiempo/index.html|/lineaTiempo/" "/juegos/juegos/puzzle/index.html|/juegos/juegos/puzzle/"; do
    r="${par%%|*}"; destino="${par##*|}"
    got=$(c -o /dev/null -w '%{http_code} %{redirect_url}' "$URL$r" || true)
    [ "$got" = "301 $URL$destino" ] && resultado OK "GET $r" "301 -> $destino" "301" || resultado FALLO "GET $r" "301 -> $destino" "${got//$URL/}"
done
got=$(c -o /dev/null -w '%{http_code} %{redirect_url}' "$URL/climatico.html?x=1&y=2" || true)
[ "$got" = "301 $URL/climatico?x=1&y=2" ] && resultado OK "301 conserva la query (?x=1&y=2)" "301 + query" "301" || resultado FALLO "301 conserva la query" "301 + query" "${got//$URL/}"

echo "# API (solo lectura)"
esperar GET /api/sesion 401 "GET /api/sesion sin sesión"
esperar GET /api/historial 401
esperar GET /api/departamentos/10/preguntas 200
esperar GET /api/departamentos/10/ficha 200
esperar GET /api/nada 404
esperar GET /api/resultados 405 "GET /api/resultados (solo admite POST)"
ct=$(c -D - -o /dev/null "$URL/api/sesion" | tr -d '\r' | tr 'A-Z' 'a-z' | grep '^content-type:' || true)
case "$ct" in *application/json*) resultado OK "/api/sesion responde JSON" "application/json" "application/json" ;; *) resultado FALLO "/api/sesion responde JSON" "application/json" "${ct:-nada}" ;; esac

echo "# Servicios PHP retirados (410, sin cuerpo útil ni datos)"
for r in /mapplic/php/iniciarSesion.php /mapplic/php/cambiarAvatar.php /mapplic/php/consultarInfo.php /mapplic/php/buscarPreguntas.php /juegos/php/guardar_resultado.php /juegos/php/consultar_ranking.php /juegos/php/consultar_historial.php; do
    esperar GET "$r" 410
done
esperar POST /juegos/php/guardar_resultado.php 410 "POST vacío a un servicio retirado"

echo "# Archivos y carpetas que NO deben servirse"
for r in /composer.json /artisan /.env /.git/HEAD /storage/logs/laravel.log /vendor/autoload.php /bootstrap/cache/config.php /app/Http/Kernel.php /config/app.php /database /tests /DOCUMENTACION_MEJORAMIENTO; do
    esperar GET "$r" "403|404"
done
for r in /composer.lock /README.md /x.sql /x.map /copia.bak /.htaccess /.htpasswd /mapplic/php/conexion.php; do esperar GET "$r" "403|404"; done
for r in /css/ /js/ /miPerfil/ /mapplic/php/ /contenido/ /contenido/fichas/; do esperar GET "$r" "403|404" "Carpeta sin índice $r (no se lista)"; done
esperar GET /contenido/x.html 404 "Solo imágenes bajo /contenido/ (x.html)"
esperar GET /index.php/x "403|404" "/index.php/x no se ejecuta como ruta"
esperar GET /index.php/api/sesion "403|404" "/index.php/api/sesion no se ejecuta como ruta"

echo "# Métodos"
esperar TRACE / "403|405" "TRACE desactivado"
esperar POST / 405 "POST / no permitido"
esperar DELETE /api/departamentos/10/ficha 405
got=$(c -I -o /dev/null -w '%{http_code}' "$URL/" || true)
[ "$got" = 200 ] && resultado OK "HEAD /" 200 "$got" || resultado FALLO "HEAD /" 200 "$got"

echo "# Cabeceras de seguridad (también en archivos estáticos)"
for r in / /js/explora-api.js /nada-que-no-existe; do
    h=$(cabeceras "$URL$r")
    for par in "x-content-type-options:nosniff" "x-frame-options:sameorigin" "referrer-policy:strict-origin-when-cross-origin"; do
        n="${par%%:*}"; v="${par#*:}"
        echo "$h" | grep -q "^$n: $v" && resultado OK "$n en $r" "$v" "presente" || resultado FALLO "$n en $r" "$v" "ausente"
    done
done
h=$(cabeceras "$URL/")
echo "$h" | grep -q '^x-powered-by' && resultado FALLO "X-Powered-By ausente" "ausente" "presente (expose_php)" || resultado OK "X-Powered-By ausente" "ausente" "ausente"
echo "$h" | grep -q '^server: apache/' && resultado AVISO "Server sin versión (ServerTokens Prod)" "Apache" "$(echo "$h" | grep '^server:' | tr -d '\n')" || resultado OK "Server sin versión" "Apache" "ok"
case "$URL" in https://*) echo "$h" | grep -q '^strict-transport-security' && resultado OK "HSTS" "presente" "presente" || resultado AVISO "HSTS (pendiente de decisión)" "presente" "ausente" ;; *) resultado OMITIDO "HSTS (solo con HTTPS)" "-" "-" ;; esac

echo "# Cookies"
for r in / /climatico /lineaTiempo/ /api/ranking; do
    n=$(cabeceras "$URL$r" | grep -c '^set-cookie' || true)
    [ "$n" -eq 0 ] && resultado OK "Sin Set-Cookie en $r" "0 cookies" "0" || resultado FALLO "Sin Set-Cookie en $r" "0 cookies" "$n"
done
h=$(cabeceras "$URL/perfil")
echo "$h" | grep -q '^set-cookie: explora_session=' && resultado OK "/perfil crea la cookie de sesión" "Set-Cookie" "presente" || resultado FALLO "/perfil crea la cookie de sesión" "Set-Cookie" "ausente"
echo "$h" | grep '^set-cookie: explora_session=' | grep -q 'httponly' && resultado OK "Cookie de sesión HttpOnly" "httponly" "presente" || resultado FALLO "Cookie de sesión HttpOnly" "httponly" "ausente"
echo "$h" | grep '^set-cookie: explora_session=' | grep -q 'samesite=lax' && resultado OK "Cookie de sesión SameSite=Lax" "samesite=lax" "presente" || resultado FALLO "Cookie de sesión SameSite=Lax" "samesite=lax" "ausente"
case "$URL" in
    https://*) echo "$h" | grep '^set-cookie: explora_session=' | grep -q 'secure' && resultado OK "Cookie de sesión Secure" "secure" "presente" || resultado FALLO "Cookie de sesión Secure (SESSION_SECURE_COOKIE=true)" "secure" "ausente" ;;
    *) resultado OMITIDO "Cookie Secure (solo con HTTPS)" "-" "-" ;;
esac

echo "# Host ajeno"
got=$(codigo GET / otro.example)
case "$got" in 400|403|421) resultado OK "Host: otro.example en /" "400|403|421" "$got" ;; *) resultado FALLO "Host: otro.example en /" "400|403|421" "$got" ;; esac
got=$(codigo GET /api/sesion otro.example)
case "$got" in 400|403|421) resultado OK "Host: otro.example en /api/sesion" "400|403|421" "$got" ;; *) resultado FALLO "Host: otro.example en /api/sesion" "400|403|421" "$got" ;; esac

echo "# Tipos de contenido y compresión"
ctipo() { c -D - -o /dev/null "$URL$1" | tr -d '\r' | tr 'A-Z' 'a-z' | grep '^content-type:' | sed 's/content-type: *//' | tr -d '\n' || true; }
for par in "/js/explora-api.js|javascript" "/lineaTiempo/eventos.json|application/json" "/ViajaPED/data.json|application/json" "/favicon.ico|image/"; do
    r="${par%%|*}"; esperado="${par##*|}"; got=$(ctipo "$r")
    case "$got" in *"$esperado"*) resultado OK "Content-Type de $r" "$esperado" "$got" ;; *) resultado FALLO "Content-Type de $r" "$esperado" "${got:-nada}" ;; esac
done
for r in /js/explora-api.js /lineaTiempo/eventos.json /api/departamentos/10/ficha; do
    c -H 'Accept-Encoding: gzip' -D - -o /dev/null "$URL$r" | tr -d '\r' | tr 'A-Z' 'a-z' | grep -q '^content-encoding: gzip' \
        && resultado OK "gzip en $r" "gzip" "gzip" || resultado AVISO "gzip en $r (mod_deflate recomendado)" "gzip" "sin comprimir"
done

echo "# Imágenes externalizadas de las fichas (Fase 16A)"
TAM=$(c -o /dev/null -w '%{size_download}' "$URL/api/departamentos/10/ficha" || true); TAM="${TAM:-0}"
# Sin externalizar la ficha del depto 10 pesa ~48 MB; externalizada, ~0,26 MB.
if [ "$TAM" -gt 5000000 ]; then resultado AVISO "Ficha 10 ligera (imágenes ya externalizadas)" "< 1 MB" "$((TAM / 1048576)) MB (¿sin externalizar?)"
elif [ "$TAM" -gt 0 ]; then resultado OK "Ficha 10 ligera (imágenes ya externalizadas)" "< 1 MB" "$((TAM / 1024)) KB"
else resultado FALLO "Ficha 10 ligera" "< 1 MB" "sin respuesta"; fi
# El JSON escapa las barras (\/): se des-escapan antes de buscar la URL.
IMG=$(c "$URL/api/departamentos/10/ficha" | sed 's#\\/#/#g' | grep -oE '/contenido/fichas/[0-9a-f]{2}/[0-9a-f]{64}\.(png|jpg|gif|webp)' | head -n 1 || true)
if [ -n "$IMG" ]; then
    h=$(cabeceras "$URL$IMG")
    echo "$h" | head -n 1 | grep -q ' 200' && resultado OK "GET $(echo "$IMG" | cut -c1-34)…" "200" "200" || resultado FALLO "GET imagen de la ficha" "200" "$(echo "$h" | head -n 1)"
    echo "$h" | grep -q '^content-type: image/' && resultado OK "Content-Type de la imagen" "image/*" "image/*" || resultado FALLO "Content-Type de la imagen" "image/*" "otro"
    echo "$h" | grep -q '^cache-control: public, max-age=31536000, immutable' && resultado OK "Cache-Control inmutable de la imagen" "immutable" "immutable" || resultado FALLO "Cache-Control inmutable de la imagen" "immutable" "ausente"
else
    resultado OMITIDO "Imágenes de /contenido/ (la ficha 10 no las enlaza)" "-" "-"
fi

echo "# Riesgo conocido (informativo: lo decide el responsable)"
got=$(codigo GET "/mapplic/php/chat.php")
case "$got" in 200) resultado AVISO "chat.php sigue accesible (chat legado sin proteger)" "403 si se mitiga" "200" ;; 403|404) resultado OK "chat.php bloqueado" "403|404" "$got" ;; *) resultado AVISO "chat.php responde algo inesperado" "200|403" "$got" ;; esac

echo "------------------------------------------------------------------------------------------------------------"
echo "Comprobaciones: $TOTAL · fallos: $FALLOS · avisos: $AVISOS · omitidas: $OMITIDOS"
if [ "$FALLOS" -gt 0 ]; then echo "RESULTADO: HAY FALLOS. No abra el sitio hasta resolverlos (GUIA_DESPLIEGUE.md, marcha atrás)."; exit 1; fi
echo "RESULTADO: correcto."
