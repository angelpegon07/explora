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
