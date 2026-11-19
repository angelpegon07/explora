# INFORME DE PRUEBAS

Fecha: 2026-11-18. Entorno: PHP 8.2.31, MySQL 8.0.41 local, Laravel 10.50.4, PHPUnit. Datos: copias `explora_test` y `pedigital_test` anonimizadas (nunca BD reales).

## Fase 1 — Pruebas de caracterización del PHP legado

**Objetivo:** fijar el comportamiento actual de los 8 endpoints (incluidos sus defectos) para comprobar equivalencia al migrarlos.
**Cómo se ejecuta:**
1. `laravel/tests/Legacy/serve.sh` sirve una **copia temporal** del PHP legado contra las bases `*_test` (los `conexion*.php` originales no se tocan).
2. `php artisan test --testsuite=Characterization`

**Archivos:** `laravel/tests/Characterization/LegacyEndpointsTest.php`, `laravel/tests/Legacy/serve.sh`, `laravel/tests/Legacy/harness/*`, suite añadida en `laravel/phpunit.xml`.

### Resultados

| Estado | Detalle |
|---|---|
| **Exitoso** | 20 de 20 pruebas de caracterización (71 aserciones). Suite completa tras la Fase 2: 33 de 33 (160 aserciones). |
| **Omitido por diseño** | Sin el servidor legado activo las 20 pruebas se omiten (no fallan). Verificado. |
| **No ejecutado** | Camino exitoso del chat con IA (Ollama no está activo: solo se caracterizó el modo sin IA y el error de conexión). Lógica interna de los 8 juegos y de Mapplic en navegador (requiere pruebas de navegador). |
| **Pendiente de validación manual** | Flujo completo en navegador (login → juego → ranking → perfil). |

### Cobertura por endpoint

| Endpoint | Pruebas | Comportamiento fijado |
|---|---|---|
| `consultarInfo.php` | 5 | Ficha con 16 secciones; id inexistente → JSON de error con HTTP 200; id no numérico → error fatal con ruta; inyección SQL efectiva; respuesta ≈9,8 MB como `text/html` |
| `buscarPreguntas.php` | 3 | 30 preguntas por departamento, `correcta` y `remember_token` expuestos; inexistente → cuerpo vacío; sin id → error fatal |
| `chat.php` | 2 | Sin `cadena` → JSON controlado; sin Ollama → mensaje interno de cURL |
| `iniciarSesion.php` | 3 | Respuesta con hash/email/token; mensajes distintos (enumeración); no revisa `estado_usuario` |
| `consultar_historial.php` | 2 | Accesible para cualquier `id_usuario`; inyectable |
| `consultar_ranking.php` | 2 | Filtra por juego/nivel/categoría (máx. 5); inyectable |
| `guardar_resultado.php` | 1 | Acepta puntaje 999999, HTML y `id_usuario` ajeno sin sesión; fecha como texto |
| `cambiarAvatar.php` | 2 | Modifica el avatar de cualquier usuario sin sesión; valor no numérico → error fatal |

### Seguridad de las pruebas
- Solo bases `*_test`. Las pruebas que escriben (`guardar_resultado`, `cambiarAvatar`) limpian y restauran: se verificó después `resultado_juegos` = 1 fila y avatar del usuario 1 = valor original.
- Las inyecciones usadas son de lectura (`OR 1=1`), no destructivas.
- Usuario `legacy_test` (solo sobre las dos bases de prueba) usado únicamente por el arnés; sus credenciales están en `laravel/.env` (ignorado por Git).

### Cómo se usarán al migrar
Cada endpoint migrado debe: (1) mantener la forma de respuesta que el JS actual necesita, y (2) **invertir** las aserciones marcadas "DEFECTO" (p. ej. la inyección ya no funciona, el login ya no devuelve hash). Esas inversiones se escribirán en las pruebas del código Laravel.

### Registro de datos observados
- `estado_usuario`: todos los usuarios de la copia están `ACTIVO` → DAT-01 no es explotable con los datos actuales, solo con cuentas futuras.
- Roles reales en `tipo_usuario`: Estudiante (1.563), Profesor (106), Administrador (2), root (1).
- `resultado_juegos`: 1 fila (invitado, `id_usuario=0`) en la copia.

## Fase 2 — modelos y migración base
- **Migración base:** esquema idéntico al real (422 elementos comparados, 0 diferencias) e idempotente sobre `explora_test`. Estado: Exitoso.
- **Modelos:** 11 pruebas (relaciones, valores contra la tabla, preguntas/opciones, solo lectura de contenido y de `pedigital`, escritura de `ResultadoJuego`, usuario sin hash en serialización, bloqueo de escritura a nivel de MySQL). Estado: Exitoso.
- **Equivalencia con el legado:** 3 departamentos (1, 2, 7) comparados campo a campo y por conteo. Estado: Exitoso. No se compararon los 33 (cada ficha legada pesa ≈10 MB); **No ejecutado** para el resto.
- **No cubierto:** `regiones_*` y `explora.users` (sin modelos), comparación del contenido HTML completo de las secciones largas.

## Estado tras la revisión integral (2026-11-18)
Suite: **34 pruebas, 206 aserciones, todas correctas** con el servidor legado activo; sin él, 21 se omiten y el resto pasa. Añadidas: `ModelosSinChoquesTest` (anti-colisiones relación/columna) y la guarda de seguridad en `TestCase`. **No ejecutado:** prueba negativa de la guarda (que aborte ante una BD no `_test`); contratos de ficha y quiz (Fase 3). Ver [REVISION_INTEGRAL_FASES_0_2.md](REVISION_INTEGRAL_FASES_0_2.md).

**Actualización:** tras `perfiles_explora` la suite es de **37 pruebas, 214 aserciones, todas correctas** (con el servidor legado activo).

## Fase 3 — contrato de la ficha y el quiz
- **Contrato legado ↔ Laravel (15 pruebas, 522 aserciones; la cifra de 512 publicada antes era un error, detectado por el agente de QA):** equivalencia de los **33 departamentos** (ficha y quiz) comparando claves y valores con escalares normalizados a texto (el legado usa mysqli; la API nueva devuelve números como números), todas las claves que lee `verMas.js`, forma que consume `quiz.js`, ausencia de columnas internas, 404 JSON sin trazas, ids inyectados rechazados, límite de peticiones y sin consultas en bucle (≤ 24 por ficha). Estado: Exitoso. Escritas antes que el código (fase roja verificada).
- **Hallazgo al probar:** el legado no puede servir el departamento 10 con 128 MB (FUN-11); el oráculo se ejecuta con 1 GB. El endpoint nuevo sí lo sirve con 128 MB por HTTP real. Estado: Exitoso (comprobación manual, no automatizada).
- **No ejecutado:** prueba en navegador con el frontend conectado; rendimiento bajo carga; medición de tiempos más allá de una muestra (dep. 10: ≈1,3 s, 48,6 MB; dep. 2: ≈0,4 s, 9,8 MB).
- **Suite completa: 52 pruebas, 736 aserciones, todas correctas.**

## Fase 4 — autenticación y avatar
- **16 pruebas (71 aserciones):** login correcto con lista blanca, cookie HttpOnly/SameSite, persistencia y cierre de sesión, 401 sin sesión, respuesta idéntica para usuario inexistente y clave incorrecta, validación (422, incluido `usuario` como arreglo), cuenta inactiva (se prepara cambiando `estado_usuario` en `pedigital_test` con un usuario de escritura **solo de pruebas** y se restaura), límite de intentos (429 + `Retry-After`, sin bloquear a otros usuarios), CSRF efectivo (419 sin token), avatar solo con sesión, sobre el propio usuario, sin tocar pedigital, sin poder cambiar el de otro y rechazo de valores inválidos, `Usuario` autenticable sin «recordarme». Estado: Exitoso. Escritas antes del código (fase roja verificada).
- **Verificación manual por HTTP real** (cookies y token CSRF reales): 10 pasos correctos.
- **No ejecutado:** prueba en navegador con el frontend conectado; carga/ataque de fuerza bruta distribuido; pruebas con HTTPS y cookie `Secure`.
- **Suite completa: 68 pruebas, 806 aserciones, todas correctas.**

## Fase 5 — juegos
- **19 pruebas (`JuegosTest`):** invitado guarda (formato de fecha del legado); con sesión la identidad sale de la sesión y se ignora el cuerpo (nombre, grado, avatar, `id_usuario`); sin nombre/grado/avatar con sesión; 29 entradas inválidas rechazadas con 422 sin guardar nada (juego, nivel, puntaje, tiempo, categoría, nombre, grado, avatar, incluidos HTML e inyección SQL); invitado sin datos obligatorios; los 8 juegos y 4 niveles reales; categorías con tildes y signos; límite de 10/min (429 + Retry-After); CSRF (419); cuenta desactivada (401); ranking top 5 ordenado y con solo 5 campos; filtros; parámetros inválidos/inyectados (422); ranking público y limitado; historial con sesión obligatoria, solo del usuario y ordenado, sin revelar otros campos; historial vacío; **equivalencia con el legado** del ranking y del historial. Estado: Exitoso. Escritas antes del código (fase roja verificada).
- **Verificación manual por HTTP real:** 9 pasos correctos (guardar con CSRF, rechazo de HTML, 419 sin token, ranking, inyección rechazada, historial 401 sin sesión, guardar con sesión ignorando nombre e id, historial solo propio, ranking combinado).
- **No ejecutado:** prueba en navegador con el frontend conectado; carga real sobre el ranking con miles de filas (no hay índice sobre `nombre_juego, nivel, categoria`, campos `text`: DAT-03).
- **Suite completa: 99 pruebas, 1.096 aserciones, todas correctas.**

## Fase 7 — pruebas en navegador y servidor de desarrollo
- **Playwright (36 pruebas, Chromium de Playwright 1.60.0):** `bloqueos` (410 en los 7 PHP sustituidos con GET y POST; 404 en `.env`, `.git`, documentación, `conexion*.php`, `/x.php/y.css` y sin distinguir mayúsculas; ningún cuerpo filtra rutas), `sesion-ficha-quiz` (login sin exponer el hash, ficha con 500 simulado cierra la carga y avisa, ficha real del departamento 2, quiz por teclado, doble clic en opción correcta suma exactamente 1, opción incorrecta suma 0 y se anuncia, aviso en el mapa climático, cierre de sesión), `perfil` (datos del usuario, avatar por teclado persiste, sin sesión no llama al historial, sin cookie XSRF se avisa de sesión caducada), `juegos` (invitado guarda y aparece en el ranking, fallo 500 restaura el botón, modal sin `backdrop` en Bootstrap 4 y 5) y `xss` (ranking e historial con HTML sembrado se ven como texto; sin diálogos nativos). Estado: **Exitoso**, 36/36 en dos ejecuciones completas seguidas (35/35 en tres ejecuciones anteriores a la última corrección).
- **PHPUnit (118 pruebas, 1.136 aserciones; incluye las 2 pruebas de ejemplo del esqueleto de Laravel, 116 propias):** añade `ServidorDesarrolloTest` (19: arranca el router y comprueba cada bloqueo) y el límite configurable de lectura. Estado: Exitoso.
- **Prueba de mutación** sobre `quiz.js` (ver REGISTRO_CAMBIOS.md): la prueba del doble clic detecta el defecto.
- **Inestabilidad encontrada y resuelta:** dos pruebas de perfil fallaban de forma intermitente por carreras de la propia prueba (foco de Bootstrap y cookie XSRF re-emitida); ahora esperan eventos en lugar de suponer tiempos.
- **No ejecutado / límites:** Firefox y Safari; un Apache real; prueba de carga; la ficha del departamento 10 (≈48 MB) en navegador; prueba visual/responsive.

## Prueba de los `.htaccess` con Apache real (Fase 7B, 2026-11-19)
Apache 2.4.56 del sistema (macOS) en 127.0.0.1:8095, PHP por CGI (`php-cgi`), bases `*_test`, `AllowOverride All`. Resultado: **todo conforme**.
- Sitio y estáticos: 200. `/laravel/` (`.env`, `composer.json`, `storage`, `vendor`), `DOCUMENTACION_MEJORAMIENTO/` y `conexion*.php`: 403. `/.git/HEAD` y `/.env`: 404.
- Los 7 PHP sustituidos: 410. Sin listado de directorios y sin MultiViews (`/index`, `/api/sesion.php`: 404).
- `/api/...` llega a Laravel (401 sin sesión, 404 de Laravel en rutas inexistentes, 419 sin CSRF); ciclo POST/GET/DELETE `/api/sesion` correcto (200, 200, 200, luego 401).
- **No comprobado:** mod_php/php-fpm, cabecera `Authorization`, ausencia de `mod_rewrite` (bloque de respaldo), otros sistemas o versiones, Firefox y Safari (Playwright 1.60 no trae Firefox para macOS 13; `safaridriver` requiere activarlo con permisos de administrador).

## Ronda de pruebas de navegador con 3 agentes (2026-11-19)
Áreas: mapas y fichas/quiz (escritorio y móvil), sesión/perfil/7 juegos, seguridad y robustez. **Sin regresiones de mapas.** Defectos nuevos hallados y corregidos (pruebas: `DefectosQaTest`, `juegos.spec.js`):
- Juegos: los avisos del modal de guardar quedaban detrás del modal y, en banderas-color, un `Swal` sustituía al «Felicidades» y redirigía la página → ahora el aviso va dentro del modal (`#aviso_guardar`).
- Laberinto: tiempo negativo (<4 s) daba 422 → `Math.max(0, …)`.
- Login con emoji → 500 (SQLSTATE 3988, `pedigital` es utf8mb3) → 422 por validación.
- Ids enormes en `/api/departamentos/{id}/…` → 500 → ahora 404 (`[0-9]{1,9}`).
- 429/419/404 de la API en inglés → español (`Handler`); peticiones sin tiempo máximo → 120 s; historial engañoso con sesión caducada → se vacía.
**Previos, no corregidos (no son de la migración):** menú «Volcanes» comentado en las páginas; fuente sin límite en los botones de accesibilidad; desborde horizontal por el panel `#opciones_mapa`; 404 de `ventana_mundo.mp3` y de `puzzle/mapa/` (carpeta inexistente); errores de consola de panzoom/tag-it/ahorcado; sopa de letras y perfil poco usables en móvil; `X-Powered-By` con versión (se resuelve en el servidor con `expose_php=Off`).
Resultado final: PHPUnit 121 (1.143 aserciones), Playwright 53/53, Pint limpio.
