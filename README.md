# Explora — migración a Laravel 10

Nueva base de código de **Explora Colombia y aprende** (sitio educativo sobre geografía, cultura e historia de
Colombia). Reemplazó **de forma incremental** los 12 scripts PHP planos del sitio original. Desde la **Fase 15** los 7
sustituidos por la API se eliminaron (siguen en la etiqueta git `pre-retiro-legado`; ver «Retiro del PHP legado»); solo quedan
`public/mapplic/php/chat.php` y `chat copy.php` (el chat con IA queda fuera de este repositorio).

> Contexto, auditorías, decisiones y registro de cambios: [`DOCUMENTACION_MEJORAMIENTO/`](DOCUMENTACION_MEJORAMIENTO/)
> (empezar por `REGISTRO_CAMBIOS.md` e `IMPLEMENTACION_MEJORAS.md`).

> **Estructura del repositorio.** Desde la reestructuración (etiqueta de recuperación `pre-reestructura-raiz`) el proyecto Laravel
> vive en la RAÍZ del repositorio (`app/`, `public/`, `artisan`, `composer.json`…) y `DOCUMENTACION_MEJORAMIENTO/` es la carpeta
> de documentación. Hasta el commit 53eee80 la aplicación estaba en la subcarpeta `laravel/`. Todos los comandos de este README
> se ejecutan desde la raíz del repositorio.

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
| 9 | Cimientos Blade: `public/` es el único documento raíz y contiene todo el sitio (HTML intacto, solo movido) | ✔ |
| 10 | Mapas temáticos (`/climatico`, `/coordilleras`, `/etnias`, `/parques_naturales`, `/vertientes`): una vista Blade parametrizada + layout y parciales reutilizables | ✔ |
| 11 | Portada (`/`): mapa general + ficha de departamento + quiz en Blade, con los mismos layout y parciales; `/index.html` redirige a `/` | ✔ |
| 12 | Perfil (`/perfil`) y portal de juegos (`/juegos`) en Blade con la sesión pintada en servidor | ✔ |
| 13 | Los 7 juegos (`/juegos/juegos/<slug>/`) en Blade con un layout común de juego | ✔ |
| 14 | Chat y corrector con IA | cancelada en este repositorio (chat aplazado; el corrector se desarrolla en otro proyecto) |
| 15 | `lineaTiempo` y `ViajaPED` en Blade; retiro del PHP legado sustituido (7 scripts) y de las pruebas que dependían de él | ✔ |
| 16A | Imágenes base64 de las fichas a archivos: comando `explora:externalizar-imagenes` (dry-run por defecto, `--aplicar`, `--revertir`, `--verificar`). **Implementado y verificado en el sandbox; NO ejecutado contra la base real** (pendiente de autorización) | ✔ (código) / pendiente de aplicar |
| 16B | Esquema, fechas, índices y FK: migraciones `…000003` (`resultado_juegos.creado_en` + índices) y `…000004` (índices y FK de `departamento_descripcion`), doble escritura de `creado_en` y comando de solo lectura `explora:auditar-integridad`. **Aplicadas solo a `explora_test`/`explora_migtest`; NO a la base real** (pendiente de autorización) | ✔ (código) / pendiente de aplicar |
| 16C | Guía de despliegue definitiva (`DOCUMENTACION_MEJORAMIENTO/GUIA_DESPLIEGUE.md`), plantillas y scripts en `deploy/`, y **ensayo completo sobre Apache + PHP-FPM reales** (cierra el hueco «Apache con PHP real nunca se probó»). **Nada se desplegó en un servidor real** | ✔ |

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
                         (*.json, css/, js/, mapplic/, juegos/, images/, maps*/…; el ÚNICO HTML estático que queda es `corrector.html`;
                         del PHP legado solo queda mapplic/php/chat*.php).
                         Desde la Fase 11 no hay `index.html`: la portada `/` es una vista Blade. Desde la Fase 12 tampoco
                         `miPerfil.html` ni `juegos/index.html` (perfil y portal de juegos son vistas Blade); las carpetas
                         `miPerfil/` y `juegos/` siguen existiendo con sus CSS/JS.

app/
  Http/
    Controllers/InicioController.php        Portada `/` (Fase 11)
    Controllers/PerfilController.php        Perfil `/perfil`, pintado en servidor con la sesión (Fase 12)
    Controllers/PortalJuegosController.php  Portal de juegos `/juegos`, barra según la sesión del servidor (Fase 12)
    Controllers/JuegoController.php         Las 7 páginas de juego `/juegos/juegos/<slug>/`; la forma sin barra redirige (Fase 13)
    Controllers/PaginaEstaticaController.php  `/lineaTiempo/` y `/ViajaPED/` (carpetas con assets); la forma sin barra y `index.html` redirigen (Fase 15)
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
  Support/SesionServidor Usuario de la sesión del servidor SOLO si su cuenta está activa (lo usan perfil y portal)
  Exceptions/Handler     Toda la ruta api/* responde JSON
  Providers/RouteServiceProvider   Limitadores de peticiones (`login`, `api`)
config/explora.php       Opciones propias (respuestas del quiz, cantidad de avatares)
config/juegos.php        Lista cerrada de los 7 slugs de juego (arreglo PHP plano: también lo lee `servidor-desarrollo.php`) (Fase 13)
config/paginas_estaticas.php  Lista cerrada de las 2 páginas con carpeta de assets, `lineaTiempo` y `ViajaPED` (arreglo plano: también lo lee `servidor-desarrollo.php`) (Fase 15)
config/servicios_retirados.php  Los 7 PHP legados retirados que responden 410 (arreglo plano: también lo lee `servidor-desarrollo.php`) (Fase 15)
config/mapas.php         Un arreglo por mapa temático (clave = slug de la URL): título, JSON, carpeta de imágenes, tutorial, variante y CSS
resources/views/
  layouts/sitio.blade.php   Doctype, <head> y scripts comunes (todo con asset()); secciones `titulo`, `contenido`, `hojas_orden` (orden de
                            CSS por página) y `script_mapplic`, y pilas `estilos`/`scripts`
  layouts/juego.blade.php   Layout común de los 7 juegos (extiende `base`; pilas `cabecera` y `pie`, ver «Los 7 juegos (Fase 13)»)
  juegos/juego/<slug>       Una vista por juego; juegos/partials: terminar-css/js, explora-api, pantalla-css/js
  inicio.blade.php          La portada `/` (mapa general + ficha + quiz)
  partials/                 Bloques reutilizables de portada y mapas: mapa-contenedor, panel-contenido, chat (variante `chatCompleto`),
                            modal-pantalla-completa (`relleno`), modal-tipo-mapa, multimedia (video/imágenes), test-quiz (portada),
                            test-aviso (mapas), ficha-departamento, lightbox-imagen, menu-circular, menu-accesibilidad, final
  paginas/                  lineaTiempo.blade.php y ViajaPED.blade.php (layout `base`; Fase 15)
  mapas/                    tematico.blade.php (la página), estilos.blade.php (CSS por mapa) y dato-interes.blade.php (solo climatico)
routes/web.php           `GET /` (portada), `GET /index.html` -> 301 a `/`, `GET /{slug}` (lista cerrada desde config/mapas.php) y `GET /{slug}.html` -> 301
public/js/mapa-comun.js  JS común de portada y mapas (acordeón, modales, pestañas de la ficha, visores, accesibilidad); lee `window.EXPLORA_MAPA`
public/js/inicio.js      JS de la portada (Mapplic con sus opciones, búho del test, visor ampliado de imágenes)
public/js/mapa-tematico.js  JS de los mapas temáticos (dato de interés y opciones de Mapplic temática)
database/migrations/     Migración base (esquema real, idempotente) y perfiles_explora
routes/api.php           Rutas de la API
tests/
  Contract/              Forma de la ficha y del quiz que consume el frontend (33 departamentos; ya sin comparar con el legado)
  Feature/               Modelos, perfil, autenticación, juegos, servidor de desarrollo, mapas, juegos, páginas estáticas y 410 retirados
  Equivalencia/          comparar-paginas.php: compara el HTML original de cada página migrada con el renderizado (compuerta)
  Browser/               Playwright (npm run test:e2e)
```
(Desde la Fase 15 no existen `tests/Characterization/` ni `tests/Legacy/`: ver «Retiro del PHP legado».)

## API

En la columna «Reemplaza a», cada script PHP legado **ya no existe** (Fase 15): su URL responde 410 Gone (ver «Retiro del PHP legado»).

Todas las rutas responden **JSON** (incluidos 404 y errores). Prefijo `/api`.

### Lectura pública (límite configurable, 300 peticiones/min por IP o usuario)

| Método y ruta | Reemplaza a (retirado, ahora 410) | Respuesta |
|---|---|---|
| `GET /api/departamentos/{id}/ficha` | `consultarInfo.php` | 16 secciones con las claves que lee `mapplic/verMas.js`; 404 `{error}` si no existe |
| `GET /api/departamentos/{id}/preguntas` | `buscarPreguntas.php` | `{preguntas:[{id,tipo_pregunta,pregunta,origen_pregunta,opciones}], departamento:{id,nombre,capital}}` |

`{id}` es `list_departamentos.id` (1–33); cualquier valor no numérico devuelve 404. La ficha puede superar 40 MB
(imágenes en base64 dentro del contenido, hallazgo DAT-07); por eso su ruta eleva `memory_limit` a 512 MB.
Mientras el frontend corrija el quiz en el navegador, las opciones incluyen `correcta`/`respuesta`. **No poner
`QUIZ_INCLUIR_RESPUESTAS=false` con el cliente actual**: sin esos campos todas las respuestas de opción múltiple contarían
como buenas y todas las de verdadero/falso como malas (SEC-06 se cierra migrando antes la corrección al servidor).

### Juegos (Fase 5)

| Método y ruta | Reemplaza a (retirado, ahora 410) | Respuesta |
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
En HTTPS, definir `SESSION_SECURE_COOKIE=true`. Pasos para conectar el frontend: [`GUIA_CONMUTACION_FRONTEND.md`](DOCUMENTACION_MEJORAMIENTO/GUIA_CONMUTACION_FRONTEND.md).

## Servir el sitio y la API (mismo origen)

La sesión es una cookie `SameSite=Lax` con CSRF, así que el sitio estático y la API deben servirse desde el **mismo origen**.

```bash
# Desarrollo: router que sirve el sitio (public/) y envía /api/* a Laravel, y bloquea lo que no debe servirse
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080 -t public servidor-desarrollo.php
```

También sirve `php artisan serve` (puerto 8000): el archivo `server.php` de la raíz del repositorio delega en el mismo router. Sin él, el router
por defecto de `artisan serve` dejaba un «Not Found» en `/juegos` y en `/juegos/juegos/<slug>/` (carpetas reales de `public/`). **Si ya
tenías `artisan serve` en marcha, reinícialo** para que lo use.

El router entrega `/` a Laravel (la portada es una vista Blade desde la Fase 11) y responde **410**
a las 7 URL de los PHP legados retirados (`config/servicios_retirados.php`) y **404** a `.git`, `.env`, `conexion*.php` (ya no existen), cualquier otro `.php` (incluido el chat, que en desarrollo queda desactivado porque usaría credenciales reales)
y a extensiones sensibles. `.env`, `vendor/`, `storage/` y la documentación ya no están bajo el docroot. **Producción
con Apache:** DocumentRoot = `<raíz del repositorio>/public` (ver `DOCUMENTACION_MEJORAMIENTO/DESPLIEGUE_APACHE.md`; solo `public/.htaccess`: las
carpetas `php/` ya no tienen `.htaccess`, el 410 lo da Laravel).

**Rutas de Laravel fuera de `/api`.** Igual que Apache (`public/.htaccess` envía a `index.php` lo que no es archivo ni carpeta), el
router de desarrollo entrega a Laravel toda ruta que no existe en disco (tras los filtros de seguridad), así que `/climatico`
funciona (también `/`, `/perfil` y `/juegos`), `/index.html` y `/climatico.html` redirigen (301) y lo desconocido da el 404 de Laravel (sin trazas). La guarda de bases `_test`
(`EXPLORA_SOLO_BD_PRUEBA=1`) se aplica a TODA petición que llegue a Laravel, no solo a `/api`.

### Mapas temáticos (Fase 10)

Las cinco páginas de mapas comparten la vista `mapas/tematico.blade.php`; lo que cambia entre ellas vive en `config/mapas.php`.
Los HTML estáticos antiguos se eliminaron; `seleccionarTipoMapa()` de `general.js` (compartido con la portada) navega ya a las
URL sin `.html` y las antiguas redirigen con 301 solo para marcadores viejos. El JS inline idéntico entre páginas es ahora
`public/js/mapa-comun.js` (común con la portada) y `public/js/mapa-tematico.js` (scripts clásicos, funciones globales porque el
HTML usa `onclick=`), parametrizados por `window.EXPLORA_MAPA`, que imprime la vista con `@json` (con las banderas `JSON_HEX_*`).

**Cómo añadir un mapa:**
1. Colocar sus datos en `public/` (el JSON de Mapplic, el SVG e imágenes en su carpeta `maps_*`).
2. Añadir una entrada a `config/mapas.php` con el slug como clave y las claves documentadas allí (`titulo`, `json`,
   `carpeta_imagenes`, `tutorial`, `variante`, `dato_interes`, `ocultar_niveles`, `css`). La ruta `/{slug}` y la
   redirección `/{slug}.html` salen solas (no hay que tocar `routes/web.php`).
3. Si usa `seleccionarTipoMapa()`, enlazarlo desde `partials/modal-tipo-mapa.blade.php` y `public/mapplic/js/general.js`.
4. Añadir el caso a `tests/Feature/MapasTematicosTest.php` (`mapas()`) y a `tests/Browser/{mapas,recursos}.spec.js`.

### Layouts: cuándo usar cada uno (Fase 12)

- `layouts/base`: esqueleto mínimo (doctype, `<html lang="es">`, charset, título, viewport, favicon y puntos de extensión
  `cabecera`, `estilos`, `contenido`, `scripts_pie`). Úsalo en páginas con **su propio conjunto de CSS/JS**: perfil, portal de
  juegos y los juegos (Fase 13).
- `layouts/sitio`: extiende a `base` y añade las ~15 hojas y las librerías que comparten la portada y los mapas temáticos
  (Mapplic, Bootstrap 4, SweetAlert, chat, tutorial…). Su salida no cambió con la extracción (la compuerta de equivalencia
  sigue en verde).

### Perfil y portal de juegos (Fase 12)

**El servidor es la fuente de verdad de la sesión.** La cookie de sesión es la misma para la API y las páginas (grupo `web`), así
que las vistas usan el guard `web`; `App\Support\SesionServidor::usuarioActivo()` devuelve el usuario solo si su cuenta está activa
(una cuenta inactiva se trata como «sin sesión», sin escribir en ninguna base). `localStorage` queda como caché cosmética para el
JS de los juegos (Fase 13).

- **`/perfil`** (`PerfilController`, vista `perfil/index`): con sesión se pintan en servidor nombre, usuario, grado (opción
  seleccionada), avatar vigente (`PerfilExplora::avatarDe`, validado 1..`avatares_max`) y la lista de avatares
  (`config('explora.avatares_max')`, ya no 35 fijos en JS). Sin sesión (o cuenta inactiva) solo el aviso «Debe iniciar sesión…»:
  ni formulario, ni historial, ni datos, y no se carga `miPerfil/script.js` (nadie pide `/api/historial`). Respuesta con
  `Cache-Control: private, no-store`. No recibe ids: solo la sesión. Todo con `{{ }}` (nombre y usuario vienen de una base externa).
  `miPerfil/script.js` queda para lo interactivo (historial con `textContent`, avatar con CSRF, caducidad 401/419);
  `miPerfil/cerrar-sesion.js` es el antiguo script inline.
- **`/juegos`** (`PortalJuegosController`, vista `juegos/portal`): la barra pinta en servidor `#btn-perfil` y `#btn-cerrar` (con
  sesión) o `#btn-iniciar` (sin ella), sin parpadeo; `mapplic/js/iniciarSesion.js` no toca esos botones hasta que
  `/api/sesion` confirma la sesión (`sesionConfirmada`) y después los mantiene al día tras login/logout. `Cache-Control: private,
  no-cache`. `juegos/js/anio-pie.js` es el antiguo script inline del año.
- **Carpeta frente a ruta.** `public/miPerfil/` y `public/juegos/` son carpetas reales y ni Apache (`RewriteCond !-d`) ni el router
  de desarrollo envían carpetas a Laravel. Por eso el perfil está en `/perfil` (no `/miPerfil`) y `/juegos` y `/juegos/` (solo esas
  dos) se envían a Laravel con la regla `RewriteRule ^juegos/?$ index.php [L]` de `public/.htaccess` y, en
  `servidor-desarrollo.php`, con la comprobación `$minusculas === ['juegos']`. `/juegos/css/…`, `/juegos/juegos/…` siguen siendo
  estáticos y el resto de carpetas sin index siguen sin listarse (403 en Apache por `-Indexes`, 404 en el router).
- **Redirecciones 301** (conservan la consulta): `/miPerfil.html` → `/perfil` y `/juegos/index.html` → `/juegos`. Las ~20 páginas
  de juegos que enlazan `../../index.html` siguen funcionando por esa redirección (se conservan tal cual en la Fase 13).
- **Equivalencia:** el portal anónimo se compara con su original (`bb4b68b`) en la compuerta. El perfil no es comparable por
  texto (formulario frente a aviso): la compuerta hace una comprobación estructural (cada `id` del original existe con sesión,
  las funciones de los `onclick` siguen definidas, mismo texto del aviso que el JS, sin datos sin sesión) y PHPUnit
  (`PerfilPortalTest`) cubre el resto.

### Los 7 juegos (Fase 13)

`laberinto`, `banderas-color`, `puzzle`, `sopa_letras`, `juego_ahorcado`, `arrastra` y `completa` son vistas Blade
(`resources/views/juegos/juego/<slug>.blade.php`) sobre `layouts/juego`, servidas por `JuegoController`. La lógica de cada juego
(`script.js`, `game.js`…, `terminar_juego.js`, `pantalla_completa.js`) NO cambió; los juegos no reciben datos del servidor.

- **URL canónica CON barra final:** `/juegos/juegos/<slug>/`. Casi todo lo que carga un juego en ejecución (`fetch('js/preguntas.json')`,
  `$.getJSON('datos.json')`, `../sounds/*.mp3`, `url()` de CSS) es relativo a la URL de la página, así que NO se usa `<base>` ni se
  movieron assets. La forma sin barra y `/<slug>/index.html` redirigen (301, conservan la consulta) a la canónica
  (`JuegoController::redirigirACanonica`; ojo: `redirect()->to()` recorta la barra final, por eso usa `away()`).
- **Lista de juegos = `config/juegos.php`** (arreglo plano, una sola fuente). La leen `routes/web.php` (`where()` de lista cerrada, el
  resto da 404), `servidor-desarrollo.php` (`require`) y `JuegosPaginasTest`, que comprueba que la regla de `public/.htaccess`
  (`RewriteRule ^juegos/juegos/(…)/?$ index.php [L]`, antes de las genéricas) lista exactamente los mismos slugs. La regla solo casa la
  carpeta (no sus archivos) y no habilita listados. Verificada con Apache 2.4.68 (sin PHP): con barra llega al front controller; sin
  barra, mod_dir redirige antes con 301 (equivalente); los JSON, CSS y mp3 se sirven con su content-type y `…/js/` da 403.
- **Layout `layouts/juego`:** no impone ningún recurso. Cada vista declara en SU orden (cascada y dependencias) `@push('cabecera')`
  (CSS y JS del `<head>` en una sola pila) y `@push('pie')` (scripts del final), más `@section('titulo' | 'viewport' | 'atributos_body' | 'juego')`.
  `@section('sin_viewport', 'sí')` suprime el `<meta viewport>` (sopa_letras y juego_ahorcado no lo tenían; `layouts/base` lo admite).
  Parciales `juegos/partials/`: `terminar-css`, `explora-api`, `terminar-js`, `pantalla-css`, `pantalla-js`. Particularidades que se
  conservan: sopa carga `pantalla_completa.js` antes que `terminar_juego.js`; arrastra no carga `pantalla_completa.css`; banderas-color usa su
  propio Bootstrap 4.6.2/jQuery 3.7.1/SweetAlert 11.17 y no el Bootstrap 5.3 compartido.
- **CSS/JS inline extraídos:** el `<style>` de cada juego es `public/juegos/juegos/<slug>/estilo-pagina.css`, enlazado en la misma posición
  del `<head>`; el `<script>` inline de sopa es `sopa_letras/juego.js` (sin tocar el código, incluido el muerto `readText`). Viven en la
  carpeta del juego, así que sus rutas relativas resuelven igual que antes.
- **Compuerta de equivalencia:** `comparar-paginas.php` compara también los 7 juegos con su `index.html` de `b97b191` (el CSS/JS extraído
  se reintegra y se compara texto a texto). Diferencias admitidas: URL con `asset()`, `<style>`/`<script>` extraídos, `<title>`
  duplicado, `lang="es"` (banderas decía `en`; sopa no lo tenía) y `<meta charset>` en sopa. `JuegosRutasTest` comprueba que las rutas
  literales de cada juego existen (salvo 2 conocidas rotas, ver `DOCUMENTACION_MEJORAMIENTO/INVENTARIO_JUEGOS.md`).
- **Seguridad:** sin `{!! !!}` ni datos del servidor en las vistas; el `nombre_juego` enviado a `/api/resultados` sigue escrito en el JS de cada
  juego; endpoints y CSRF sin cambios.

### Portada (Fase 11)

`/` es la vista `inicio` (`InicioController`), con el mismo layout y los mismos parciales que los mapas; la ficha del departamento
(`partials/ficha-departamento`) es una copia literal del HTML original porque `public/mapplic/verMas.js` depende de sus ids.
`/index.html` redirige (301) a `/`. En Apache `DirectoryIndex index.php index.html` entrega `/` a Laravel (`/juegos/` lo atiende Laravel
desde la Fase 12 con su propia regla; ver más abajo). La portada usa `mapplic/mapplic.js` y su propio orden de CSS (`@section('hojas_orden')`).

**Compuerta de equivalencia:** `php tests/Equivalencia/comparar-paginas.php [--ver-diferencias] [--ref=<pagina>:<commit>]...` compara
el HTML original de `index` (por defecto en `0c60734`), de los cinco mapas (`0c7c594`), del portal `juegos` (`bb4b68b`), de los 7 juegos (`b97b191`) y de `lineaTiempo`/`ViajaPED` (`25acea1`) con el renderizado,
normalizando espacios, URL de `asset()` y JS inline, y falla si hay diferencias no admitidas (documentadas en la cabecera del script).
Además hace la comprobación estructural del perfil (necesita las bases de prueba: el script las fuerza antes de arrancar Laravel).

Cliente común del sitio: [`public/js/explora-api.js`](public/js/explora-api.js) (`window.ExploraApi`): URL de la API desde cualquier
profundidad, cabecera `X-XSRF-TOKEN` leída de la cookie en cada petición, mensajes de error unificados, sincronización de la sesión
y funciones para escapar HTML y validar avatares. Se incluye **después de jQuery** en todas las páginas que usan la API.

## Calidad de código (Fase 17A: plataforma)

Sin cambiar de versión: se mantienen **Laravel 10 y PHP 8.2** (mínimo 8.1).

```bash
composer lint        # Pint en modo comprobación (lint:fix lo aplica)
composer analyse     # Larastan (PHPStan) nivel 5; el defecto conocido está en phpstan-baseline.neon
composer rector      # Rector en modo informativo (solo muestra); rector:fix aplica, revisar el diff
composer qa          # lint + analyse
```
- **Larastan 2.x** (la 3.x exige Laravel 11+). Para subir de nivel: cambiar `level` en `phpstan.neon`, corregir y, solo si hace falta, regenerar el baseline con `vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon`.
- **Rector** (`rector.php`) solo aplica `UP_TO_PHP_81` y código muerto; no sube PHP ni Laravel.
- **CI** (`.github/workflows/ci.yml`): Pint, Larastan, `composer audit` y pruebas unitarias en cada PR. Rector es informativo. Las suites Feature y Contract **no corren en CI** porque necesitan `explora_test` y `pedigital_test` con datos reales (el volcado no se versiona).

### Docker (opcional, solo desarrollo)
```bash
docker compose up -d                       # app en http://localhost:8080 + MySQL 8 (puerto 3307 del host)
docker compose --profile frontend up -d    # además Vite
```
`docker/mysql-init.sql` crea las bases (`explora`, `pedigital`, `*_test`, `explora_migtest`) y los usuarios; **los datos hay que importarlos del volcado**.
El compose no es la configuración de producción (ver `GUIA_DESPLIEGUE.md`).

## Caché de lectura (Fase 17B)

`GET /api/departamentos/{id}/ficha` y `/preguntas` pasan por `App\Http\Middleware\CacheLecturaPublica`: guardan el cuerpo en la caché de Laravel
(`EXPLORA_CACHE_TTL`, 1 h) y responden con `ETag` + `Cache-Control: public, max-age=300, must-revalidate`, con 304 si el navegador ya tiene la versión.
Las fichas mayores que `EXPLORA_CACHE_MAX_BYTES` (2 MB; las que aún llevan imágenes base64) no se guardan en el servidor, pero sí llevan ETag.
Tras cambiar el contenido o ejecutar `explora:externalizar-imagenes`: `php artisan explora:limpiar-cache-lectura`. `EXPLORA_CACHE_TTL=0` la desactiva.
Para que rinda en producción, `CACHE_DRIVER` debe ser `file`, `redis` o `database` (no `array`).

## Identidad (Fase 17D)

El dominio no conoce `pedigital.users`: depende de `App\Contracts\IdentidadUsuario` (nombre, login, grado, rol, `esActivo()`) y `App\Contracts\ProveedorIdentidad`
(buscar por login, avatar heredado). El único adaptador es `App\Support\Identidad\PedigitalProveedorIdentidad`, enlazado en `AppServiceProvider`; el modelo
`Pedigital\Usuario` implementa el contrato. Para cambiar de proveedor (otra base, SSO) hay que escribir un adaptador, enlazarlo y apuntar `config/auth.php` a un modelo que
cumpla `IdentidadUsuario`. `tests/Feature/IdentidadContratoTest.php` impide que otro archivo de `app/` lea las columnas del legado. El comportamiento del login no cambió.

## API (Fase 17E)

- **Versionado.** La API vive en `/api/v1/*` (rutas con nombre `v1.*`). Las rutas sin versión, `/api/*`, son el legado
  **deprecado**: mismos controladores, con las cabeceras `Deprecation` y `Link`, y cuerpos de error antiguos. Los clientes
  nuevos deben usar `/api/v1`.
- **Errores.** En la v1 todo error 4xx/5xx es `application/problem+json` (RFC 9457): `type` (`about:blank`), `title`,
  `status` (entero), `detail` e `instance`. Se conservan como extensiones las claves que lee el frontend: `mensaje` y
  `message` (siempre, igual a `detail`), `campos` (formularios de resultados y ranking), `success` (inicio de sesión),
  `error` (404 de ficha y quiz) y `errors` (validación estándar de Laravel). Los 500 nunca filtran datos internos.
- **Contrato.** [`docs/openapi.yaml`](docs/openapi.yaml) (OpenAPI 3.1, en español) describe rutas, esquemas, cabeceras de
  caché, seguridad (cookie de sesión + CSRF) y todas las respuestas de error. `tests/Feature/OpenApiTest.php` lo mantiene
  alineado con las rutas reales y con respuestas reales; si cambia la API, hay que cambiar también el documento.
- **Decisión: Sanctum NO se adopta.** La sesión por cookie con CSRF ya cubre el modo SPA de Sanctum, no hay consumidores
  con tokens y añadir `personal_access_tokens` ampliaría la superficie de ataque sin beneficio; se reevaluará si aparece
  una app móvil o un tercero.

## Pruebas

```bash
# Contra bases *_test (las pruebas lo verifican y abortan si no lo son). Ya NO necesitan ningún servidor del legado.
php artisan test                         # toda la suite (Unit, Feature y Contract)
php artisan test --testsuite=Contract    # forma de la ficha y del quiz que consume el frontend
php artisan test --testsuite=Feature     # modelos, perfil, autenticación, juegos, páginas, router de desarrollo, 410
php tests/Equivalencia/comparar-paginas.php   # compuerta de equivalencia de las páginas migradas
```

`phpunit.xml` fuerza `APP_DEBUG=false` y las bases `explora_test` / `pedigital_test`; `tests/TestCase.php` aborta si la
conexión no es local o no termina en `_test`. **Nombre histórico de variables:** `AutenticacionTest` y `PerfilPortalTest` leen
`LEGACY_DB_USER` / `LEGACY_DB_PASS` de `.env` (usuario MySQL con escritura solo sobre `*_test`, para preparar escenarios en
`pedigital_test`). Se llaman «LEGACY» porque antes también las usaba el arnés del PHP legado, que ya no existe; **no se renombraron**
para no obligar a editar el `.env` de nadie.

### Imágenes de las fichas fuera de la base (Fase 16A)

Las fichas guardan imágenes como `<img src="data:image/...;base64,...">` dentro del HTML de la base `explora` (DAT-07: la ficha del
depto 10 pesaba 48,6 MB). El comando saca esas imágenes a archivos que sirve Apache y deja en la base solo la URL.
**El comando NO se ha ejecutado contra la base real**; se desarrolló y verificó solo en `explora_test`/`explora_migtest`.
El procedimiento para producción está en `DOCUMENTACION_MEJORAMIENTO/GUIA_DESPLIEGUE.md` (§5.8 y §7.3).

```bash
php artisan explora:externalizar-imagenes                       # DRY-RUN (por defecto): no escribe nada; cifras por tabla/columna
php artisan explora:externalizar-imagenes --aplicar             # escribe archivos y actualiza filas (una transacción por fila)
php artisan explora:externalizar-imagenes --revertir            # vuelve a incrustar base64 leyendo los archivos
php artisan explora:externalizar-imagenes --verificar=storage/logs/externalizar-imagenes-<fecha>.json   # compuerta posterior
```

| Opción | Efecto |
|---|---|
| `--dry-run` | Modo por defecto. Imprime filas afectadas, nº de imágenes, MB de base64 a quitar, archivos y MB a crear, duplicados, tipos reales y anomalías. No escribe ni archivos ni filas ni informe |
| `--aplicar` | Escribe `<destino>/<2 hex>/<sha256>.<ext>` y sustituye SOLO el valor del `src` por `/contenido/fichas/<2 hex>/<sha256>.<ext>` |
| `--revertir` | Reincrusta las URL de `contenido_url` como `data:` leyendo los archivos (comprueba su sha256). **Funcionalmente equivalente, no idéntico byte a byte**: el MIME declarado pasa a ser el real (p. ej. un GIF que decía `image/png` vuelve como `image/gif`) y el base64 es el estándar. No borra los archivos |
| `--verificar=<informe>` | Comprueba un informe de `--aplicar`: cada archivo existe con su sha256 y cada fila contiene su URL. Código de salida 1 si algo falla. No modifica nada |
| `--tabla=a,b` `--columna=x,y` | Limitan el recorrido (solo seleccionan entre lo descubierto; nunca se usan como identificador directo) |
| `--limite=N` | Máximo de filas por columna |
| `--destino=<carpeta>` | Otra carpeta de destino, **dentro de `public/`** (por defecto `config('explora.contenido_dir')` = `public/contenido/fichas`) |
| `--conexion=<nombre>` | Conexión de base de datos (por defecto la del proyecto) |
| `--base-real` + `--confirmo-respaldo` | Necesarias las DOS juntas para `--aplicar`/`--revertir` en una base cuyo nombre no acaba en `_test` o `_migtest` |

**Cómo funciona.** Descubre por `INFORMATION_SCHEMA` todas las columnas `char/varchar/*text/*blob` de la base conectada que contienen
`data:image/` (sin listas escritas a mano; `config('explora.externalizar_excluir')` excluye tablas técnicas como `sessions`, cuyo PHP
serializado se rompería al cambiar longitudes) y procesa fila por fila por clave primaria: nunca carga una tabla entera. Para cada
`<img src="data:...">`: decodifica base64 de forma estricta, detecta el tipo REAL por magic bytes (png, jpg, gif, webp; el MIME declarado
miente) y nombra el archivo con el sha256 del contenido (deduplica y es idempotente). Otros tipos (SVG incluido) y los `data:image` que no
son el `src` de un `<img>` se dejan intactos y se reportan; una fila con base64 inválido se deja entera sin tocar y se reporta. Cada fila
va en su transacción (bloqueo `FOR UPDATE` solo mientras dura): primero se escriben los archivos (temporal + `rename`, verificando el
hash) y luego se actualiza la fila; si algo falla, esa fila no cambia. Una segunda ejecución no cambia nada.
El resto del HTML queda byte a byte igual; no se tocan `updated_at` ni otras columnas.

**Guardas de seguridad.** `--aplicar` y `--revertir` se niegan a correr si la base no acaba en `_test`/`_migtest` salvo `--base-real` Y
`--confirmo-respaldo` (el responsable confirma que hizo un `mysqldump`). `--destino` se rechaza si tiene `..`, byte nulo, queda fuera de
`public/`, es `public/` mismo o pasa por un enlace simbólico que sale del docroot. Los nombres de archivo salen solo del hash. Las
sentencias son parametrizadas y los identificadores los escapa el constructor de consultas.

**Informe.** `--aplicar` y `--revertir` escriben `storage/logs/externalizar-imagenes-<fecha>.json` (tabla, columna, clave, nº de imágenes,
sha256 y bytes; nunca el contenido): sirve de inventario, para `--verificar` y para saber qué revertir.

**Servir los archivos.** `public/contenido/` está en `.gitignore` (nunca se versiona: producción lo genera con el comando a partir de los datos
reales). `public/.htaccess` solo sirve png/jpg/gif/webp bajo `/contenido/` (todo lo demás da 404) con
`Cache-Control: public, max-age=31536000, immutable`; `servidor-desarrollo.php` aplica la misma regla de tipos. No usar `storage:link`:
el router de desarrollo rechaza enlaces simbólicos que salen del docroot.

**Memoria.** Con las imágenes fuera, la ficha más pesada pasa de 27,9 MB (depto 14; el 10 de 48,6 MB) a 0,5 MB y el pico de PHP de 208 MB a 22 MB.
`MemoriaParaFichas` sigue en 512M (configurable con `FICHAS_MEMORIA`): código y datos se despliegan por separado, y bajarlo antes de aplicar el
comando en producción rompería la ficha del depto 10. Después de aplicar y verificar, basta `FICHAS_MEMORIA=128M` en el `.env`.

**Pruebas.** `tests/Feature/ExternalizarImagenesTest.php` usa la base `explora_migtest` (debe existir: `CREATE DATABASE explora_migtest`;
el usuario de la app tiene permisos sobre ella; si no existe, las pruebas se omiten) con tablas propias `tmp_ext_img_*` que borra al terminar,
y una carpeta `public/contenido-prueba-*` que también borra. `tests/Unit/ImagenesEmbebidasTest.php` cubre las funciones puras. El router y
`.htaccess` se vigilan en `ServidorDesarrolloTest` y `tests/Browser/contenido.spec.js`. El router de desarrollo con `EXPLORA_SOLO_BD_PRUEBA=1`
acepta bases `*_test` y `*_migtest`.

### Esquema, fechas, índices y claves foráneas (Fase 16B)

Dos migraciones ADITIVAS (no borran ni cambian el tipo de ninguna columna) y un comando de auditoría de solo lectura.
**No se han aplicado a la base real `explora`**: solo a `explora_test` y al sandbox `explora_migtest`. Procedimiento en
`DOCUMENTACION_MEJORAMIENTO/GUIA_DESPLIEGUE.md` (§5.5 y §7.1).

| Migración | Qué hace |
|---|---|
| `2026_11_21_000003_ampliar_resultado_juegos_creado_en_e_indices` | DAT-03, fase «expandir»: añade `resultado_juegos.creado_en DATETIME NULL` y la rellena desde `fecha` (texto `d-m-Y H:i:s`) donde el formato es válido; lo no interpretable (vacío, basura, `31-02-2026`…) queda en NULL y se cuenta en el aviso. Crea `idx_resultado_ranking (nombre_juego(32), nivel(16), categoria(100), puntaje DESC, tiempo ASC)` e `idx_resultado_historial (id_usuario, id)`. Idempotente; `down()` elimina solo eso |
| `2026_11_21_000004_agregar_indices_y_fk_de_integridad` | DAT-04: índices `preguntas(departamento)` y `opciones_verfal(pregunta)`; FK `departamento_descripcion.departamento → list_departamentos.id` (ON UPDATE CASCADE, ON DELETE RESTRICT) **solo si no hay huérfanas** (si las hay: no falla, avisa con los ids). Idempotente; `down()` elimina solo lo añadido |

**Orden de despliegue obligatorio: respaldo → `explora:auditar-integridad` → mantenimiento → `php artisan migrate --force --path=<migración>` (una a una) → código y cachés → `php artisan up` → `explora:externalizar-imagenes`.**
El código nuevo (`ResultadoJuegoController`/`ResultadoJuego`) escribe TAMBIÉN `creado_en`: sin la migración 000003 aplicada, guardar un resultado falla.
(Con el código viejo y la migración aplicada no pasa nada: la columna admite NULL; esas filas quedan con `creado_en` NULL y la auditoría las cuenta.)
La API no cambia: ranking e historial siguen leyendo y devolviendo `fecha` como texto `d-m-Y H:i:s`; leer `creado_en` y retirar `fecha` es la fase «contraer», pendiente y con autorización.

```bash
php artisan explora:auditar-integridad              # informe legible; código de salida 2 si hay problemas BLOQUEANTES para añadir una FK
php artisan explora:auditar-integridad --json       # lo mismo en JSON (para scripts)
php artisan explora:auditar-integridad --max-ids=50 # más ids de ejemplo por anomalía (por defecto 20; máx. 200)
php artisan explora:auditar-integridad --conexion=<nombre>
```

El comando es de **solo lectura** (solo SELECT/INFORMATION_SCHEMA dentro de una transacción `READ ONLY` que el servidor hace cumplir) y no imprime contenido
de campos largos (ids, conteos y, de `fecha`, una muestra de 30 caracteres sin caracteres de control). Informa de: relaciones lógicas (FK sí/no, huérfanos con ids, tipos y colaciones
compatibles), `preguntas.departamento` (polimórfica, desglosada por `origen_pregunta`), duplicados en las 12 tablas 1:1, FK existentes (y las 13 autorreferenciadas de `regiones_*`),
formatos de `resultado_juegos.fecha` (válidos/no interpretables, con muestra) y `creado_en` NULL, índices esperados presentes/ausentes y colaciones mezcladas. Código de salida:
0 sin bloqueantes, 2 con bloqueantes (huérfanos o tipos incompatibles en una FK candidata), 1 si no pudo ejecutarse. **Con los datos actuales de `explora_test` da 2**: 4 filas
de `opciones_verfal` apuntan a preguntas inexistentes (ids 223, 277, 278, 279); esa FK NO se crea (decisión del dueño del contenido). Se reportan y NO se tocan: `preguntas.departamento` (polimórfica, sin FK
posible), `UNIQUE(departamento)` en las tablas 1:1 (hay duplicados), las FK autorreferenciadas de `regiones_*` (DAT-02), las FK entre bases (`id_usuario`, `usuario_id` → `pedigital.users`) y las colaciones (`utf8mb3`/`utf8mb4`).

**Pruebas.** `tests/Feature/EsquemaIntegridadTest.php` (17) usa el sandbox `explora_migtest` como conexión por defecto (comprueba `SELECT DATABASE()` antes de escribir; nunca toca `explora_test`),
parte del estado «antes de la 16B» en cada prueba y lo restaura al terminar. Para que el código nuevo pase el resto de la suite, las dos migraciones deben estar aplicadas también en `explora_test`
(`DB_DATABASE=explora_test php artisan migrate --path=database/migrations/<archivo>`, una a una).

### Retiro del PHP legado (Fase 15)

Se eliminaron del repositorio (`git rm`), porque la API Laravel ya los sustituye y nadie los incluye:

- los 7 scripts `public/mapplic/php/{iniciarSesion,cambiarAvatar,consultarInfo,buscarPreguntas}.php` y
  `public/juegos/php/{guardar_resultado,consultar_ranking,consultar_historial}.php`;
- los 3 `conexion*.php` (credenciales de MySQL escritas en el código) y los 2 `.htaccess` de esas carpetas (la carpeta
  `public/juegos/php/` desapareció);
- el arnés de pruebas: `tests/Legacy/` (`serve.sh` y su harness) y `tests/Characterization/` (20 pruebas que fijaban el
  comportamiento del legado), la suite `Characterization` de `phpunit.xml` y las 5 pruebas de equivalencia «API = legado»
  (`FichaContractTest`, `QuizContractTest`, 2 en `JuegosTest`, 1 en `ModelosExploraTest`), más sus ayudantes;
- `ViajaPED/package.json` y `package-lock.json` (React/Babel que ninguna página usa) y `jsons/unir.html` (utilidad suelta sin
  referencias que cargaba jQuery desde un CDN).

**Para recuperarlo todo:** la historia completa está en la etiqueta git `pre-retiro-legado`
(p. ej. `git show pre-retiro-legado:laravel/public/mapplic/php/consultarInfo.php` (la etiqueta es anterior a la reestructuración: allí la aplicación estaba en `laravel/`), o `git checkout pre-retiro-legado -- <ruta>`).

**Compatibilidad:** las 7 URL antiguas siguen respondiendo **410 Gone** con `{"status":"error","mensaje":"Servicio retirado"}` (cualquier
método HTTP, sin distinguir mayúsculas, sin cookies ni trazas): en Apache, mediante la ruta `servicio-retirado` de `routes/web.php`
(los archivos ya no existen, así que `public/.htaccess` las envía a `index.php`); en el router de desarrollo, con su propio 410. Ambos
leen la lista de `config/servicios_retirados.php`. **Lo que NO se tocó** (decisión del responsable: el chat con IA se aplaza y el
corrector no se implementa aquí): `public/mapplic/php/chat.php`, `chat copy.php`, `public/mapplic/js/chat.js` (y los parciales de
chat) y `public/corrector.html`. `chat.php` no es un servicio «retirado»: bajo Apache lo sirve PHP como siempre; el router de desarrollo
lo bloquea (404). Sus credenciales siguen en el archivo y en el historial de git.

### `lineaTiempo` y `ViajaPED` (Fase 15)

Son vistas Blade (`resources/views/paginas/<slug>.blade.php`, layout `base`) servidas por `PaginaEstaticaController`, con la misma
mecánica que los juegos: carpetas reales con assets y rutas relativas (`fetch('eventos.json')`, `data.json`, `img/…`), así que la URL
canónica lleva **barra final** (`/lineaTiempo/`, `/ViajaPED/`, respetando mayúsculas); la forma sin barra y `*/index.html` redirigen
(301). La lista de slugs es `config/paginas_estaticas.php` (la leen `routes/web.php`, `servidor-desarrollo.php` y
`PaginasEstaticasTest`, que vigila que la regla `^(lineaTiempo|ViajaPED)/?$` de `public/.htaccess` liste lo mismo). El menú circular
enlaza a esas URL. Equivalencia: `comparar-paginas.php` las compara con su `index.html` de `25acea1`.

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

**Contra un servidor ya levantado (opt-in, Fase 16C).** Con `EXPLORA_BASE_URL=http://127.0.0.1:<puerto>` Playwright no arranca `php -S` ni siembra fixtures:
apunta a ese servidor (p. ej. la pila Apache + PHP-FPM del ensayo de despliegue). Solo se acepta `127.0.0.1`/`localhost` (las pruebas escriben datos) y solo valen las specs que no
dependen de filas sembradas (`bloqueos`, `recursos`, `mapas`, `sesion-ficha-quiz`); el servidor debe usar bases `*_test`/`*_migtest` y límites altos (`API_POR_MINUTO`, `RESULTADOS_POR_MINUTO`).
Contra Apache real fallan 4 pruebas de `bloqueos.spec.js` que describen el router de desarrollo (chat 200 en vez de 404; `conexion*.php` 403 en vez de 404): ver `GUIA_DESPLIEGUE.md`, anexo B.
Sin la variable, el comportamiento es el de siempre.

## Despliegue en producción (Fase 16C)

La guía definitiva es **`DOCUMENTACION_MEJORAMIENTO/GUIA_DESPLIEGUE.md`** (requisitos, arquitectura, usuarios MySQL de mínimo privilegio, `.env`, vhost Apache + PHP-FPM, procedimiento
con la salida esperada de cada comando, verificación, marcha atrás por escenarios, operación y lista de comprobación imprimible). Marca qué se verificó en el ensayo del 2026-11-23
(macOS, httpd 2.4.68 + PHP-FPM 8.2.31, MySQL 8.0.41, HTTPS autofirmado) y qué NO (Linux, nginx, `mod_php`, certificado real, producción real). Los archivos de apoyo están en `deploy/`:

| Archivo | Uso |
|---|---|
| `deploy/env.produccion.ejemplo`, `apache-vhost.conf.ejemplo`, `php-fpm-pool.conf.ejemplo`, `php.ini.ejemplo` | Plantillas sin secretos |
| `deploy/respaldar.sh --base explora [--con-contenido]` | `mysqldump --single-transaction` con comprobaciones (credenciales por archivo, nunca por argumento) |
| `deploy/desplegar.sh` | Guiado e idempotente; **simula por defecto**; `--aplicar`, `--migrar` y `--externalizar` exigen `--respaldo` y confirmación |
| `deploy/verificar.sh https://dominio` | Solo lectura: tabla OK/FALLO con lo esperado; código de salida 1 si algo falla |

Resumen del orden crítico: **respaldo → auditoría → mantenimiento → migrar (una a una con `--path`; nunca `migrate` a secas, aplicaría también la tabla de Sanctum) → código y cachés → quitar
mantenimiento → externalizar imágenes → `FICHAS_MEMORIA=128M`**. `desplegar.sh` y `verificar.sh` se ejercitaron en el ensayo; `PROPUESTA_DESPLIEGUE.md` solo apunta a la guía.

## Variables de entorno propias

| Variable | Valor por defecto | Para qué sirve |
|---|---|---|
| `SESSION_SECURE_COOKIE` | sin definir (`null` en `config/session.php`: la cookie NO se marca `Secure`; `.env.example` trae `false`) | Cookie de sesión solo por HTTPS. **Poner `true` en producción con HTTPS** |
| `TRUSTED_HOSTS` | vacío | Hosts adicionales aceptados en la cabecera `Host` (nombres exactos separados por comas; el de `APP_URL` siempre se acepta, y en `local`/`testing` también `localhost`, `127.0.0.1` y `[::1]`). Otro `Host` da 400 (`App\Http\Middleware\TrustHosts`) |
| `CORS_ALLOWED_ORIGINS` | vacío | Otros orígenes permitidos (separados por comas). Vacío = solo el mismo origen |
| `QUIZ_INCLUIR_RESPUESTAS` | `true` | Enviar `correcta`/`respuesta` al navegador (ver aviso en la API del quiz) |
| `AVATARES_MAX` | `35` | Cantidad de avatares (`juegos/images/avatars/N.png`) |
| `FICHAS_MEMORIA` | `512M` | Memoria de PHP para la ruta de la ficha (`MemoriaParaFichas`). Bajar a `128M` solo tras aplicar `explora:externalizar-imagenes` en producción y verificarlo |
| `API_POR_MINUTO` | `300` | Peticiones de lectura por minuto, por IP (o por usuario con sesión). Subido de 60 porque un aula entera comparte una IP (NAT) y cada página sincroniza la sesión al cargar |
| `RESULTADOS_POR_MINUTO` | `10` | Partidas que puede guardar cada usuario (o IP, si es invitado) por minuto |
| `PEDIGITAL_DB_*` | — | Conexión de solo lectura a la base de usuarios |
| `LEGACY_DB_USER/PASS` | — | **Solo pruebas** (nombre histórico, ver «Pruebas»): usuario MySQL con escritura únicamente sobre las bases `*_test`, que usan las pruebas que preparan escenarios en `pedigital_test`. `LEGACY_DB_HOST`, `LEGACY_BASE_URL` y `LEGACY_MEMORY` ya no se usan (arnés retirado en la Fase 15) |

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
6. **Pruebas primero** para todo endpoint nuevo: contrato (misma forma) y casos de seguridad.
7. **Migraciones aditivas e idempotentes;** `down()` nunca borra datos. Cualquier cambio destructivo requiere autorización.
8. **Nombres de la base de datos tal cual**, incluidos los errores ortográficos heredados (`opciones_verfal`,
   `pasword_usuario`, `update_at`): no «corregirlos» sin una migración autorizada. Los modelos documentan estos casos.
9. **Atributo frente a relación.** Eloquent da prioridad a la columna; una relación no puede llamarse como una columna
   de su tabla (`ModelosSinChoquesTest` lo verifica).

## Problemas conocidos

Resumidos de `DOCUMENTACION_MEJORAMIENTO/MATRIZ_HALLAZGOS.md`: imágenes base64 en el contenido (DAT-07; solución implementada en la Fase 16A, pendiente de aplicar en producción), el legado
falla con la ficha del departamento 10 (FUN-11), FK ausentes o rotas en la base (DAT-02, DAT-04), fecha de resultados
como texto (DAT-03). Ninguno se corrige en la base sin autorización.
