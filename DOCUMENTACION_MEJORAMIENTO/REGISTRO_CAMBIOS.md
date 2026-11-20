# REGISTRO DE CAMBIOS

| Fecha | Hallazgo | Archivos | Descripción / motivo | Pruebas | Resultado |
|---|---|---|---|---|---|
| 2026-11-18 | Plan, Fase 0 | etiqueta Git `pre-migracion-laravel` (sobre `main`, `c29b29e`); rama `migracion-laravel` | Punto de recuperación y rama de trabajo. Sin commits; los 3 `conexion*.php` con cambios ajenos siguen sin confirmar. | — | Exitoso |
| 2026-11-18 | Plan, Fase 0 / TEC-05, CAL-01 | `laravel/` (nuevo, sin confirmar) | Esqueleto Laravel 10 (`laravel/laravel` v10.3.3, `laravel/framework` 10.50.4) instalado con Composer; clave de aplicación generada en `.env` local (ignorado por Git). | `php artisan test`: 2 de 2 pruebas del esqueleto | Exitoso |
| 2026-11-18 | Decisión de riesgo | `laravel/composer.json` (`config.policy.advisories.ignore-id`) | Por decisión expresa del responsable (alinear con pednia/ambientes en Laravel 10) se ignoran **solo** los 8 avisos de seguridad que bloqueaban la instalación; no se desactivó el control en general. | `composer install` correcto | Exitoso (riesgo aceptado, ver abajo) |

No se modificó código del sistema actual, ni la base de datos, ni credenciales.

## Riesgo aceptado: Laravel 10 con avisos sin parche
Avisos ignorados (ajustado tras el barrido: solo los **5** que afectan a 10.50.4): PKSA-yxbx-3c9s-cjt9 (inyección de salida en Concurrency), PKSA-d5tc-s1qs-h781 (XSS en página de depuración), PKSA-m5cs-t1y6-qpcs (URLs firmadas), PKSA-3r5d-mb8f-1qw9 y PKSA-mdq4-51ck-6kdq (CRLF en regla `email`). Los otros 3 que se ignoraron al inicio (PKSA-b41t-wbzk-9q51, PKSA-8qx3-n5y5-vvnd, PKSA-w7xr-vk7n-rstm) ya están corregidos en 10.50.4 y se retiraron de la lista.
Mitigaciones de diseño en este proyecto:
- `APP_DEBUG=false` fuera del entorno local (XSS en la página de depuración).
- No usar URLs firmadas temporales ni `TokenGuard` con credenciales no validadas.
- No confiar en la regla `email` para cabeceras de correo; no enviar correo con datos del usuario sin saneado propio.
- Validar archivos subidos por contenido y no solo por la regla de validación (no hay subidas hoy).
- Repetir `composer audit` en cada fase y planificar la actualización de Explora y pednia a una versión con soporte.

## Fase 0 — entorno de pruebas (2026-11-18)

| Hallazgo | Cambio | Detalle | Resultado |
|---|---|---|---|
| TEC-05, FUN-08 | Bases de prueba locales | MySQL 8.0.41 local no tenía `explora` ni `pedigital`. Se crearon `explora_test` (39 tablas, 32 FK, 33 departamentos, 1.136 preguntas) y `pedigital_test` (1 tabla, 1.672 usuarios) importando los dumps con el nombre de BD reescrito. Los sistemas reales y los dumps no se modificaron. | Exitoso |
| Privacidad | Anonimización | En ambas copias: nombre → `Usuario N`, login → `usuarioN`, correo → `uN@example.test`, `remember_token` nulo y contraseña = hash bcrypt de una clave de prueba común; `jugador` en resultados → `Jugador N`. Solo afecta las copias de prueba. | Exitoso |
| SEC-02, SEC-03 | Usuarios MySQL de mínimo privilegio | `explora_app` (todos los privilegios solo sobre `explora_test`) y `pedigital_ro` (solo `SELECT` sobre `pedigital_test`). Sus contraseñas aleatorias están únicamente en `laravel/.env` (ignorado por Git). La clave de `root` no se guardó en ningún archivo. | Exitoso |
| Plan, Fase 0 | Conexión `pedigital` | `laravel/config/database.php` (conexión de solo lectura) y `laravel/.env.example` (sin secretos). Verificado: lectura de 1.672 usuarios correcta y `CREATE TABLE` denegado. | Exitoso |

### Incidente (corregido)
Durante la verificación de la conexión `explora` ejecuté por error una sentencia `DROP TABLE list_departamentos` sobre `explora_test` (la prueba pretendía comprobar permisos y no debió incluir una sentencia destructiva). Afectó solo a la copia de pruebas. Se restauró la tabla desde el dump: 33 registros, codificación UTF-8 verificada, 39 tablas y 32 FK intactas. Lección: las comprobaciones de permisos no se harán con sentencias que puedan destruir datos.

### Advertencia para la Fase 2
**No ejecutar `php artisan migrate` con las migraciones por defecto de Laravel** sobre `explora_test`: crean `users`, `password_reset_tokens`, etc. y chocan con tablas existentes (`users`). Se sustituirán por una migración base del esquema real.

## Fase 1 — pruebas de caracterización (2026-11-18)

| Hallazgo | Archivos | Descripción | Pruebas | Resultado |
|---|---|---|---|---|
| FUN-08, CAL-01 | `laravel/tests/Characterization/LegacyEndpointsTest.php`, `laravel/tests/Legacy/serve.sh`, `laravel/tests/Legacy/harness/{conexion,conexion_pedigital}.php`, `laravel/phpunit.xml` (suite `Characterization`), `laravel/.env(.example)` (variables `LEGACY_*`) | 20 pruebas que fijan el comportamiento actual de los 8 endpoints, contra una copia temporal del legado y bases `*_test`. Se creó el usuario MySQL `legacy_test` (solo bases de prueba). Hallazgo nuevo REN-03 (ficha ≈9,8 MB). | 20/20 (con servidor); 20 omitidas (sin servidor); suite completa 22/22 | Exitoso |

No se modificó el PHP legado ni los `conexion*.php`.

## Barrido de Fases 0 y 1 (2026-11-18)

| Verificación | Resultado |
|---|---|
| Secretos (clave de `root`, claves de usuarios de prueba) en documentación o código no ignorado | Ninguno; solo en `laravel/.env` (ignorado) |
| `.env`, `vendor/`, `node_modules/` ignorados por Git | Sí |
| `composer validate` | Válido |
| `composer audit` | 5 avisos activos, todos ignorados de forma explícita; 0 sin ignorar |
| Lista de avisos ignorados | Reducida de 8 a 5 (los 3 retirados ya estaban corregidos en 10.50.4) |
| Estilo (Pint) en `tests/` y `config/database.php` | Fallaba en 3 archivos; corregido con `pint` |
| Documentación | Corregida una afirmación inexacta (SEC-07: «5 endpoints con error fatal» → 3 fatales + 3 con advertencias) |
| Pruebas | Suite completa verde tras los ajustes |

## Fase 2 — datos y modelos (2026-11-18)

| Hallazgo | Archivos (`laravel/`) | Descripción | Pruebas | Resultado |
|---|---|---|---|---|
| TEC-05 | `database/migrations/2026_11_18_000001_baseline_esquema_explora.php`; se retiraron las 4 migraciones por defecto del esqueleto | Migración base **idempotente** (crea solo las tablas que no existen; `down()` no elimina nada) generada desde el esquema real: 39 tablas, 32 FK. Las migraciones por defecto (`users`, `password_reset_tokens`, `failed_jobs`, `personal_access_tokens`) chocaban con tablas reales y se retiraron. | Sobre una BD vacía temporal: 422 elementos de esquema (columnas, tipos, nulos, valores por defecto, FK con ON DELETE, índices, collation y motor) **idénticos** a `explora_test` (0 diferencias). Sobre `explora_test`: no modificó ninguna tabla (40 = 39 + `migrations`; datos intactos). BD temporal eliminada. | Exitoso |
| TEC-01, TEC-03 | `app/Models/Explora/*` (26 clases), `app/Models/Pedigital/Usuario.php`, `app/Models/Concerns/SoloLectura.php` | Capa Eloquent con relaciones desde la BD real; modelos de contenido y `pedigital` de solo lectura; `Usuario` oculta hash/token/correo. | `tests/Feature/ModelosExploraTest.php`: 11 pruebas | Exitoso |
| FUN-08 | `tests/Feature/ModelosExploraTest.php` | Prueba de **equivalencia** con el endpoint legado en 3 departamentos (nombre, ids, clima, historia, capital y conteo de sitios, festividades, personajes, fauna, flora y etnias). | Con el servidor legado activo | Exitoso |

Suite completa: **33 pruebas, 160 aserciones, todas correctas** (22 de Fases 0–1 + 11 de modelos; las 21 que dependen del servidor legado se omiten solas sin él).

### Defecto detectado y corregido durante la fase
Las relaciones `clima` y `geografia` de `DepartamentoDescripcion` no funcionaban: la tabla tiene columnas con esos nombres y Eloquent devolvía el atributo en lugar de la relación. Se renombraron `climaDetalle()` y `geografiaDetalle()`.

### Notas
- Sanctum registra su propia migración (`personal_access_tokens`) desde el paquete; **no se ha ejecutado sobre `explora_test`** (se migró solo la ruta de la base). Se decidirá en la Fase 4.
- Siguen sin corregirse en BD: FK autorreferenciadas de `regiones_*` (DAT-02), FK ausentes (DAT-04), `fecha` como texto (DAT-03). Requieren migración de datos con autorización (propuesta pendiente).
- `app/Models/User.php` del esqueleto sigue sin uso; se reemplazará en la Fase 4.

## Revisión integral de las Fases 0–2 (2026-11-18)
Revisión con 5 agentes y correcciones aplicadas; detalle, defectos y decisiones en [REVISION_INTEGRAL_FASES_0_2.md](REVISION_INTEGRAL_FASES_0_2.md). Archivos tocados en `laravel/`: `app/Models/Explora/{DepartamentoDescripcion,SeccionDepartamento,OpcionMultiple,OpcionVerfal,Pregunta}.php`, `app/Models/Concerns/SoloLectura.php`, `database/migrations/2026_11_18_000001_baseline_esquema_explora.php`, `tests/TestCase.php`, `tests/Feature/{ModelosExploraTest,ModelosSinChoquesTest}.php`, `tests/Legacy/serve.sh`, `phpunit.xml`, `.env.example`. Resultado: 34 pruebas / 206 aserciones correctas. Correcciones de documentación en 8 archivos.

## Decisión del avatar → `perfiles_explora` (2026-11-18)

| Hallazgo | Archivos (`laravel/`) | Descripción | Pruebas | Resultado |
|---|---|---|---|---|
| SEC-01, SEC-03 (cambiarAvatar) | `database/migrations/2026_11_18_000002_crear_perfiles_explora.php`, `app/Models/Explora/PerfilExplora.php`, `tests/Feature/PerfilExploraTest.php` | El avatar se guarda en `explora` en vez de escribir en `pedigital`. Tabla nueva aditiva; sin perfil se lee el valor de pedigital (sin copiar datos). Migración ejecutada solo sobre `explora_test` (41 tablas = 39 + `migrations` + `perfiles_explora`). | 3 pruebas nuevas (fallback, prioridad sin tocar pedigital, unicidad). Suite: **37 pruebas, 214 aserciones, todas correctas** | Exitoso |

Nota de coexistencia: ver INVENTARIO_SISTEMA.md (el legado sigue escribiendo en pedigital hasta retirarlo).

## Fase 3 — lectura pública: ficha y quiz (2026-11-18)

| Hallazgo | Archivos (`laravel/`) | Descripción | Pruebas | Resultado |
|---|---|---|---|---|
| SEC-01, SEC-07, FUN-02, TEC-03 | `routes/api.php`, `app/Http/Controllers/Api/{FichaDepartamento,PreguntasDepartamento}Controller.php`, `app/Http/Resources/{FichaDepartamento,PreguntasDepartamento}Resource.php`, `app/Http/Middleware/MemoriaParaFichas.php`, `app/Exceptions/Handler.php`, `config/explora.php` | `GET /api/departamentos/{id}/ficha` y `/preguntas`: misma forma que `consultarInfo.php` / `buscarPreguntas.php`, con **lista blanca** de columnas (sin `remember_token` ni marcas de tiempo), solo ids numéricos, límite de peticiones (60/min) y JSON siempre (incluidos 404). Ids inexistentes → 404 con JSON (el legado: 200 con error o cuerpo vacío). `correcta`/`respuesta` se mantienen mientras el cliente corrija en el navegador (`QUIZ_INCLUIR_RESPUESTAS`, SEC-06). | Escritas **antes** que el código (en rojo: 9 fallaban): `tests/Contract/{Ficha,Quiz}ContractTest.php`, 15 pruebas: equivalencia campo a campo con el legado en los **33** departamentos, todas las claves que lee `verMas.js` (extraídas del propio archivo), sin columnas internas, 404 sin trazas, sin consultas en bucle, límite de peticiones | Exitoso |
| FUN-11 | `app/Http/Middleware/MemoriaParaFichas.php`, `tests/Legacy/serve.sh` | El departamento 10 pesa 46 MB y el legado agota 128 MB. El endpoint nuevo eleva el límite a 512 MB **solo en esa ruta** (pico medido 225 MB); verificado por HTTP real con `memory_limit=128M`: HTTP 200, 48,6 MB, JSON válido. El arnés del legado usa 1 GB para servir de oráculo. | Comprobación manual por HTTP; contrato del departamento 10 contra el oráculo | Exitoso (paliativo; la solución de fondo es DAT-07) |

Suite completa: **52 pruebas, 736 aserciones, todas correctas.** `APP_DEBUG=false` forzado en `phpunit.xml` (con `true` el 404 devolvía una traza con rutas: detectado al probar por HTTP).

**Sin cambiar el frontend todavía.** Los endpoints nuevos no están conectados: `verMas.js` hace `JSON.parse(response)` sobre una respuesta que el legado sirve como `text/html`; con `application/json` jQuery ya entrega un objeto y fallaría. Al conmutar (Fase 7 o un adaptador previo) hay que quitar ese `JSON.parse` y manejar el 404 JSON.

## Fase 4 — autenticación por sesión y avatar (2026-11-18)

| Hallazgo | Archivos (`laravel/`) | Descripción | Pruebas | Resultado |
|---|---|---|---|---|
| SEC-03, SEC-04, DAT-01, SEC-10 | `app/Models/Pedigital/Usuario.php` (ahora `Authenticatable`), `config/auth.php` (proveedor `usuarios`), `app/Http/Controllers/Api/{Sesion,PerfilAvatar}Controller.php`, `app/Http/Requests/{Login,CambiarAvatar}Request.php`, `app/Http/Resources/UsuarioSesionResource.php`, `app/Providers/RouteServiceProvider.php` (limitador `login`), `app/Http/Middleware/Authenticate.php`, `routes/api.php`, `config/explora.php` | `POST/GET/DELETE /api/sesion` y `PATCH /api/perfil/avatar`. **Sesión de servidor** (cookie cifrada, HttpOnly, SameSite=Lax) + **CSRF**; el usuario sale de la sesión, nunca del cliente. Login: respuesta con lista blanca de 6 campos (sin hash, correo ni token); mismo cuerpo 401 para usuario inexistente, clave errónea o cuenta inactiva; comparación bcrypt también con un hash señuelo para igualar tiempos; regeneración del id de sesión; límite 5 intentos/min por usuario+IP y 30/min por IP (429 JSON). Se respeta `estado_usuario`. El avatar valida entero 1–35, solo actúa sobre el usuario de la sesión y se guarda en `perfiles_explora` (pedigital no se toca). `Usuario::getRememberToken()` devuelve null para que cerrar sesión no escriba en pedigital. Se retiraron `App\Models\User` y `UserFactory` del esqueleto. | Escritas antes que el código (16 en rojo): `tests/Feature/AutenticacionTest.php`, 16 pruebas, 71 aserciones. Verificado además por HTTP real con cookies: 419 sin token CSRF, 401/200/422, cookie `HttpOnly; SameSite=Lax`, sesión invalidada tras cerrar | Exitoso |

### Defecto propio detectado por las pruebas y corregido
El limitador de login convertía `usuario` a texto sin comprobar el tipo: un cliente que enviara un arreglo (`usuario[]=x`) provocaba un **error 500**. Ahora usa una cadena vacía si no es texto y el formulario responde 422.

### Notas para conectar el frontend (aún no conectado)
- `iniciarSesion.js` y `miPerfil/script.js` hacen `JSON.parse(response)`: con `application/json` jQuery ya entrega un objeto. Además, el éxito/fallo llega con HTTP 200/401 (el legado siempre 200): el `error` de `$.ajax` debe leer `responseJSON.mensaje`.
- Antes del primer POST/PATCH hay que hacer `GET /api/sesion` (entrega la cookie `XSRF-TOKEN`) y enviarla en `X-XSRF-TOKEN`.
- `localStorage` deja de ser la fuente de verdad de la sesión; los endpoints de Fase 5 (historial, guardar resultado) leerán el usuario de la sesión.
- Sitio y API deben servirse desde el mismo origen (cookies `SameSite=Lax`). En HTTPS: `SESSION_SECURE_COOKIE=true`.
- Pendiente de decisión del responsable: rutas de producción (¿el sitio estático y Laravel bajo el mismo dominio?).

### Documentación del código (pedido del responsable, 2026-11-18)
Se auditó todo el código nuevo con un escáner de **presencia** de bloque (clases/métodos): **74 elementos** sin documentar → **0 en código propio**. *(Matiz: ese escáner no medía @param/@return ni la veracidad del texto; ver la revisión posterior, que los completó.)* (quedan 8 archivos de esqueleto de Laravel que no se modificaron: `EncryptCookies`, `TrimStrings`, `TrustHosts`, `TrustProxies`, `ValidateSignature`, `VerifyCsrfToken`, `RedirectIfAuthenticated`, `PreventRequestsDuringMaintenance`). Se añadió `laravel/README.md` (estructura, API, pruebas, convenciones y estándar de documentación) y todo el código de la Fase 4 se escribió documentado desde el inicio. El estándar está en el README (convención 1) y en la memoria del proyecto.

Suite completa: **68 pruebas, 806 aserciones, todas correctas** (con el servidor legado activo).

## Revisión integral de las Fases 3 y 4 (2026-11-18)

Revisión con **5 agentes independientes de solo lectura**: seguridad de autenticación (Opus), contrato de API y compatibilidad con el frontend (Opus), QA que ejecuta la suite (Sonnet), auditoría de la documentación del código (Haiku) e integridad de Git y entorno (Haiku). Los informes son salida de modelo: cada afirmación se contrastó con el código antes de actuar. Dos afirmaciones de los agentes **eran incorrectas** y se rechazaron con evidencia: que CSRF se evalúa antes que `auth` (se midió dos veces —en pruebas y por HTTP real— que sin sesión responde 401; con sesión válida y token viejo sí 419) y que `contenido` de la etnografía no es LONGBLOB (la migración lo convierte con `ALTER` y la comparación de esquema dio 0 diferencias).

### Defectos de código hallados y corregidos
| # | Origen | Defecto | Corrección | Prueba |
|---|---|---|---|---|
| 1 | Seguridad | El límite de intentos por cuenta se esquivaba cambiando de mayúsculas/acentos (la base no los distingue) o de IP | Clave normalizada (`App\Support\NormalizaLogin`) y **límite por cuenta**: 20 fallos/15 min, comprobado antes de la clave, también para usuarios inexistentes (no delata cuáles existen); un login correcto reinicia el contador | `test_el_limite_por_cuenta_*`, `test_un_login_correcto_reinicia_el_contador_de_fallos` |
| 2 | Seguridad | `TrimStrings` recortaba los espacios de `contrasena` (el legado no) | `contrasena` excluida de `TrimStrings` y de `dontFlash` | `test_la_contrasena_no_se_recorta` |
| 3 | Seguridad (confirmado con datos reales) | 1 de los 1.672 hashes es `$2a$12$`: Laravel solo reconoce `$2y$` y lo trataba como error, dejando fuera a esa persona (el legado la admitía). Las demás son `$2y$10$` | `Usuario::getAuthPassword()` normaliza `$2a$`/`$2b$` a `$2y$` (`$2x$` no). *Un primer intento con `preg_replace` fallaba porque `$2` se interpreta como referencia de grupo: lo encontró la prueba.* | `test_los_hashes_bcrypt_2a_y_2b_se_aceptan` |
| 4 | Seguridad | Una cuenta desactivada conservaba su sesión abierta (DAT-01 solo se comprobaba al iniciar sesión) | Middleware `cuenta.activa` (relee el estado en cada petición con sesión y cierra la sesión) | `test_una_cuenta_desactivada_pierde_la_sesion_abierta` |
| 5 | Seguridad | Una cuenta con hash vacío se comparaba con el hash señuelo | Una cuenta sin hash nunca es utilizable | `test_una_cuenta_sin_hash_no_puede_entrar` |
| 6 | Seguridad | Dos primeras peticiones simultáneas de avatar podían dar 500 por la clave única | `retry()` en `PerfilAvatarController` | (cubierto por unicidad en `PerfilExploraTest`) |
| 7 | Seguridad | `avatarDe()` podía devolver 0 (pediría `0.png`) | Valores < 1 → 1 | `test_un_avatar_vacio_o_cero_en_pedigital_se_sustituye_por_el_1` |
| 8 | Seguridad | CORS abierto (`*`); `SESSION_SECURE_COOKIE` ausente de la plantilla; `.env.*` no ignorado | CORS solo mismo origen (`CORS_ALLOWED_ORIGINS`), variables documentadas en `.env.example`, `.env.*` ignorado (salvo `.env.example`); permisos `600` en `.env` y `700` en las sesiones | — |
| 9 | Documentación | `EtnografiaItem` heredaba `update_at`, pero su tabla usa `updated_at` | Hereda de `SeccionDepartamento`; documentadas las 5 listas que sí usan `update_at` | cubierto por la comparación de esquema |
| 10 | QA | La prueba de cuenta inactiva se omitía en silencio sin credencial de pruebas | Ahora **falla** con un mensaje claro | — |
| 11 | QA | El `tearDown` borraba perfiles de los usuarios de prueba sin restaurarlos | Trait `RespaldaPerfiles` (respalda y restaura) | — |
| 12 | Documentación | 15 textos inexactos (cifras de modelos, comentarios obsoletos, README) | Corregidos; README con variables de entorno, límites, 401/419, `cuenta.activa` | — |
| 13 | Documentación | 27 relaciones/métodos sin `@return` descriptivo y 65 pruebas sin docblock | Añadidos (Pint elimina los `@return` que solo repiten el tipo, así que se escriben con descripción) | — |

### Pruebas nuevas (11) y cifras reales
Regeneración del id de sesión, CSRF del cierre de sesión, límite por cuenta (con cambio de IP y mayúsculas), límite por cuenta para usuarios inexistentes, reinicio del contador, contraseña sin recorte, cuenta desactivada con sesión abierta, cuenta sin hash, hashes `$2a$`/`$2b$`, bordes del avatar, avatar 0, y el corte a la petición 61 del límite de lectura. **Suite completa: 80 pruebas, 983 aserciones, todas correctas** (ejecutada con el servidor legado activo; sin residuos: `perfiles_explora` vacía, `resultado_juegos` con su fila original, todos los usuarios ACTIVO). Contract: 522 aserciones (no 512).

### Pendiente / decisiones
Ver [GUIA_CONMUTACION_FRONTEND.md](GUIA_CONMUTACION_FRONTEND.md) (11 pasos y riesgos: NAT en colegios, proxy inverso, cabeceras y HTTPS). Siguen abiertos: SEC-04 y SEC-03 en el legado hasta retirar `iniciarSesion.php`/`cambiarAvatar.php`/historial/guardar resultado; prueba negativa de la guarda de `TestCase`; pruebas de navegador. Git: confirmar siempre por ruta (nunca `-A`), revisar `git diff --cached --name-only` y repetir `git add -A --dry-run` antes del commit.

## Fase 5 — juegos: resultados, ranking e historial (2026-11-18)

| Hallazgo | Archivos (`laravel/`) | Descripción | Pruebas | Resultado |
|---|---|---|---|---|
| SEC-01, SEC-03, SEC-05, SEC-06, FUN-09, FUN-01 (parcial) | `routes/api.php`, `app/Http/Controllers/Api/{ResultadoJuego,Ranking,Historial}Controller.php`, `app/Http/Requests/{GuardarResultado,Ranking}Request.php`, `app/Http/Requests/Concerns/ReglasDeJuego.php`, `app/Providers/RouteServiceProvider.php` (limitador `resultados`), `config/explora.php` | `POST /api/resultados`, `GET /api/ranking` y `GET /api/historial`. **Identidad desde la sesión** (el id de usuario, nombre, grado y avatar del cuerpo se ignoran si hay sesión); **modo invitado conservado** (FUN-09) con datos estrictos y 10 partidas/min por IP; listas cerradas para `juego` y `nivel` (los 8 juegos y 4 niveles reales, extraídos de `juegos/`), categoría y nombre sin HTML ni comillas, puntaje 0–1000 y tiempo 0–14400 s; ranking público con solo 5 campos (sin `id_usuario`); historial solo del usuario de la sesión (ignora `?id_usuario=`), orden por `id` descendente y máx. 500 filas; `fecha` en el formato del legado. Sin SQL concatenado. | Escritas antes que el código (17 en rojo): `tests/Feature/JuegosTest.php`, 19 pruebas, incluida la equivalencia de ranking e historial con el legado. Verificado por HTTP real con cookies y CSRF (9 pasos) | Exitoso |

### Decisiones y límites (para el responsable)
- **Honestidad del puntaje (SEC-06): límite aceptado.** El navegador calcula puntaje y tiempo; el servidor solo descarta valores imposibles. Evitar el fraude exige mover la lógica de los juegos al servidor (fuera del alcance de esta migración).
- **Las peticiones inválidas también cuentan para el límite** de 10/min (el límite se evalúa antes de validar): es deliberado contra el abuso; la prueba rota la IP para probar la validación.
- **Datos existentes sin sanear:** la validación protege las filas nuevas. Las filas ya guardadas por el legado pueden contener HTML (SEC-05); el cliente debe escapar al mostrarlas (Fase 7) y una limpieza de datos requeriría autorización.
- **`fecha` sigue siendo texto** (`d-m-Y H:i:s`, UTC): cambiarla a `datetime` es una migración de datos (DAT-03).
- **Grado vacío:** las cuentas sin grado (p. ej. profesores) se guardan con grado vacío; el ranking mostraría «° Grado».
- **Zona horaria:** el legado usó la del servidor (UTC en la copia de pruebas); producción por confirmar (PV).

Suite completa: **99 pruebas, 1.096 aserciones, todas correctas** (con el servidor legado activo). Sin residuos en las bases de prueba.

## Fase 6 — aplazada por decisión del responsable (2026-11-18)
No se implementa nada. El chat con IA y el corrector se tratarán en un trabajo aparte enfocado solo en IA; el legado (`chat.php`, `chat copy.php`, `corrector.html`) no se modifica. Riesgos que quedan abiertos y se aceptan de forma explícita: SEC-02 (credenciales en `chat.php` y `chat copy.php`), SEC-08, SEC-11, FUN-03, FUN-04, FUN-05. Resumen del trabajo futuro, con lo que ya se sabe y las decisiones pendientes: [PENDIENTE_TRABAJO_IA.md](PENDIENTE_TRABAJO_IA.md).

## Fase 7 — conmutación segura del frontend a la API Laravel (2026-11-19)

Organización (pedido del responsable): el líder (Opus) diseñó, revisó cada diff y ejecutó las suites; los lotes de código los escribió **Sonnet** y la exploración la hizo **Haiku**, sobre archivos disjuntos. **Esta fase no tocó** los 3 `conexion*.php` (siguen con los cambios ajenos sin confirmar que ya tenían antes de empezar el proyecto) ni el chat. Alcance acordado: «conmutación segura»; Vite, librerías, Mapplic y medios van a la Fase 7B.

| Lote | Archivos | Descripción | Resultado |
|---|---|---|---|
| A. Infraestructura | `laravel/servidor-desarrollo.php`, `.htaccess` (raíz), `laravel/.htaccess`, `laravel/public/.htaccess`, `DOCUMENTACION_MEJORAMIENTO/.htaccess`, `mapplic/php/.htaccess`, `juegos/php/.htaccess`, `laravel/config/explora.php` + `RouteServiceProvider` (límite `API_POR_MINUTO`, 300), `laravel/tests/Feature/ServidorDesarrolloTest.php`, `DESPLIEGUE_APACHE.md` | Sitio y API en el **mismo origen**; 410 a los 7 PHP sustituidos (sin distinguir mayúsculas ni por `PATH_INFO`), 404 a `laravel/`, `.git`, documentación, `conexion*.php` y otros `.php`; el router **nunca devuelve `false` ante un segmento PHP** (así `php -S` no ejecuta el legado con sus credenciales); con `EXPLORA_SOLO_BD_PRUEBA=1` aborta si las bases no son `*_test`. | 19 pruebas PHPUnit; `.htaccess` **sin probar con un Apache real** (no hay Apache en este equipo) |
| B. Cliente común | `js/explora-api.js` | `window.ExploraApi` (URL desde cualquier profundidad, CSRF leído de la cookie en cada petición, errores unificados, sincronización de sesión, `escaparHtml`, `avatarValido`). Sin `innerHTML`/`eval`; el token no se guarda. | Revisado por el líder |
| C. Fichas y quiz | `mapplic/verMas.js`, `mapplic/js/quiz.js`, `mapplic/quiz.css` y las 6 páginas raíz | Sin `JSON.parse`; en error se cierra la carga y se avisa; ids validados como enteros; quiz con botones reales, foco, `aria-live`, primer clic bloquea (corrige el doble conteo) y una sola llamada a preguntas; aviso claro en las 5 páginas sin selector de departamento; `lang="es"`. El contenido editorial sigue insertándose como HTML por diseño. | Playwright |
| D. Sesión y perfil | `mapplic/js/iniciarSesion.js`, `miPerfil/script.js`, `miPerfil.html`, `juegos/index.html` | Login/cierre por la API (DELETE), `tipo_modal` eliminado, historial sin `id_usuario` y con `textContent`, avatar por PATCH con avatares accesibles, aviso «Debe iniciar sesión». | Playwright |
| E. Juegos | `juegos/js/terminar_juego.js` y las 7 páginas de juegos | Guardar con los nombres nuevos (invitado envía nombre/grado/avatar), ranking con `createElement`/`textContent` y avatar validado, modal compatible con Bootstrap 4 y 5, errores visibles. | Playwright |
| F. Pruebas en navegador | `laravel/package.json`, `package-lock.json`, `playwright.config.js`, `laravel/tests/Browser/*` | 36 pruebas: bloqueos, sesión, ficha, quiz, perfil, juegos y XSS con filas hostiles sembradas; abortan si las bases no son `*_test`; limpian lo que crean. | 36/36 |

### Verificación (cifras reales)
- **PHPUnit:** 118 pruebas, 1.136 aserciones, todas correctas (con el servidor del legado activo).
- **Playwright:** 36 de 36, **dos ejecuciones completas seguidas** (1,8 min cada una) tras la última corrección; antes, 35 de 35 en tres ejecuciones. Sin residuos en las bases de prueba y sin puertos abiertos.
- **Prueba de mutación:** al quitar el bloqueo de opciones de `quiz.js`, la prueba del doble clic falla (esperado 1, recibido 2); restaurado, pasa. La prueba original era débil (podía pulsar una opción incorrecta) y se reescribió con un cuestionario controlado.
- **Revisión del líder:** sin sumideros HTML con datos de usuarios (`.html()` solo con textos fijos o contenido editorial), sin llamadas a los PHP legados en el frontend (salvo chat y corrector, fuera de alcance), `node --check` en todo el JS, y sin cambios nuevos en `chat.js`, `corrector.html`, `chat.php`, `chat copy.php` ni los `conexion*.php` (estos tres conservan los cambios ajenos sin confirmar de siempre).

### Incidencias y decisiones durante la fase
1. **Límite de uso de los agentes:** los lotes C, D y E se cortaron sin guardar ediciones; se retomaron con sus instrucciones originales tras restablecerse el límite.
2. **El Google Chrome del sistema desapareció** del equipo a mitad de la fase (no lo tocó el proyecto) y las pruebas de navegador dejaron de arrancar. Se consultó al responsable; Playwright no maneja el Firefox instalado y **Playwright 1.65 no soporta macOS 13 con ningún navegador**, por lo que se **fijó `@playwright/test` en 1.60.0** y se descargó su Chromium a `~/Library/Caches/ms-playwright` (fuera del repo). *Nota honesta: la descarga ocupó ≈565 MB en caché (incluye el «headless shell»), más de los ≈150 MB estimados.* La configuración ya no depende del Chrome del sistema.
3. **Defecto real del modal de avatares:** Bootstrap 4 ignora `hide()` mientras el modal aún se abre; elegir un avatar enseguida lo dejaba abierto sobre «Guardar». Se añadió `cerrarModalAvatar()` (reintenta al terminar de abrirse). Una prueba intermitente que seguía fallando resultó ser una **carrera de la propia prueba** (Bootstrap mueve el foco al modal al terminar de abrirse; si ocurre entre el `focus()` y el `Enter`, la tecla no llega a la opción): ahora espera el evento `shown.bs.modal`. 6 ejecuciones seguidas sin fallos.
4. **Prueba «sin cookie XSRF»:** una respuesta tardía del historial volvía a emitir la cookie después de borrarla (carrera de la prueba); ahora espera a que termine la red y borra la cookie justo antes de guardar.
5. **Límite de peticiones:** el límite de lectura pasó de 60 a 300 por minuto (`API_POR_MINUTO`) porque un aula comparte IP y cada página sincroniza la sesión al cargar.

### Límites y pendientes
Solo se probó en **Chromium** (no Firefox ni Safari). Los `.htaccess` no se probaron con un Apache real. Sitio en subdirectorio: el cliente lo soporta, el servidor no. Nombre del juego «laberinto» en minúsculas se conserva (renombrarlo partiría los rankings existentes). Fase 7B (Vite, unificar Bootstrap/jQuery/Font Awesome, Mapplic, medios de 11 MB) y el retiro definitivo de los PHP legados quedan pendientes; el despliegue real requiere autorización aparte.

## Fase 7B (parcial, 2026-11-19)
- **Font Awesome:** de ~83 MB a ~3 MB (solo `css/` y `webfonts/`; sin js/svgs/sprites/metadata/less/scss). banderas-color usa la copia de `juegos/librerias/fontawesome`.
- **Mapplic:** `mapplic_vertientes.js` fusionado en `mapplic_generico.js` con la opción `variante: 'vertientes'` (sin id `contenido_texto` ni títulos en mayúsculas). `.load(fn)` → `.on('load', fn)` (compatibilidad jQuery 3).
- **jQuery:** páginas raíz, `miPerfil.html` y `juegos/index.html` pasan de 1.11.3 a 3.5.1 (`bootstrap/jquery.min.js`); se borra `js/jquery.min.js`.
- **Pruebas:** `laravel/tests/Browser/mapas.spec.js` (6 páginas de mapas: sin errores de JS y abre un lugar). Playwright: 42 pruebas. Corregido un error de JS previo en `index.html` (`.mapplic-levels` nulo).
- **Pendiente:** GIF/MP4 (~200 MB; faltan herramientas para redimensionar: `sharp` denegado), Bootstrap 4/5 por grupo, Vite.
- **Medios (7B, cierre):** 16 GIF >1 MB convertidos a WebP animado (640 px; 800 px en laberinto): 169 MB → 47 MB, mismos fotogramas; 4 sin referencia eliminados (`normal`, `saludando`, `nave`, `nave_lenta`); referencias actualizadas. `mapplic/images/loader.gif`, `fantasma.gif` y `mario.gif` se conservan. `ventana_mundo.mp4` recodificado (H.264 CRF 30, audio 64 kbps, faststart): 30,8 MB → 19,0 MB, misma duración y resolución (640x360).
- **Bootstrap:** laberinto usa `juegos/librerias/bootstrap-5.3` (5.3.5) y se borra su copia 5.3.0. banderas-color (4.6.2) y las páginas raíz (4.5.2) se mantienen: son dos generaciones con CSS propio distinto.
- **Pruebas:** `recursos.spec.js` (11 páginas sin 4xx/5xx). Playwright: 53. Defecto previo: laberinto pide `css/perdio_gano.png`, que nunca existió (exento en la prueba).
- **Build ligero:** `laravel/build-sitio.mjs` (`npm run build:sitio`) genera `dist/` minificando JS/CSS con el esbuild de Vite, sin módulos ni cambios en HTML; el router acepta `EXPLORA_RAIZ_SITIO` y las 53 pruebas pasan también contra `dist/`. Migrar a módulos ES/Vite completo queda descartado por riesgo.
- **Fuera de alcance / pendiente:** Firefox/Safari, Apache real.

## Fase 9 — Cimientos Blade: `laravel/public` como único documento raíz (2026-11-19)
- **Movimiento (`git mv`, historial conservado):** las 8 páginas HTML, los 6 JSON de datos y las 25 carpetas del sitio (`bootstrap css driver fontawesome fonts images img js jsons juegos lineaTiempo maps* mapplic miPerfil multimedia sounds sweetalert ViajaPED`) pasan de la raíz a `laravel/public/`, con la misma estructura relativa (las referencias `../` siguen resolviendo) y sin tocar el contenido de ninguna página. El PHP legado (`mapplic/php`, `juegos/php`, incluido `chat.php`) se movió con su carpeta; el chat sigue activo y no se arregla aquí. Los `conexion*.php` se movieron igual que el resto (sin tocar su contenido).
- **Apache:** `laravel/public/.htaccess` fusiona las reglas de seguridad de la raíz (sin listado ni MultiViews, 404 a `.git`/`.env`, deniega `conexion*.php` y `sql|gz|zip|md|sh|lock|log|bak`), añade `DirectoryIndex index.html index.php` y conserva el front controller de Laravel. Se elimina `/.htaccess` (la regla `RewriteRule ^api …` y el `Require all granted` ya no tienen sentido). `DOCUMENTACION_MEJORAMIENTO/.htaccess` y `laravel/.htaccess` se conservan como defensa en profundidad. Hallazgo: con `AllowOverride All` en una carpeta PADRE, Apache leería `laravel/.htaccess` (`Require all denied`) y bloquearía todo el sitio; `DESPLIEGUE_APACHE.md` indica conceder `AllowOverride All` solo sobre `laravel/public`.
- **`laravel/servidor-desarrollo.php`:** docroot por defecto `laravel/public`; sirve `index.html` en `/` y en carpetas (`php -S` preferiría `index.php`), redirige 301 las carpetas sin «/»; se retiró el bloqueo por nombre de carpeta (`laravel`, documentación, `node_modules`), que ya no pueden existir bajo el docroot. Conserva el resto de protecciones.
- **Pruebas/herramientas:** `playwright.config.js` (`EXPLORA_RAIZ_SITIO` por defecto `public`), `ServidorDesarrolloTest` (docroot nuevo; casos: `/` sirve index.html, carpetas, `/.htaccess`, `/.env`, `/index.php` sin fuente, `/laravel/.env`, `/vendor/…`, `/storage/…` → 404), `FichaContractTest` (`public/mapplic/verMas.js`), `tests/Legacy/serve.sh` (copia desde `public/`). Nuevo `laravel/pint.json` que excluye el PHP legado movido bajo `public/` (si no, Pint lo reformateaba).
- **Build ligero retirado:** se eliminan `laravel/build-sitio.mjs`, el script `build:sitio` y `dist/` del `.gitignore` (la minificación pasará a Vite). `.gitignore` ignora `graphify-out/` y `graphify-out-laravel/`.
- **Documentación:** `DESPLIEGUE_APACHE.md`, `PROPUESTA_DESPLIEGUE.md`, `ARQUITECTURA_ACTUAL.md`, `laravel/README.md` y `PLAN_MIGRACION_BLADE.md` (layouts, parciales y rutas web de la Fase 9 se posponen a la Fase 10).
- **Pruebas:** `php artisan test` 130 de 130 (con el oráculo legado en :8081); `npm run test:e2e` 53 de 53; `pint --test` correcto; comprobación manual con `php -S` y con Apache 2.4.68 sin PHP (ver `DESPLIEGUE_APACHE.md`).
