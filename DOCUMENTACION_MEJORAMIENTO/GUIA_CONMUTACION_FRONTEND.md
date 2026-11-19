# GUÍA PARA CONECTAR EL FRONTEND A LA API NUEVA

Estado: **el frontend NO está conectado.** Esta guía reúne, en orden, lo que hay que cambiar en el sitio estático para
usar los endpoints de `laravel/` (ficha, quiz, sesión y avatar). Fuente: revisión de contrato de la Fase 4
(agente de compatibilidad) contrastada con el código; las comprobaciones empíricas están marcadas.

## Qué NO cambia
Los cuerpos de respuesta son compatibles campo a campo con el legado (las 16 claves de `verMas.js`, la forma del quiz y
`usuario_objeto`). Los ids numéricos salen como enteros en vez de cadenas, y ningún JS depende del tipo.

## Cambios necesarios, en orden

1. **Mismo origen y base única.** El sitio y la API deben servirse desde el mismo dominio (la cookie es `SameSite=Lax` y
   CORS no admite otros orígenes por defecto). Definir una constante, p. ej. `window.EXPLORA_API = '/api'`, y dejar de
   usar rutas relativas (`mapplic/php/…`, `../mapplic/php/…`) y la variable `tipo_modal` de `localStorage`, que puede
   quedar desfasada entre páginas (`iniciarSesion.js:7`). En HTTPS: `SESSION_SECURE_COOKIE=true`.
2. **`$.ajaxSetup` global** que lea la cookie `XSRF-TOKEN` (con `decodeURIComponent`) **en cada petición** y la envíe en
   `X-XSRF-TOKEN`. El token cambia al iniciar y al cerrar sesión; guardarlo una sola vez al cargar la página provoca 419.
   jQuery, a diferencia de axios, no lo hace solo.
3. **Tratar 401 y 419 como «sesión caducada»:** limpiar `localStorage` y mostrar «Iniciar sesión».
   *(Comprobado: sin sesión la ruta responde 401, porque `auth` se evalúa antes que CSRF; con sesión válida y token viejo
   responde 419.)* También un 401 con «La cuenta no está activa.» significa que la cuenta fue desactivada.
4. **Al cargar cada página que use sesión** (las 6 con `iniciarSesion.js`, `miPerfil.html` y los 7 juegos) llamar a
   `GET /api/sesion`: entrega la cookie CSRF y sirve para sincronizar o limpiar `localStorage`. Devuelve 401 si no hay
   sesión (deja un error rojo en consola; se puede ignorar en el `fail`).
5. **`mapplic/verMas.js`:** URL `${API}/departamentos/${id}/ficha`; **quitar `JSON.parse(response)`** (línea 23: con
   `application/json` jQuery ya entrega un objeto); en `error` **cerrar `loadingAlert`** y avisar (hoy, ante un 404,
   429 o 500, el diálogo de carga queda abierto y bloquea la pantalla porque `allowOutsideClick:false`).
6. **`mapplic/js/quiz.js`:** URL `${API}/departamentos/${id}/preguntas`; `$.getJSON` ya funciona sin cambios de parseo;
   **quitar la llamada duplicada** a `buscarPreguntas` (líneas 24 y 26) y mostrar un aviso en `.fail` (404 o 429).
7. **`mapplic/js/iniciarSesion.js`:** `POST ${API}/sesion`; quitar `JSON.parse`; en `error` mostrar
   `xhr.responseJSON?.mensaje` (401, 422 y 429 traen `mensaje`; el 419 no); **mantener la escritura en `localStorage`**
   hasta la Fase 5. `cerrarSesion()` debe llamar antes a `DELETE ${API}/sesion`.
8. **`miPerfil/script.js`:** `type: 'PATCH'` (o POST con `_method=PATCH`), enviar solo `avatar` (el usuario sale de la
   sesión); quitar `JSON.parse`; añadir un callback `error` (hoy no existe) que lea `responseJSON.message` y trate 401 y
   419; no enviar si `avatar_seleccionado` está vacío.
9. **No tocar** `terminar_juego.js`, `consultar_historial.php` ni `guardar_resultado.php` hasta la Fase 5: dependen de
   `id_usuario` en `localStorage`. Si el login dejara de escribirlo, se rompen los juegos y el historial.
10. **Retirar o bloquear en el servidor los endpoints legados equivalentes** al conmutar: `iniciarSesion.php` (devuelve
    el hash y no tiene límite de intentos: SEC-04 sigue abierto por la ruta vieja) y `cambiarAvatar.php` (escribe en
    `pedigital`, mientras la API nueva lee primero `perfiles_explora`). Mientras convivan, un cambio de avatar hecho en
    el legado no se verá en Laravel.
11. **`QUIZ_INCLUIR_RESPUESTAS` debe seguir en `true`** mientras `quiz.js` corrija en el navegador (con `false`, todas las
    respuestas de opción múltiple contarían como buenas y las de verdadero/falso como malas).

## Riesgos que conviene decidir antes
- **Colegios detrás de una sola IP (NAT):** comparten 60 peticiones/min de lectura y 30 inicios de sesión/min. Un aula
  entera puede recibir 429; ajustar los límites (`RouteServiceProvider`) a la realidad o dimensionarlos por colegio.
- **Proxy inverso:** si se coloca uno, hay que configurar `TrustProxies` con las IP del proxy (no con `*`) para que el
  límite use la IP real del cliente y no sea falsificable por `X-Forwarded-For`.
- **Cabeceras de seguridad** (HSTS, `X-Frame-Options`, `nosniff`, CSP) y HTTPS: se resuelven en el servidor web real.
- **`GET /api/sesion` sin sesión responde 401** (correcto semánticamente). Si el error en consola molesta, se puede
  cambiar a 200 con `success:false`; hoy se mantiene el 401.

## Pruebas manuales recomendadas tras conectar
Flujo completo en navegador: iniciar sesión → abrir una ficha (incluida la del departamento 10, ≈48 MB) → hacer el quiz
→ cambiar el avatar → cerrar sesión → comprobar 401/419 con la sesión caducada. Aún **no hay pruebas de navegador**
automatizadas (pendiente).
