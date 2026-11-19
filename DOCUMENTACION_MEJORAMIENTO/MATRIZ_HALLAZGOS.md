# MATRIZ INTEGRAL DE HALLAZGOS

Fecha: 2026-11-18. Base: lectura estática del código (sin ejecutar la aplicación, sin acceso a la BD). Esfuerzo: S ≤ 0,5 día · M 1–3 días · L > 3 días (estimación orientativa, sin personal ni fechas asignadas).
Tipo: **DC** defecto comprobado (verificado leyendo el código) · **RP** riesgo potencial · **OM** oportunidad de mejora · **PV** pendiente de validación.
Todas las mejoras se resolverán **dentro de la migración incremental a Laravel 10** salvo que se indique lo contrario.

## Resumen

| Prioridad | Cantidad |
|---|---|
| CRÍTICA | 3 |
| ALTA | 12 |
| MEDIA | 25 |
| BAJA | 6 |
| **Total** | **46** |

Por tipo: defectos comprobados (DC) 36 · riesgos potenciales (RP) 2 · oportunidades de mejora (OM) 2 · pendientes de validación (PV) 6 (algunos hallazgos combinan DC/RP con PV y se cuentan en su tipo principal).

## A. Seguridad

| ID | Módulo | Descripción y evidencia | Ubicación | Impacto / Riesgo | Prior. | Tipo | Solución propuesta | Esf. | Dep. | Criterio de aceptación |
|---|---|---|---|---|---|---|---|---|---|---|
| SEC-01 | M02, M03, M04, M06, M13 | **Inyección SQL.** Valores de `$_GET/$_POST` concatenados en SQL sin sanear: `id_usuario` (historial), `nombre_juego/nivel/categoria` (ranking), 9 campos en el INSERT de resultados, `id` (fichas, quiz), `avatar`/`id_usuario` en UPDATE sobre `pedigital.users`. Solo `iniciarSesion.php` usa sentencia preparada. | `juegos/php/consultar_historial.php`, `consultar_ranking.php`, `guardar_resultado.php`; `mapplic/php/consultarInfo.php`, `buscarPreguntas.php`, `cambiarAvatar.php`; `chat.php` (ids internos) | Lectura/modificación/borrado de toda la BD, incluida la plataforma externa `pedigital` | CRÍTICA | DC | Eloquent/Query Builder con parámetros enlazados + Form Requests; mientras tanto, bloquear endpoints en el servidor web | M | Esquema BD | Ningún endpoint concatena entrada; pruebas con payloads `' OR 1=1 --` no alteran resultados |
| SEC-02 | Transversal | **Credenciales de BD en código versionado**, usuario `root` y contraseña trivial, en 5 archivos (3 `conexion*.php`, `chat.php`, `chat copy.php`). Valores no reproducidos. Además hay cambios sin confirmar en 3 de ellos. | `*/php/conexion*.php`, `mapplic/php/chat.php`, `chat copy.php` | Compromiso total de BD si el repo se filtra o el MySQL es alcanzable; queda en el historial de Git | CRÍTICA | DC | `.env` fuera del repo, usuario de BD con privilegios mínimos, **rotar credenciales** (requiere autorización) y purgar del historial | S | Autorización para rotar | Sin secretos en Git; usuarios BD distintos y mínimos por conexión |
| SEC-03 | M05, M06, M13 | **Sin autenticación ni autorización en servidor (IDOR).** Cualquiera puede consultar el historial de cualquier `id_usuario`, cambiar el avatar de cualquier usuario o guardar resultados a nombre de otro. La "sesión" es `localStorage` editable (`id_usuario`). | `consultar_historial.php`, `cambiarAvatar.php`, `guardar_resultado.php`, `miPerfil/script.js`, `iniciarSesion.js` | Suplantación y acceso a datos de menores | CRÍTICA | DC | Sesión/token de servidor (Laravel Sanctum o sesión), `auth` middleware y Policies; el `id` se toma del usuario autenticado | M | SEC-04, auth `pedigital` | Peticiones sin sesión devuelven 401; un usuario no accede a datos de otro (prueba automatizada) |
| SEC-04 | M05 | **Login expone el hash.** `iniciarSesion.php` devuelve la fila completa de `users` (incluye `pasword_usuario`). Mensajes distintos para usuario inexistente/clave errónea (enumeración) y sin límite de intentos. | `mapplic/php/iniciarSesion.php` | Filtración de hashes; fuerza bruta | ALTA | DC | Devolver solo campos necesarios; mensaje genérico; throttling (`RateLimiter`) | S | — | Respuesta sin hash; 6.º intento fallido en 1 min bloqueado |
| SEC-05 | M13, M02 | **XSS almacenado.** El ranking inserta con `innerHTML` valores de BD (`avatar`, `grado`, nombre) que provienen de un POST sin autenticación ni validación. Fichas/quiz usan `.html()`/plantillas con contenido de BD y `onclick` inline. | `juegos/js/terminar_juego.js:258`, `mapplic/verMas.js`, `mapplic/js/quiz.js:98` | Ejecución de script en el navegador de otros estudiantes | ALTA | DC (ranking) / RP (fichas) | Validar en servidor, escapar (`textContent`/Blade `{{ }}`), CSP | M | SEC-01 | Nombre `<img onerror=…>` se muestra como texto |
| SEC-06 | M13, M03 | **Resultados y respuestas controlados por el cliente.** El servidor acepta `puntaje`, `tiempo` y `nivel` tal cual (ranking falsificable); el quiz envía la respuesta correcta (`correcta`) al navegador. | `guardar_resultado.php`, `buscarPreguntas.php`, `quiz.js` | Ranking sin integridad; trampas | ALTA | DC | Validar rangos/tipos en servidor; verificar respuestas en servidor para el quiz | M | SEC-03 | Puntaje fuera de rango rechazado (422); el JSON de preguntas no incluye la correcta |
| SEC-07 | Transversal | **Errores internos expuestos:** `die("Error de conexión: …")`, `$conexion->error` devueltos al cliente. Sin configuración explícita de errores. | `conexion*.php`, `guardar_resultado.php`, `cambiarAvatar.php`, `chat.php` | Fuga de información técnica | MEDIA | DC | Manejo de excepciones de Laravel, `APP_DEBUG=false`, logs | S | — | Ningún mensaje de BD llega al cliente |
| SEC-08 | M04, M24 | **IA sin control:** `chat.php` sin límite de uso (cada consulta puede ocupar un proceso hasta 120 s) ni validación de la categoría/departamento devueltos por el modelo; `corrector.html` llama a Ollama desde el navegador con el texto interpolado en el prompt (inyección de prompt). | `chat.php`, `corrector.html` | Denegación de servicio; resultados manipulables | MEDIA | RP | Rate limiting, lista blanca de salida, cola/timeout corto, servicio servidor para el corrector | M | — | Consultas limitadas por IP/usuario; salida fuera de lista rechazada |
| SEC-09 | Transversal | **Dependencias obsoletas:** jQuery 1.11.3 (y 1.7.1 referenciado por CDN en al menos un HTML), Bootstrap 4.5.2. Sin integridad (SRI) en CDN. | `js/jquery.min.js`, HTML con CDN | Vulnerabilidades conocidas del frontend | MEDIA | RP / PV | Gestionar con npm/Vite y versiones actuales; confirmar con auditoría de dependencias | M | — | Sin dependencias con CVE conocidos |
| SEC-10 | Transversal | Sin CSRF, CORS, cabeceras de seguridad ni HTTPS verificables (no hay configuración de servidor en el repo). | — | Riesgo al introducir sesiones | MEDIA | PV | Middleware de Laravel (CSRF, headers), HTTPS en despliegue | S | Entorno | Cabeceras presentes en revisión |

## B. Funcional

| ID | Módulo | Descripción y evidencia | Ubicación | Impacto / Riesgo | Prior. | Tipo | Solución | Esf. | Dep. | Criterio |
|---|---|---|---|---|---|---|---|---|---|---|
| FUN-01 | M13 | `guardar_resultado.php` guarda la fecha como texto `d-m-Y H:i:s`; el historial se ordena en PHP con `strtotime`, sin paginación. (Confirmado en el esquema: `fecha` es `text`.) | `guardar_resultado.php`, `consultar_historial.php` | Orden incorrecto/lento al crecer | MEDIA | DC | Columna `datetime`/timestamps y orden en SQL con paginación | S | Esquema BD | Historial ordenado en BD y paginado |
| FUN-02 | M03 | `buscarPreguntas.php` no responde nada si el departamento no tiene registros (el cliente recibe vacío, no JSON). `consultarInfo.php` sí devuelve error. | `buscarPreguntas.php` | Quiz sin mensaje; error JS | MEDIA | DC | Respuesta JSON consistente con códigos HTTP | S | — | Departamento sin datos → JSON de error |
| FUN-03 | M04 | `chat.js` concatena el mensaje sin codificar en la URL (`?cadena='+mensaje`): `&`, `#`, `%` rompen la consulta. | `mapplic/js/chat.js:124` | Consultas truncadas | MEDIA | DC | `encodeURIComponent`/POST JSON | S | — | Texto con `&` llega íntegro |
| FUN-04 | M04 | `chat.php` no valida que `categoria`/`departamento` devueltos por el modelo estén en las listas permitidas; `$mapa[$categoria]` queda indefinido si no coincide. | `chat.php` (`$mapa[$categoria]`) | Error/aviso PHP, respuesta vacía | MEDIA | DC | Validar contra la lista blanca y responder con mensaje controlado | S | — | Categoría desconocida → mensaje de ayuda |
| FUN-05 | M24 | `corrector.html` y `chat.php` dependen de Ollama en `localhost`; desde el navegador solo funciona en el equipo que lo ejecuta. | `corrector.html:200` | Inoperante para usuarios remotos | ALTA | DC | Mover a endpoint servidor (Laravel) con cola | M | Decisión de IA | Funciona desde otro equipo |
| FUN-06 | M03 | Texto "de 10" fijo en el quiz independientemente de la cantidad real de preguntas. | `quiz.js:92,173` | Información incorrecta | BAJA | DC | Usar el conteo real | S | — | Contador refleja las preguntas cargadas |
| FUN-07 | M19–M21 | **Corregido tras el barrido:** Arrastra y Completa son juegos completos que guardan resultados; solo faltan enlaces en el menú (hay enlaces `#` sin destino). La carpeta `mapas` solo tiene imágenes (PV). `chat copy.php` es copia. | `juegos/juegos/*` | Menú incompleto; decisión sobre `mapas` | MEDIA | PV | Confirmar con el responsable antes de migrar/archivar | S | Responsable | Decisión documentada por módulo |
| FUN-08 | Todos | Flujos de extremo a extremo (login, quiz, juegos, ranking) **no verificados**: no hay BD ni entorno de ejecución. | — | Riesgo de regresión no medible | ALTA | PV | Montar BD aislada con el dump y ejecutar pruebas | M | Dump BD | Línea base de pruebas funcionales |

## C. Técnica y datos

| ID | Módulo | Descripción y evidencia | Ubicación | Impacto | Prior. | Tipo | Solución | Esf. | Dep. | Criterio |
|---|---|---|---|---|---|---|---|---|---|---|
| TEC-01 | M02 | Cada ficha ejecuta ≈19 `SELECT *` secuenciales (relación indirecta: tablas hijas enlazan a `departamento_descripcion.id`, no a `list_departamentos.id`). | `consultarInfo.php` | Latencia y modelo confuso | MEDIA | DC | Modelos con relaciones y `with()`; unificar claves | M | Esquema BD | ≤ 3 consultas por ficha |
| TEC-02 | Transversal | Duplicación: 3 variantes de Mapplic (~1.300 líneas c/u; difieren en ≈250 líneas entre sí, el resto es común), `chat copy.php`, `conexion` ×3, Font Awesome ×3, Bootstrap ×4. | ver inventario | Mantenimiento costoso | MEDIA | DC | Parametrizar Mapplic; un solo paquete por librería vía npm | M | — | Una única copia por librería |
| TEC-03 | Transversal | Sin capas: HTML dentro de `chat.php`, SQL en scripts, sin Content-Type JSON en la mayoría de endpoints, `SELECT *`. | `mapplic/php/*`, `juegos/php/*` | Baja mantenibilidad | MEDIA | DC | Controladores + Resources + Form Requests | M | — | Respuestas JSON uniformes |
| TEC-04 | Datos | Charset `utf8` (3 bytes) en vez de `utf8mb4`; sin transacciones; nombres con errores (`opciones_verfal`, `pasword_usuario`). Esquema real obtenido (ver ESQUEMA_BASE_DATOS.md): ver DAT-02 a DAT-05. | `conexion*.php` | Pérdida de caracteres, deuda de datos | MEDIA | DC / PV | Migraciones con `utf8mb4`, mapeo de nombres con alias | M | Esquema BD | Esquema revisado e indexado |
| TEC-05 | Datos | Sin migraciones, seeders ni respaldo documentado; el dump (~360 MB) fuera del repo. | — | No reproducible | ALTA | DC | Migraciones + seeders; procedimiento de respaldo | M | Dump BD | Entorno recreable con `migrate --seed` |

## D. Rendimiento, UX y calidad

| ID | Módulo | Descripción y evidencia | Ubicación | Impacto | Prior. | Tipo | Solución | Esf. | Criterio |
|---|---|---|---|---|---|---|---|---|---|
| REN-01 | M13 | GIFs de 11 MB y 3,6 MB (victoria/derrota), `pensando.gif`, `ventana_mundo.mp4`, `fondo.mp3`. | `images/`, `img/`, `multimedia/` | Carga lenta (sin medición real) | MEDIA | OM | Convertir a WebP/MP4, carga diferida; medir antes y después | S | Peso reducido medido |
| REN-02 | M10, M01 | `parques_naturales.json` 6,2 MB y `jsons/` 35 MB servidos sin compresión verificada. | raíz, `jsons/` | Carga inicial pesada | MEDIA | OM / PV | Gzip/Brotli, simplificar geometrías, API paginada | M | Tiempo de carga medido |
| UX-01 | Varios | `<label onclick>` sin foco ni teclado en el quiz; `index.html` sin atributo `lang`; 1 de 3 imágenes de `index.html` sin `alt`. | `quiz.js:98`, `index.html` | Accesibilidad | MEDIA | DC | Botones reales, `lang="es"`, alt | S | Navegación por teclado completa |
| UX-02 | Todos | Adaptación móvil, contraste, consistencia visual entre módulos: **sin revisar** (requiere pruebas visuales). | — | — | MEDIA | PV | Revisión visual en 3 tamaños | M | Informe UX validado |
| CAL-01 | Todos | Sin pruebas automatizadas, linters, CI ni logging. | — | Regresiones al migrar | ALTA | DC | PHPUnit/Pest + Pint + Larastan en el proyecto Laravel; CI | M | Suite verde en CI |
| CAL-02 | Todos | Sin README ni documentación técnica (cubierto parcialmente por esta carpeta). | — | Dependencia de conocimiento tácito | BAJA | DC | README + guía de instalación | S | Instalación reproducible por un tercero |
| CAL-03 | Repo | `.gitattributes` sin reglas de binarios; carpetas `node_modules` ignoradas pero sin lockfile raíz. | repo | Orden del repo | BAJA | DC | Limpiar tras confirmación | S | Estructura revisada |

## E. Datos (hallazgos nuevos tras recibir los dumps)

| ID | Módulo | Descripción y evidencia | Ubicación | Impacto | Prior. | Tipo | Solución | Esf. | Criterio |
|---|---|---|---|---|---|---|---|---|---|
| DAT-01 | M05 | `pedigital.users` tiene `estado_usuario`, pero `iniciarSesion.php` no lo consulta: un usuario inactivo podría iniciar sesión (los valores que significan "inactivo" no se leyeron). | `iniciarSesion.php`; `pedigital.users` | Acceso de cuentas deshabilitadas | ALTA | DC (semántica PV) | Validar estado en el login; confirmar valores con el responsable | S | Usuario inactivo recibe rechazo |
| DAT-02 | M01 | Las 13 tablas `regiones_*` tienen FK **autorreferenciada** (p. ej. `reg_clima` → `regiones_clima.id`) en vez de `regiones.id`; el módulo de regiones no aparece en el código revisado. | `explora.regiones_*` | Integridad referencial inválida; módulo posiblemente huérfano | MEDIA | DC / PV | Corregir FK en migración no destructiva tras confirmar uso | M | FK apuntan a `regiones.id` |
| DAT-03 | M13 | `resultado_juegos`: todas las columnas textuales y `fecha` son `text`; sin FK ni índices; el ranking filtra por 3 columnas `text`. | `explora.resultado_juegos` | Consultas lentas al crecer; orden por fecha frágil | MEDIA | DC | `varchar`/enum, `datetime`, índice compuesto (juego, nivel, categoría, puntaje) | S | Ranking usa índice; fechas ordenables |
| DAT-04 | M02, M03 | Sin FK: `departamento_descripcion.departamento`→`list_departamentos`, `preguntas.departamento`, `opciones_verfal.pregunta`. | `explora` | Huérfanos posibles | MEDIA | DC | Añadir FK tras verificar datos | M | Sin registros huérfanos |
| DAT-05 | Datos | Inconsistencias: `update_at` vs `updated_at` (5 de las 6 tablas `*_list` usan `update_at`; `departamento_etnografia_list` usa `updated_at`), collations/charsets mezclados (utf8mb3 en `users`, utf8mb4 resto), `departamento_etnografia.contenido` y `regiones_etnografia.etnografia` como `longblob` para texto, índices duplicados con el mismo nombre. | `explora` | Errores de ORM y de codificación | BAJA | DC | Normalizar en migraciones | M | Esquema uniforme |
| DAT-06 | M05 | `explora.users` duplica la estructura de `pedigital.users` (sin `grado`/`avatar`) y el código no la usa. | `explora.users` | Confusión sobre la fuente de usuarios | MEDIA | PV | Confirmar propósito; no migrar si es residual | S | Decisión documentada |

## F. Barrido complementario (2026-11-18, previo a la Fase 0)

Revisión adicional de secretos, URLs externas, archivos sueltos, referencias rotas, los 8 juegos, Mapplic, línea de tiempo y ViajaPED (a nivel de puntos de entrada de datos y sumideros; no se ejecutó nada).

| ID | Módulo | Descripción y evidencia | Ubicación | Prior. | Tipo | Solución | Criterio |
|---|---|---|---|---|---|---|---|
| SEC-11 | M04 | El chat arma HTML concatenando el texto escrito por el usuario (`'lista de '+mensaje`) y nombres devueltos por BD, y lo inserta con jQuery `.append` (auto-XSS y XSS si la BD contiene HTML malicioso). | `mapplic/js/chat.js` | MEDIA | DC | Insertar con `textContent`/plantillas escapadas | Entrada con `<script>` se muestra como texto |
| FUN-09 | M13–M21 | **Regla de negocio: modo invitado.** Sin sesión, los juegos usan `id_usuario = 0` y permiten escribir nombre, grado y avatar libres, y guardar el resultado. Es parte del comportamiento actual y debe conservarse, pero hoy no tiene validación ni límites. | `juegos/js/terminar_juego.js` | MEDIA | PV (confirmar si es intencional) | Mantener invitados con validación, límites de longitud y rate limiting | Invitado puede jugar; datos validados |
| FUN-10 | M12 | `general.js` redirige a `volcanes.html`, que no existe. | `mapplic/js/general.js` | BAJA | DC | Quitar o crear la página | Sin enlaces rotos |
| CAL-04 | Repo | `ViajaPED/package.json` declara React 19 y Babel sin uso en `script.js`; `.DS_Store` versionado; `jsons/unir.html` (herramienta de desarrollo con jQuery por CDN) en carpeta pública; sopa de letras usa XHR síncrono. | `ViajaPED/`, `juegos/librerias/bootstrap-5.3/css/.DS_Store`, `jsons/unir.html`, `juegos/juegos/sopa_letras/index.html` | BAJA | DC | Retirar tras confirmación; XHR asíncrono | Repo sin residuos |

### Correcciones a hallazgos previos
- **FUN-07:** Arrastra (M19) y Completa (M20) **son juegos completos** que cargan `terminar_juego.js` y guardan resultados con el mismo mecanismo; el menú de juegos tiene enlaces `#` sin destino, probablemente pendientes. `mapas` solo contiene imágenes (PV).
- **SEC-06 (ampliado):** toda la lógica de los 8 juegos corre en el navegador: preguntas y respuestas correctas están en JSON públicos (`preguntas.json`, `completa.json`, `pistas.json`, `datos.json`, `categorias.json`) y el puntaje lo calcula el cliente. Validar en servidor solo puede limitar rangos, no garantizar integridad; **requiere aceptar ese alcance**.
- **SEC-09:** el único `jquery 3.6.0` por CDN está en `jsons/unir.html`; jQuery 1.7.1 aparece en el ejemplo de terceros `wordfind-master/example`. Las páginas de la aplicación cargan jQuery 1.11.3 local.
- **SEC-02:** el barrido confirmó 5 instanciaciones `new mysqli` con credenciales (`conexion.php` ×2, `conexion_pedigital.php`, `chat.php`, `chat copy.php`); no hay otras claves ni tokens en el repositorio.

### Resultado del barrido (sin hallazgos adicionales)
Sin referencias rotas desde las 7 páginas raíz hacia sus CSS/JS; sin `.env`, `.sql` ni copias de seguridad versionadas; sin llaves de API; las tres variantes de Mapplic cargan datos solo desde JSON locales (`departamentos.json`, `jsons/*.json`, `climatico.json`, etc.), no desde PHP.

## G. Hallazgos medidos en la Fase 1 (pruebas de caracterización, 2026-11-18)

| ID | Módulo | Descripción y evidencia | Ubicación | Prior. | Tipo | Solución | Criterio |
|---|---|---|---|---|---|---|---|
| REN-03 | M02 | **Medido:** `consultarInfo.php?id=2` responde **9.777.872 bytes (≈9,8 MB)** en la copia de pruebas; con la inyección `2 OR 1=1` responde 11,8 MB. Cada apertura de ficha transfiere ese volumen (contenido embebido en `longtext`/`longblob`; la tabla que más pesa en la BD es `departamento_sitios_list`, 61,5 MB). | `consultarInfo.php`, BD | ALTA | DC (medido) | Cargar secciones bajo demanda, separar medios embebidos, compresión | Ficha inicial < tamaño acordado, medido antes/después |

### Evidencias que confirman hallazgos previos (con prueba automatizada)
SEC-01 (inyección en `consultarInfo`, `consultar_historial`, `consultar_ranking`) · SEC-03 (historial y avatar sin sesión, suplantación de `id_usuario`) · SEC-04 (respuesta de login con hash, email y token; mensajes distintos) · SEC-05 (HTML almacenado y devuelto sin escapar) · SEC-06 (`puntaje=999999` aceptado; `correcta` visible en el quiz) · SEC-07 (error fatal con ruta del servidor en 3 endpoints —`consultarInfo`, `buscarPreguntas`, `cambiarAvatar`— y advertencias con la ruta en 3 más —`iniciarSesion`, `consultar_ranking`, `guardar_resultado`— cuando faltan parámetros) · FUN-01 (`fecha` guardada como `d-m-Y H:i:s`) · FUN-02 (cuerpo vacío) · TEC-03 (todas las respuestas `text/html` salvo `chat.php`).
**Nota:** cada departamento tiene 30 preguntas (no 10); el contador "de 10" de FUN-06 sugiere que el cliente muestra una muestra de 10, por confirmar en `quiz.js`.

## H. Hallazgos de la Fase 3 (contrato de la ficha y el quiz, 2026-11-18)

| ID | Módulo | Descripción y evidencia | Ubicación | Prior. | Tipo | Solución | Criterio |
|---|---|---|---|---|---|---|---|
| FUN-11 | M02 | **`consultarInfo.php?id=10` falla:** con el límite de memoria por defecto de PHP (128 MB) responde HTTP 200 con un *Fatal error* («Allowed memory size … exhausted», 247 bytes) en lugar de JSON, por lo que `verMas.js` (`JSON.parse`) no muestra la ficha de ese departamento. Medido contra la copia de pruebas. En producción depende de su `memory_limit` (PV). | `mapplic/php/consultarInfo.php:151` | ALTA | DC (límite por defecto) / PV (producción) | Reemplazo Laravel ya sirve ese departamento con 128 MB (ver REGISTRO_CAMBIOS); solución de fondo: DAT-07 | Ficha del departamento 10 carga con el límite por defecto |
| DAT-07 | M02 | **Imágenes embebidas en base64 dentro del contenido HTML:** 1.071 elementos de las listas (sitios, personajes, festividades, fauna, flora) contienen `data:image`. En el departamento 10 los sitios suman 19 MB y la ficha completa 46 MB (departamento 2: 9,3 MB). Es la causa raíz de REN-03 y FUN-11. | tablas `departamento_*_list`, `departamento_cultura`, … | ALTA | DC (medido en la copia) | Extraer las imágenes a archivos y sustituirlas por URLs (migración de **contenido**, requiere autorización y respaldo); mientras tanto, carga por secciones | Ficha < tamaño acordado; sin `data:image` en BD |

### Estado de los hallazgos tratados por los endpoints nuevos (`/api/departamentos/{id}/ficha` y `/preguntas`)
SEC-01 (ficha y quiz: sin SQL concatenado, solo ids numéricos) · SEC-07 (sin errores fatales ni rutas; 404 JSON) · FUN-02 (id inexistente → 404 con JSON) · TEC-03 (Content-Type JSON, Resources de lista blanca, sin columnas internas) · SEC-04 N/A. **Parcial:** TEC-01 (sin consultas en bucle, pero siguen siendo ≈19 consultas porque cada sección es una tabla) · SEC-06 (`correcta` se mantiene por compatibilidad con `quiz.js`; configurable con `QUIZ_INCLUIR_RESPUESTAS`). **No resuelto:** REN-03 (el peso es de los datos, DAT-07).

## I. Decisiones de alcance (2026-11-18)

**Fase 6 (IA) aplazada** por decisión del responsable: el chat con IA y el corrector de texto se tratarán en un trabajo posterior enfocado solo en IA (ver [PENDIENTE_TRABAJO_IA.md](PENDIENTE_TRABAJO_IA.md)). Quedan **abiertos** SEC-08 (IA sin límite de uso), FUN-03 (URL del chat sin codificar), FUN-04 (salida de la IA sin validar), FUN-05 (Ollama en `localhost` desde el navegador) y SEC-11 (HTML del chat). **Riesgo residual que se acepta explícitamente:** `mapplic/php/chat.php` y `mapplic/php/chat copy.php` siguen publicados con credenciales de MySQL escritas en el código (SEC-02, parte del chat) y con SQL construido a partir de la respuesta de la IA; no se modifican. La rotación de credenciales y el retiro de `chat copy.php` se harán en el trabajo de IA.

## J. Fase 7 — conmutación del frontend (2026-11-19)

Hallazgos **detectados al explorar el frontend y corregidos en esta fase** (verificados con pruebas en navegador):

| ID | Módulo | Descripción y evidencia | Prior. | Tipo | Estado |
|---|---|---|---|---|---|
| FUN-12 | M13–M21 | El modal de guardar resultado se eliminaba en plena transición de Bootstrap: quedaban `.modal-backdrop` y `modal-open` (pantalla bloqueada). En `banderas-color` (Bootstrap 4.6) se ignoraban además `data-bs-backdrop`/`data-bs-keyboard`. | MEDIA | DC | **Corregido** (`terminar_juego.js`: instancia `bootstrap.Modal`, eliminación en `hidden.bs.modal` con respaldo; probado en Bootstrap 4 y 5) |
| FUN-13 | M03 | **El quiz solo funcionaba en `index.html`.** En las otras 5 páginas de mapas el id del departamento salía vacío (`#departamento_seleccionado` solo existe en `index.html`) y además se llamaba dos veces a `buscarPreguntas`. | ALTA | DC (por lectura estática; el nuevo comportamiento sí se probó) | **Corregido**: aviso claro con enlace al mapa de departamentos en esas 5 páginas y una sola llamada. *No se inventó funcionalidad: hacer el test por departamento en los otros mapas queda para la Fase 7B.* |
| FUN-14 | M05 | `tipo_modal` en `localStorage` podía quedar desfasado entre páginas; `cerrarSesion` fallaba al refrescar la interfaz (error al cerrar sesión con `tipo_modal == 1`) y no refrescaba los botones en `juegos/index.html`. | MEDIA | DC | **Corregido** (se eliminó `tipo_modal`; el modo se decide por el DOM) |

**Estado de hallazgos anteriores tras la fase:** SEC-05 (parte de cliente) **cerrado**: ranking e historial se construyen con `textContent`, el avatar se valida (1–35) y las filas con HTML ya guardadas en la base se muestran como texto (prueba E2E con filas hostiles sembradas). UX-01 **cerrado para el quiz** (botones reales, foco, `aria-live` con «Correcto/Incorrecto», `lang="es"` en las 6 páginas, nombres accesibles) y el modal de login. SEC-01, SEC-03, SEC-04, SEC-06 (rutas legadas) **cerrados por bloqueo**: los 7 PHP sustituidos responden 410 (Apache `.htaccess` y router de desarrollo; verificado con el router, **no** con un Apache real). **Siguen abiertos:** SEC-02 y SEC-08 del chat legado (decisión del responsable), SEC-06 (honestidad del puntaje: límite aceptado), DAT-07/REN-03 (peso de las fichas) y el resto de la Fase 7B. **Riesgo nuevo aceptado:** el contenido editorial de las fichas y del quiz se inserta como HTML por diseño (lo administra personal autorizado); si esa fuente se comprometiera habría XSS. Mitigación futura: sanitizar al guardar y/o una política CSP.

## Trazabilidad módulo → estado de auditoría

| Módulo | Cobertura de esta auditoría | Estado |
|---|---|---|
| M02, M03, M04, M05, M06, M13 | Código PHP y JS asociados leídos completos | Revisado (estático) |
| M24 | Llamada a Ollama revisada | Revisado (parcial) |
| M01, M07–M11, M12 | Solo cabeceras y dependencias; Mapplic no analizado en profundidad | Revisado (parcial) |
| M14–M21, M22, M23 | Barrido de puntos de entrada, sumideros y carga de datos (sin análisis de la lógica de juego ni pruebas de ejecución) | Revisado (parcial) |
| M21 (`mapas`) | Solo imágenes | Pendiente de información |

> La auditoría **no puede declararse completa**: faltan módulos por revisar y no hubo ejecución real ni acceso a la BD.
