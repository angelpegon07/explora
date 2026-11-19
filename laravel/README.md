# Explora — migración a Laravel 10

Nueva base de código de **Explora Colombia y aprende** (sitio educativo sobre geografía, cultura e historia de
Colombia). Reemplaza **de forma incremental** los 12 scripts PHP planos del sitio original (`../mapplic/php`,
`../juegos/php`), que siguen funcionando mientras se migra cada módulo.

> Contexto, auditorías, decisiones y registro de cambios: [`../DOCUMENTACION_MEJORAMIENTO/`](../DOCUMENTACION_MEJORAMIENTO/)
> (empezar por `REGISTRO_CAMBIOS.md` e `IMPLEMENTACION_MEJORAS.md`).

## Estado

| Fase | Contenido | Estado |
|---|---|---|
| 0 | Entorno, bases de prueba, conexión `pedigital` de solo lectura | ✔ |
| 1 | Pruebas de caracterización del PHP legado | ✔ |
| 2 | Migración base, modelos Eloquent, `perfiles_explora` | ✔ |
| 3 | API de lectura: ficha de departamento y quiz | ✔ (sin conectar al frontend) |
| 4 | Autenticación por sesión y avatar | ✔ (sin conectar al frontend) |
| 5 | Juegos: resultados, ranking, historial | pendiente |
| 6 | Chat con IA y corrector | pendiente |
| 7 | Frontend (Vite, accesibilidad, conmutar a la API nueva) | pendiente |

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
app/
  Http/
    Controllers/Api/     Una clase por endpoint: ficha, preguntas, sesión, avatar
    Requests/            Validación de entrada (LoginRequest, CambiarAvatarRequest)
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
database/migrations/     Migración base (esquema real, idempotente) y perfiles_explora
routes/api.php           Rutas de la API
tests/
  Characterization/      Fijan el comportamiento ACTUAL del PHP legado (incluidos sus defectos)
  Contract/              La API nueva entrega al frontend lo mismo que el legado (33 departamentos)
  Feature/               Modelos, perfil y autenticación
  Legacy/                Arnés para servir una copia del legado contra bases *_test
```

## API

Todas las rutas responden **JSON** (incluidos 404 y errores). Prefijo `/api`.

### Lectura pública (límite 60 peticiones/min)

| Método y ruta | Reemplaza a | Respuesta |
|---|---|---|
| `GET /api/departamentos/{id}/ficha` | `consultarInfo.php` | 16 secciones con las claves que lee `mapplic/verMas.js`; 404 `{error}` si no existe |
| `GET /api/departamentos/{id}/preguntas` | `buscarPreguntas.php` | `{preguntas:[{id,tipo_pregunta,pregunta,origen_pregunta,opciones}], departamento:{id,nombre,capital}}` |

`{id}` es `list_departamentos.id` (1–33); cualquier valor no numérico devuelve 404. La ficha puede superar 40 MB
(imágenes en base64 dentro del contenido, hallazgo DAT-07); por eso su ruta eleva `memory_limit` a 512 MB.
Mientras el frontend corrija el quiz en el navegador, las opciones incluyen `correcta`/`respuesta`. **No poner
`QUIZ_INCLUIR_RESPUESTAS=false` con el cliente actual**: sin esos campos todas las respuestas de opción múltiple contarían
como buenas y todas las de verdadero/falso como malas (SEC-06 se cierra migrando antes la corrección al servidor).

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
Los juegos y el historial siguen dependiendo de `localStorage` (`id_usuario`) hasta la Fase 5.
Las peticiones que escriben deben enviar la cookie `XSRF-TOKEN` (decodificada) en la cabecera `X-XSRF-TOKEN`.
En HTTPS, definir `SESSION_SECURE_COOKIE=true`. Pasos para conectar el frontend: [`GUIA_CONMUTACION_FRONTEND.md`](../DOCUMENTACION_MEJORAMIENTO/GUIA_CONMUTACION_FRONTEND.md).

## Pruebas

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

Estilo: `./vendor/bin/pint` (el único archivo que incumple es `bootstrap/app.php`, del esqueleto de Laravel).

## Variables de entorno propias

| Variable | Valor por defecto | Para qué sirve |
|---|---|---|
| `SESSION_SECURE_COOKIE` | `false` | Cookie de sesión solo por HTTPS. **Poner `true` en producción** |
| `CORS_ALLOWED_ORIGINS` | vacío | Otros orígenes permitidos (separados por comas). Vacío = solo el mismo origen |
| `QUIZ_INCLUIR_RESPUESTAS` | `true` | Enviar `correcta`/`respuesta` al navegador (ver aviso en la API del quiz) |
| `AVATARES_MAX` | `35` | Cantidad de avatares (`juegos/images/avatars/N.png`) |
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
