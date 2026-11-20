# Explora — migración a Laravel 10

Nueva base de código de **Explora Colombia y aprende** (sitio educativo sobre geografía, cultura e historia de
Colombia). Reemplaza **de forma incremental** los 12 scripts PHP planos del sitio original (`public/mapplic/php`,
`public/juegos/php`), que siguen funcionando mientras se migra cada módulo.

> Contexto, auditorías, decisiones y registro de cambios: [`../DOCUMENTACION_MEJORAMIENTO/`](../DOCUMENTACION_MEJORAMIENTO/)
> (empezar por `REGISTRO_CAMBIOS.md` e `IMPLEMENTACION_MEJORAS.md`).

## Estado

| Fase | Contenido | Estado |
|---|---|---|
| 0 | Entorno, bases de prueba, conexión `pedigital` de solo lectura | ✔ |
| 1 | Pruebas de caracterización del PHP legado | ✔ |
| 2 | Migración base, modelos Eloquent, `perfiles_explora` | ✔ |
| 3 | API de lectura: ficha de departamento y quiz | ✔ (conectada al frontend en la Fase 7) |
| 4 | Autenticación por sesión y avatar | ✔ (conectada al frontend en la Fase 7) |
| 5 | Juegos: resultados, ranking, historial | ✔ (conectada al frontend en la Fase 7) |
| 6 | Chat con IA y corrector | aplazada (trabajo aparte enfocado en IA) |
| 7 | Conmutación segura del frontend (sesión, fichas, quiz, perfil, juegos), bloqueo de los PHP sustituidos y pruebas en navegador | ✔ |
| 7B | Modernizar el frontend (Vite, unificar librerías, Mapplic, optimizar medios) | parcial |
| 9 | Cimientos Blade: `laravel/public/` es el único documento raíz y contiene todo el sitio (HTML intacto, solo movido) | ✔ |
| 10 | Mapas temáticos (`/climatico`, `/coordilleras`, `/etnias`, `/parques_naturales`, `/vertientes`): una vista Blade parametrizada + layout y parciales reutilizables | ✔ |

## Requisitos

PHP ≥ 8.1 (probado con 8.2.31), Composer 2, MySQL 8 (en este equipo: `/usr/local/mysql`). Laravel 10.50.4.
**Aviso:** Laravel 10 ya no recibe parches de seguridad; `composer.json` ignora de forma explícita 5 avisos
(`config.policy.advisories.ignore-id`). Es una decisión del responsable (ver `REGISTRO_CAMBIOS.md`).

## Puesta en marcha

```bash
composer install
cp .env.example .env        # y completar las credenciales (el .env NO se versiona)
php artisan key:generate
```

Bases de datos (en `.env`):

| Conexión | Variables | Uso |
|---|---|---|
| `mysql` (por defecto) | `DB_*` | Base `explora` (contenido, quiz, resultados, `perfiles_explora`) |
| `pedigital` | `PEDIGITAL_DB_*` | Usuarios de la plataforma pednia/ambientes. **SOLO LECTURA**: el usuario MySQL debe tener únicamente `SELECT` |

⚠️ **No ejecutar `php artisan migrate` sin `--path`** sobre una base real: las migraciones son aditivas e idempotentes,
pero conviene aplicarlas de una en una y solo en bases de prueba hasta que se autorice el despliegue.

## Estructura del código

```
public/                  ÚNICO documento raíz (Fase 9): index.php (front controller), .htaccess y todo el sitio estático
                         (index.html, *.html, *.json, css/, js/, mapplic/, juegos/, images/, maps*/…; el PHP legado en
                         mapplic/php y juegos/php). Las rutas públicas (/index.html, /juegos/…) no cambiaron.
app/
  Http/
    Controllers/MapaTematicoController.php  Un único método (`__invoke`) para los 5 mapas temáticos (Fase 10)
    Controllers/Api/     Una clase por endpoint: ficha, preguntas, sesión, avatar, resultado, ranking, historial
    Requests/            Validación de entrada (Login, CambiarAvatar, GuardarResultado, Ranking; reglas compartidas en Concerns/)
    Resources/           Forma de las respuestas JSON (LISTA BLANCA de campos)
    Middleware/          MemoriaParaFichas, CuentaActiva, Authenticate (sin redirecciones)
  Models/
    Concerns/SoloLectura Trait que impide escribir en un modelo
    Explora/             Modelos de la base `explora`: 25 concretos (12 secciones 1:1, 6 listas, Departamento,
                         DepartamentoDescripcion, Pregunta, 2 de opciones, ResultadoJuego, PerfilExplora) + 2 bases abstractas
    Pedigital/Usuario    Usuario de pednia (autenticable, solo lectura)
  Support/NormalizaLogin Normaliza el usuario (minúsculas, sin acentos) para las claves de los limitadores
  Exceptions/Handler     Toda la ruta api/* responde JSON
  Providers/RouteServiceProvider   Limitadores de peticiones (`login`, `api`)
config/explora.php       Opciones propias (respuestas del quiz, cantidad de avatares)
config/mapas.php         Un arreglo por mapa temático (clave = slug de la URL): título, JSON, carpeta de imágenes, tutorial, variante y CSS
resources/views/
  layouts/sitio.blade.php   Doctype, <head> y scripts comunes (todo con asset()); secciones `titulo`, `contenido` y pilas `estilos`/`scripts`
  partials/                 Bloques reutilizables (la portada de la Fase 11 usará los mismos): mapa-contenedor, panel-contenido, chat,
                            modal-pantalla-completa, modal-tipo-mapa, multimedia (video/imágenes/test), menu-circular, menu-accesibilidad, final
  mapas/                    tematico.blade.php (la página), estilos.blade.php (CSS por mapa) y dato-interes.blade.php (solo climatico)
routes/web.php           `GET /{slug}` (lista cerrada desde config/mapas.php) y `GET /{slug}.html` -> 301 a `/{slug}`
public/js/mapa-tematico.js  JS compartido de los mapas (acordeón, modales, visores, accesibilidad, opciones de Mapplic); lee `window.EXPLORA_MAPA`
database/migrations/     Migración base (esquema real, idempotente) y perfiles_explora
routes/api.php           Rutas de la API
tests/
  Characterization/      Fijan el comportamiento ACTUAL del PHP legado (incluidos sus defectos)
  Contract/              La API nueva entrega al frontend lo mismo que el legado (33 departamentos)
  Feature/               Modelos, perfil, autenticación, juegos, servidor de desarrollo y mapas temáticos
  Equivalencia/          comparar-paginas.php: compara el HTML original de cada mapa con el renderizado (compuerta de la Fase 10)
  Legacy/                Arnés para servir una copia del legado contra bases *_test
```

## API

Todas las rutas responden **JSON** (incluidos 404 y errores). Prefijo `/api`.

### Lectura pública (límite configurable, 300 peticiones/min por IP o usuario)

| Método y ruta | Reemplaza a | Respuesta |
|---|---|---|
| `GET /api/departamentos/{id}/ficha` | `consultarInfo.php` | 16 secciones con las claves que lee `mapplic/verMas.js`; 404 `{error}` si no existe |
| `GET /api/departamentos/{id}/preguntas` | `buscarPreguntas.php` | `{preguntas:[{id,tipo_pregunta,pregunta,origen_pregunta,opciones}], departamento:{id,nombre,capital}}` |

`{id}` es `list_departamentos.id` (1–33); cualquier valor no numérico devuelve 404. La ficha puede superar 40 MB
(imágenes en base64 dentro del contenido, hallazgo DAT-07); por eso su ruta eleva `memory_limit` a 512 MB.
Mientras el frontend corrija el quiz en el navegador, las opciones incluyen `correcta`/`respuesta`. **No poner
`QUIZ_INCLUIR_RESPUESTAS=false` con el cliente actual**: sin esos campos todas las respuestas de opción múltiple contarían
como buenas y todas las de verdadero/falso como malas (SEC-06 se cierra migrando antes la corrección al servidor).

### Juegos (Fase 5)

| Método y ruta | Reemplaza a | Respuesta |
|---|---|---|
| `POST /api/resultados` (CSRF; sesión **opcional**) | `guardar_resultado.php` | 201 `{status:'success', mensaje}`; 422 `{status:'error', mensaje, campos}`; 401 si la cuenta de la sesión se desactivó; 429 (10 por minuto por usuario o por IP) |
| `GET /api/ranking?juego=&nivel=&categoria=` (público) | `consultar_ranking.php` | Hasta 5 filas `{jugador, avatar, grado, puntaje, tiempo}` por puntaje (desc) y tiempo (asc); 422 si algún parámetro no es válido |
| `GET /api/historial` (sesión) | `consultar_historial.php` | Partidas del usuario de la sesión, la más reciente primero: `{id, nombre_juego, categoria, nivel, puntaje, tiempo, fecha}` (máx. 500). **Ignora cualquier `id_usuario`** |

Cuerpo de `POST /api/resultados`: `juego`, `nivel`, `categoria`, `puntaje` (0–1000), `tiempo` (segundos, 0–14400) y, **solo
para invitados**, `nombre`, `grado` (1–11) y `avatar` (`N.png`, N de 1 a 35). Con sesión, la identidad (id, nombre, grado y
avatar) sale de la sesión y lo que envíe el cuerpo se ignora. `juego` y `nivel` son listas cerradas (`config/explora.php`:
los 8 juegos y 4 niveles reales); `categoria` y `nombre` no admiten HTML ni comillas. **Modo invitado (FUN-09):** se puede
jugar y guardar sin sesión (`id_usuario = 0`), con datos validados y el límite de uso. El puntaje y el tiempo los calcula el
navegador: el servidor solo descarta valores imposibles y **no puede garantizar que sean honestos** (SEC-06, límite
aceptado). `fecha` se guarda como texto `d-m-Y H:i:s` en UTC, como el legado (DAT-03).

### Sesión y perfil (cookie de sesión HttpOnly + CSRF)

| Método y ruta | Cuerpo | Respuesta |
|---|---|---|
| `GET /api/sesion` | — | 200 `{success, usuario_objeto}` o 401. **También entrega la cookie `XSRF-TOKEN`** |
| `POST /api/sesion` | `usuario`, `contrasena` | 200 `{success:true, mensaje, usuario_objeto}`; 401 (mismo cuerpo si el usuario no existe, la clave es incorrecta o la cuenta está inactiva); 422 si faltan campos; 429 por límites (ver abajo) |
| `DELETE /api/sesion` | — | 200 `{success:true, mensaje}` |
| `PATCH /api/perfil/avatar` | `avatar` (entero 1–35) | 200 `{status:'success', message, avatar}`; 401 sin sesión o cuenta desactivada; 422 si no es válido; **419 si el token CSRF no corresponde a la sesión** |

**Límites del inicio de sesión:** 5 intentos/min por pareja usuario+IP, 30/min por IP y **20 fallos cada 15 min por cuenta** (el nombre se normaliza: «juan», «JUAN» y «júan» son la misma cuenta; un inicio correcto reinicia el contador). Todos responden 429 con `Retry-After`. Ojo con los colegios detrás de una sola IP (NAT): comparten el límite por IP.

**Cuenta desactivada:** el estado (`estado_usuario`) se comprueba al iniciar sesión **y en cada petición con sesión** (middleware `cuenta.activa`): al desactivarla en pednia/ambientes la sesión abierta se cierra y responde 401.

**Sesión caducada:** una petición sin sesión recibe 401 (`auth` se evalúa antes que CSRF); con sesión válida y token CSRF viejo recibe 419. El cliente debe tratar 401 y 419 como «sesión caducada». El token CSRF cambia al iniciar y al cerrar sesión: hay que releer la cookie `XSRF-TOKEN` en cada petición.

`usuario_objeto` = `{id, nombre_usuario, login_usuario, grado_usuario, avatar_explora, tipo_usuario}`.
**Nunca** incluye el hash de la contraseña, el correo ni el token. El hash se compara con bcrypt (los prefijos `$2a$`/`$2b$`,
que existen en los datos reales, se aceptan como `$2y$`) y la contraseña **no se recorta** (se conservan los espacios).
`localStorage` ya no es la fuente de verdad: el frontend lo mantiene solo como caché de compatibilidad, sincronizada con `GET /api/sesion` (el historial y el guardado de resultados usan la sesión del servidor).
Las peticiones que escriben deben enviar la cookie `XSRF-TOKEN` (decodificada) en la cabecera `X-XSRF-TOKEN`.
En HTTPS, definir `SESSION_SECURE_COOKIE=true`. Pasos para conectar el frontend: [`GUIA_CONMUTACION_FRONTEND.md`](../DOCUMENTACION_MEJORAMIENTO/GUIA_CONMUTACION_FRONTEND.md).

## Servir el sitio y la API (mismo origen)

La sesión es una cookie `SameSite=Lax` con CSRF, así que el sitio estático y la API deben servirse desde el **mismo origen**.

```bash
# Desarrollo: router que sirve el sitio (laravel/public) y envía /api/* a Laravel, y bloquea lo que no debe servirse
cd laravel && PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 -t public servidor-desarrollo.php
```

El router sirve `index.html` en `/` (con `public/index.php` presente, `php -S` preferiría el front controller) y responde **410**
a los 7 PHP legados sustituidos y **404** a `.git`, `.env`, los `conexion*.php`, cualquier otro `.php` (incluido el chat, que en desarrollo queda desactivado porque usaría credenciales reales)
y a extensiones sensibles. `laravel/.env`, `vendor/`, `storage/` y la documentación ya no están bajo el docroot. **Producción
con Apache:** DocumentRoot = `laravel/public` (ver `../DOCUMENTACION_MEJORAMIENTO/DESPLIEGUE_APACHE.md`; `public/.htaccess` y las
carpetas `php/`).

**Rutas de Laravel fuera de `/api`.** Igual que Apache (`public/.htaccess` envía a `index.php` lo que no es archivo ni carpeta), el
router de desarrollo entrega a Laravel toda ruta que no existe en disco (tras los filtros de seguridad), así que `/climatico`
funciona, `/climatico.html` redirige (301) y lo desconocido da el 404 de Laravel (sin trazas). La guarda de bases `_test`
(`EXPLORA_SOLO_BD_PRUEBA=1`) se aplica a TODA petición que llegue a Laravel, no solo a `/api`.

### Mapas temáticos (Fase 10)

Las cinco páginas de mapas comparten la vista `mapas/tematico.blade.php`; lo que cambia entre ellas vive en `config/mapas.php`.
Los HTML estáticos antiguos se eliminaron; las URL `*.html` (que siguen usando `seleccionarTipoMapa()` de `general.js`,
compartido con `index.html`) redirigen con 301. El JS inline idéntico entre páginas es ahora `public/js/mapa-tematico.js`
(script clásico, funciones globales porque el HTML usa `onclick=`), parametrizado por `window.EXPLORA_MAPA`, que imprime la
vista con `@json` (con las banderas `JSON_HEX_*`).

**Cómo añadir un mapa:**
1. Colocar sus datos en `public/` (el JSON de Mapplic, el SVG e imágenes en su carpeta `maps_*`).
2. Añadir una entrada a `config/mapas.php` con el slug como clave y las claves documentadas allí (`titulo`, `json`,
   `carpeta_imagenes`, `tutorial`, `variante`, `dato_interes`, `ocultar_niveles`, `quiz_anidado`, `css`). La ruta `/{slug}` y la
   redirección `/{slug}.html` salen solas (no hay que tocar `routes/web.php`).
3. Si usa `seleccionarTipoMapa()`, enlazarlo desde `partials/modal-tipo-mapa.blade.php` y `public/mapplic/js/general.js`.
4. Añadir el caso a `tests/Feature/MapasTematicosTest.php` (`mapas()`) y a `tests/Browser/{mapas,recursos}.spec.js`.

**Compuerta de equivalencia:** `php tests/Equivalencia/comparar-paginas.php [--ver-diferencias] [referencia-git]` compara el HTML
original (`git show <ref>:laravel/public/<slug>.html`, por defecto `HEAD`; tras confirmar la Fase 10 usar el commit anterior) con
el renderizado, normalizando espacios, URL de `asset()` y JS inline, y falla si hay diferencias no admitidas (documentadas en la
cabecera del script).

Cliente común del sitio: [`public/js/explora-api.js`](public/js/explora-api.js) (`window.ExploraApi`): URL de la API desde cualquier
profundidad, cabecera `X-XSRF-TOKEN` leída de la cookie en cada petición, mensajes de error unificados, sincronización de la sesión
y funciones para escapar HTML y validar avatares. Se incluye **después de jQuery** en todas las páginas que usan la API.

## Pruebas

El PHP legado y el sitio viven ahora bajo `public/`; `tests/Legacy/serve.sh` copia `public/mapplic/php` y `public/juegos/php`.

```bash
# Contra bases *_test (las pruebas lo verifican y abortan si no lo son)
php artisan test                         # toda la suite
php artisan test --testsuite=Contract    # contrato legado <-> Laravel
php artisan test --testsuite=Feature     # modelos, perfil y autenticación

# Las pruebas de Characterization y Contract comparan con el legado: arrancar antes su copia
tests/Legacy/serve.sh                    # puerto 8081; se detiene con Ctrl+C (o pkill -f "php.*-S 127.0.0.1:8081")
```

Sin el servidor del legado, esas pruebas se **omiten** (no fallan). `phpunit.xml` fuerza `APP_DEBUG=false` y las bases
`explora_test` / `pedigital_test`; `tests/TestCase.php` aborta si la conexión no es local o no termina en `_test`.

Estilo: `./vendor/bin/pint`. `pint.json` excluye `public/mapplic`, `public/juegos` y `public/ViajaPED` (PHP legado que no se reformatea; JSON no admite comentarios, por eso la explicación va aquí).

### Pruebas en navegador (Playwright)

```bash
npm install                        # una vez
npx playwright install chromium    # una vez: descarga el Chromium de Playwright a ~/Library/Caches/ms-playwright (fuera del repo)
npm run test:e2e                   # levanta el router en el puerto 8090 contra las bases *_test y corre las specs de tests/Browser
```

Las specs cubren bloqueos del servidor, sesión, ficha, quiz (por teclado, doble clic y 500 simulado), perfil, juegos y XSS en
ranking e historial. Siembran y limpian sus propios datos (`tests/Browser/fixtures.php`) y **abortan si las bases no son `*_test`**.
**Versión fijada:** `@playwright/test` 1.60.0, porque Playwright 1.65 ya no soporta macOS 13 con ningún navegador. Al actualizar,
comprobar antes que el sistema operativo sea compatible. Las carpetas `test-results/` y `playwright-report/` están ignoradas.

## Variables de entorno propias

| Variable | Valor por defecto | Para qué sirve |
|---|---|---|
| `SESSION_SECURE_COOKIE` | `false` | Cookie de sesión solo por HTTPS. **Poner `true` en producción** |
| `CORS_ALLOWED_ORIGINS` | vacío | Otros orígenes permitidos (separados por comas). Vacío = solo el mismo origen |
| `QUIZ_INCLUIR_RESPUESTAS` | `true` | Enviar `correcta`/`respuesta` al navegador (ver aviso en la API del quiz) |
| `AVATARES_MAX` | `35` | Cantidad de avatares (`juegos/images/avatars/N.png`) |
| `API_POR_MINUTO` | `300` | Peticiones de lectura por minuto, por IP (o por usuario con sesión). Subido de 60 porque un aula entera comparte una IP (NAT) y cada página sincroniza la sesión al cargar |
| `RESULTADOS_POR_MINUTO` | `10` | Partidas que puede guardar cada usuario (o IP, si es invitado) por minuto |
| `PEDIGITAL_DB_*` | — | Conexión de solo lectura a la base de usuarios |
| `LEGACY_DB_HOST/USER/PASS` | — | **Solo pruebas:** usuario MySQL con escritura únicamente sobre las bases `*_test`, usado por el arnés del legado y por las pruebas que preparan escenarios en `pedigital_test` |
| `LEGACY_BASE_URL` | `http://127.0.0.1:8081` | Servidor del legado para las pruebas (debe ser local) |
| `LEGACY_MEMORY` | `1G` | Memoria del servidor del legado (con `128M` falla la ficha del departamento 10) |

## Convenciones del proyecto

1. **Documentación obligatoria.** Todo código nuevo se documenta en español: cada clase, trait, método público,
   migración, ruta, opción de configuración y prueba explica su propósito, parámetros (`@param`), retorno (`@return`),
   errores y, cuando exista, el hallazgo (`SEC-`, `FUN-`, `DAT-`…) o la decisión que lo motivó. Las decisiones no
   obvias se justifican en el propio código. Los métodos de prueba llevan una descripción de qué comprueban y por qué.
   Pint elimina los `@return`/`@param` que solo repiten el tipo, por lo que se escriben **con descripción** (que sí se
   conserva). Solo se omite en archivos de esqueleto de Laravel sin modificar. Si cambia el código, se actualiza su
   documentación en el mismo cambio.
2. **Lista blanca en las respuestas.** Nunca se devuelve un modelo tal cual: se usan Resources que enumeran los campos
   permitidos, para que una columna nueva (o un secreto) no salga por accidente.
3. **Sin SQL concatenado.** Solo Eloquent / Query Builder con parámetros; las rutas aceptan ids numéricos (`whereNumber`).
4. **`pedigital` es solo lectura.** Lo que Explora necesita guardar de un usuario va en `explora` (p. ej. `perfiles_explora`).
   Única excepción: algunas pruebas preparan un escenario (p. ej. desactivar una cuenta) en `pedigital_test` con el usuario
   `LEGACY_DB_*`, restringido a bases `*_test`, y lo restauran; nunca se hace en una base real.
5. **Identidad desde la sesión.** El usuario nunca se toma de un dato enviado por el cliente (SEC-03).
6. **Pruebas primero** para todo endpoint que reemplace al legado: contrato (misma forma) y casos de seguridad.
7. **Migraciones aditivas e idempotentes;** `down()` nunca borra datos. Cualquier cambio destructivo requiere autorización.
8. **Nombres de la base de datos tal cual**, incluidos los errores ortográficos heredados (`opciones_verfal`,
   `pasword_usuario`, `update_at`): no «corregirlos» sin una migración autorizada. Los modelos documentan estos casos.
9. **Atributo frente a relación.** Eloquent da prioridad a la columna; una relación no puede llamarse como una columna
   de su tabla (`ModelosSinChoquesTest` lo verifica).

## Problemas conocidos

Resumidos de `../DOCUMENTACION_MEJORAMIENTO/MATRIZ_HALLAZGOS.md`: imágenes base64 en el contenido (DAT-07), el legado
falla con la ficha del departamento 10 (FUN-11), FK ausentes o rotas en la base (DAT-02, DAT-04), fecha de resultados
como texto (DAT-03). Ninguno se corrige en la base sin autorización.
