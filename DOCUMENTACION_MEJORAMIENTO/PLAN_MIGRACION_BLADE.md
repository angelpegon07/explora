# PLAN: MIGRACIÓN COMPLETA A LARAVEL 10 (FRONTEND EN BLADE, IA Y RETIRO DEL LEGADO)

Estado: **PROPUESTO** (2026-11-19), pendiente de aprobación del responsable.
Continúa el trabajo de las Fases 0–8 (ver `INFORME_FINAL.md` y `laravel/README.md`).

## 1. Punto de partida

| Capa | Hoy | Meta |
|---|---|---|
| Backend / API | Laravel 10 (sesión, fichas, quiz, juegos, perfil) | Igual + chat y corrector con IA |
| Páginas | 6 mapas + `miPerfil` + `corrector` + portal de juegos + 7 juegos + línea de tiempo: HTML estático en la raíz, servido junto a Laravel | Vistas Blade con layouts y parciales, rutas web de Laravel |
| Documento raíz del servidor | Raíz del repo (mezcla sitio, PHP legado y `laravel/`) | **Solo `laravel/public/`** |
| Assets | CSS/JS sueltos en la raíz, copias duplicadas, `build-sitio.mjs` *(histórico: el script y `dist/` ya se retiraron en la Fase 9)* | `laravel/public/` + Vite para lo propio |
| PHP legado | `mapplic/php/*`, `juegos/php/*` (7 bloqueados con 410; chat activo) | Eliminado |

Del grafo de código (`graphify-out/`): el núcleo del backend ya está desacoplado (controladores finos → modelos
Eloquent; `Usuario` es el único puente entre autenticación, perfil y modelos de solo lectura). Lo que falta migrar es la
**capa de presentación** y la **IA**, no el dominio.

## 2. Decisiones tomadas (2026-11-19)

1. «De raíz» = **frontend en Blade**, Laravel sirve todo.
2. **Se incluyen** el chat y el corrector con IA y el retiro del PHP legado (rotación de credenciales incluida).
3. Se conserva lo ya decidido: Laravel 10 (con los 5 avisos ignorados), `pedigital` solo lectura, avatar en `perfiles_explora`, migración gradual, código nuevo documentado, **no tocar los 3 `conexion*.php` ajenos** y confirmar siempre por ruta (nunca `git add -A`).

## 3. Principios

- **Estrangulador, no big bang:** cada fase deja el sitio funcionando y con pruebas en verde (PHPUnit 118 + Playwright 53 hoy).
- **Mismas URLs públicas:** cada página HTML antigua (`/climatico.html`, etc.) redirige 301 a la ruta nueva hasta decidir retirarla.
- **Una página por vez**, empezando por las simples; `index.html` (956 líneas, la más compleja) va después de tener layout y plantilla de mapa probados *(histórico: la portada se migró en la Fase 11 y `index.html` ya no existe)*.
- **Nada de reescribir las librerías de terceros** (Mapplic, jQuery, SweetAlert2, driver.js, Bootstrap): se sirven tal cual desde `public/` y se actualizan aparte.
- **Prueba antes y después:** capturas y pruebas Playwright de la página original sirven de contrato de la Blade.
- Rama nueva `migracion-blade` desde `main` (etiqueta de recuperación `pre-migracion-blade`).

## 4. Fases

### Fase 9 — Cimientos (sin cambiar páginas visibles)
- Mover assets a `laravel/public/` conservando rutas (`css/`, `js/`, `mapplic/`, `bootstrap/`, `fontawesome/`, `images/`, `img/`, `maps*/`, `jsons/`, `sounds/`, `sweetalert/`, `driver/`, `multimedia/`). Se mueven con `git mv` para conservar historial; se elimina `build-sitio.mjs`/`dist/` al final de la fase.
- ~~Layouts Blade, parciales compartidos, rutas `web.php`/`PaginaController` y redirecciones 301~~: **se pospone a la Fase 10** (los layouts y parciales solo se crean cuando una página los consume).
- Servidor de desarrollo y `.htaccess` actualizados al nuevo documento raíz; `PROPUESTA_DESPLIEGUE.md` corregida.
- **Salida:** la raíz del sitio abre desde `laravel/public/` con el HTML antiguo intacto (solo movido). Playwright en verde.

### Fase 10 — Mapas temáticos (5 páginas, una plantilla) — ✔ HECHA
`climatico`, `coordilleras`, `etnias`, `parques_naturales`, `vertientes` comparten estructura: **una vista `mapa` parametrizada**
(título, JSON, carpeta de mapas, script Mapplic) + datos en `config/mapas.php`. Se migra primero `climatico` (la más simple),
se verifica contra la captura original y se replica. Cierra M07–M11.
**Hecha:** `layouts/sitio`, parciales en `resources/views/partials`, `config/mapas.php`, `MapaTematicoController`, `public/js/mapa-tematico.js`, redirecciones 301 `*.html` y el router de desarrollo entregando a Laravel las rutas inexistentes; verificada con `tests/Equivalencia/comparar-paginas.php`. Detalle en `REGISTRO_CAMBIOS.md`.

### Fase 11 — Mapa principal (`index`) y fichas — ✔ HECHA
Vista `inicio` con los parciales ya probados; el script inline de ~150 líneas se extrae a `resources/js/` (Vite) con la
configuración del mapa pasada desde Blade (`@json`). Cubre M01–M03 y M12 (tutorial). Es la fase de mayor riesgo visual.
**Hecha:** vista `inicio` en `/` (`InicioController`), parciales `ficha-departamento`, `lightbox-imagen`, `test-quiz`/`test-aviso`, JS en `public/js/{mapa-comun,inicio,mapa-tematico}.js` (clásico, sin Vite: ver riesgos), `/index.html` -> 301 a `/`, `.htaccess` y router de desarrollo entregan `/` a Laravel, pendientes de la Fase 10 cerrados (`quiz_anidado` y `</div>` sobrante) y `seleccionarTipoMapa()` con URL sin `.html`. Equivalencia verificada con `tests/Equivalencia/comparar-paginas.php`. Detalle en `REGISTRO_CAMBIOS.md`.

### Fase 12 — Perfil y portal de juegos — ✔ HECHA
`miPerfil` y `juegos/index` en Blade con la sesión de Laravel (el nombre y el avatar se pintan en servidor, no desde
`localStorage`; `localStorage` queda solo como caché cosmética). Cubre M05, M06 y M13.
**Hecha:** `layouts/base` (esqueleto) con `layouts/sitio` extendiéndolo sin cambiar su salida; `PerfilController` (`/perfil`) y
`PortalJuegosController` (`/juegos`) con la sesión del guard `web` (`Support/SesionServidor`); reglas explícitas para `/juegos` y
`/juegos/` (carpeta real) en `.htaccess` y en `servidor-desarrollo.php`; 301 de `/miPerfil.html` y `/juegos/index.html`;
compuerta de equivalencia ampliada (portal + comprobación estructural del perfil). Detalle en `REGISTRO_CAMBIOS.md`.

### Fase 13 — Los 7 juegos — ✔ HECHA
`laberinto`, `banderas-color`, `puzzle`, `sopa_letras`, `juego_ahorcado`, `arrastra`, `completa` en Blade con un layout común de juego.
**Decisión heredada M19–M21 (resuelta):** `arrastra` y `completa` SÍ son accesibles (el portal los enlaza desde su modal) y se migran como los
demás; `juegos/juegos/mapas` NO es una página: son solo imágenes (`gris/`, `marron/`) referenciadas por 6 juegos.
**Revisión de `banderas-color`:** tiene 69 archivos (los «2.217» eran anteriores a la Fase 7B); su inventario de duplicados y peso está en
`INVENTARIO_JUEGOS.md` (sin borrar nada: la unificación de librerías queda recomendada para una fase posterior).
**Hecha:** `layouts/juego` (extiende `base`) con parciales en `juegos/partials`, vistas `juegos/juego/<slug>`, `config/juegos.php` (lista cerrada,
una sola fuente de verdad), `JuegoController`, URL canónica `/juegos/juegos/<slug>/` CON barra (las rutas relativas de cada juego dependen de ella;
sin barra y `/index.html` dan 301), regla explícita en `.htaccess` y en `servidor-desarrollo.php`, `<style>` inline extraído a `estilo-pagina.css` y el
`<script>` inline de sopa a `juego.js`, compuerta de equivalencia ampliada con los 7 juegos y chequeo de rutas literales. Detalle en `REGISTRO_CAMBIOS.md`.

### Fase 14 — IA: chat y corrector (Fase 6 original)
Requiere decidir antes (ver `PENDIENTE_TRABAJO_IA.md`): dónde corre el modelo, proveedor/privacidad (menores), límites de uso,
alcance del chat, si el corrector sigue existiendo.
- `ConsultaIAService` con timeout corto, lista blanca de salida y doble de la IA en pruebas.
- Endpoints `/api/chat` y `/api/corrector` con Form Request, Resource, `throttle`, usando los modelos Eloquent existentes.
- El corrector pasa a Blade y deja de hablar con Ollama desde el navegador.

### Fase 15 — Contenido restante y retiro del legado — ✔ HECHA (2026-11-20)
**Hecha:** `lineaTiempo` y `ViajaPED` a Blade (`PaginaEstaticaController`, `config/paginas_estaticas.php`, vistas `paginas/<slug>`; URL canónica con barra,
regla en `.htaccess` y en el router; `ViajaPED` NO se compiló con Vite: nunca usó React, es JS clásico y quedó como página estática con su `script.js`);
retirados con `git rm` los 7 PHP legados sustituidos por la API, los 3 `conexion*.php`, los 2 `.htaccess` de `php/` (su 410 pasó a una ruta de Laravel,
`config/servicios_retirados.php`), el arnés de pruebas (`tests/Legacy`, `tests/Characterization` y las 5 pruebas «API = legado»), `ViajaPED/package*.json`
y `jsons/unir.html`. La historia completa está en la etiqueta `pre-retiro-legado`. Detalle en `REGISTRO_CAMBIOS.md`.
**Quedó SIN tocar, a propósito (decisión del responsable, sección 9):** `mapplic/php/chat.php`, `chat copy.php`, `mapplic/js/chat.js` (y los parciales de chat) y
`corrector.html`; por tanto el «cliente viejo del chat» y los dos `chat*.php` **no se eliminaron** y la Fase 15 NO cumple todavía la definición de «terminado» de la
sección 7 (queda PHP plano en `mapplic/php/`). **Rotar las credenciales de MySQL** sigue pendiente: es decisión del responsable (siguen en `chat*.php` y en el historial de git,
también las de los `conexion*.php` retirados); no se ejecutó.

### Fase 16 — Datos y cierre (con autorización sobre datos)
**Fase 16A (imágenes base64 a archivos) — solución implementada, pendiente de aplicar en producción (2026-11-21):** comando `php artisan explora:externalizar-imagenes`
(`App\Console\Commands\ExternalizarImagenes` + `App\Support\ImagenesEmbebidas`): dry-run por defecto, `--aplicar`, `--revertir`, `--verificar`, guardas `_test`/`_migtest`
o `--base-real --confirmo-respaldo`, archivos `public/contenido/fichas/<2 hex>/<sha256>.<ext>` (fuera de git; reglas en `.htaccess` y en el router). Verificado en el sandbox
`explora_migtest`; **NO se ha ejecutado contra la base real** (procedimiento en `PROPUESTA_DESPLIEGUE.md`, paso 8). Marca DAT-07/REN-03/FUN-11 como «solución implementada,
pendiente de aplicar en producción». DAT-02/04/05 (fecha a `datetime`, claves foráneas) siguen pendientes.
**Fase 16B (esquema, fechas, índices y FK) — implementada y verificada en bases de prueba; pendiente de aplicar en producción (2026-11-21):** migraciones `2026_11_21_000003` (`resultado_juegos.creado_en` + índices del ranking y del historial; patrón expandir/contraer, con doble escritura en el código) y `2026_11_21_000004` (índices en `preguntas`/`opciones_verfal` y FK `departamento_descripcion.departamento → list_departamentos.id` solo si no hay huérfanas), más el comando de solo lectura `php artisan explora:auditar-integridad`. **Las migraciones NO se han aplicado a la base real `explora`** (sí a `explora_test` y al sandbox `explora_migtest`); orden de despliegue: respaldo → auditoría → migrar → código → `explora:externalizar-imagenes` (`PROPUESTA_DESPLIEGUE.md`, «Fase 16B»). Quedan: la fase «contraer» de DAT-03 (leer `creado_en`, retirar `fecha`), la decisión del dueño del contenido sobre las 4 filas huérfanas de `opciones_verfal` (sin FK hasta entonces), `preguntas.departamento` (polimórfica, sin FK), `UNIQUE` en las tablas 1:1 (duplicados), DAT-02 y la conversión de charsets (solo reportados).
DAT-02/04/05/07: fecha a `datetime`, claves foráneas, imágenes base64 a archivos (la ficha del depto 10 pesa ~46 MB). Pruebas en
Firefox y Safari, informe final y despliegue (ver `PROPUESTA_DESPLIEGUE.md`).

## 5. Orden y dependencias

```
9 cimientos ─► 10 mapas ─► 11 index ─► 12 perfil/portal ─► 13 juegos ─► 15 legado ─► 16 datos
                                   └──────────► 14 IA (independiente tras la 9; antes de la 15)
```

Cada fase = una o más sesiones con commits propios, pruebas Playwright/PHPUnit y entrada en `REGISTRO_CAMBIOS.md`.

## 6. Riesgos y mitigación

| Riesgo | Mitigación |
|---|---|
| Rutas relativas de assets rotas al pasar a Blade (`../mapplic/…`) | Un helper único de assets y prueba de recursos (`recursos.spec.js`) en cada fase |
| Regresión visual en `index` | Capturas antes/después; la página va tras haber probado la plantilla de mapas |
| Scripts clásicos con jQuery global incompatibles con módulos de Vite | Mantenerlos clásicos; Vite solo para código nuevo y minificado |
| `localStorage` y sesión desincronizados | Servidor es la fuente de verdad desde la Fase 12 |
| IA sin decisiones de proveedor/privacidad | Fase 14 no arranca hasta cerrar la lista de `PENDIENTE_TRABAJO_IA.md` |
| Cambios locales ajenos en 3 `conexion*.php` | No tocarlos; confirmar por ruta |
| Laravel 10 sin parches de seguridad | Decisión aceptada; revisar al cerrar la fase 16 |

## 7. Definición de «terminado»

Sin HTML ni PHP plano de aplicación fuera de `laravel/`; el servidor sirve solo `laravel/public/`; PHPUnit y Playwright en
verde; credenciales rotadas; documentación y `README` al día.

## 8. Decisiones del líder tras la Fase 10 (2026-11-19)
- **Pendiente para la Fase 11 (RESUELTO en la Fase 11: la clave `quiz_anidado` ya no existe en `config/mapas.php`; se conserva el texto original como histórico):** unificar el `.modal-body.quizmodal` anidado (clave `quiz_anidado` en `config/mapas.php`) y quitar el `</div>` sobrante tras `#contenedorVideo`, ambos defectos heredados que se conservaron para garantizar la equivalencia.
- **Sesión en las páginas Blade:** se mantiene el grupo `web` (la cookie de sesión/CSRF se necesita de todos modos para `/api/sesion`).
- **`general.js`:** en la Fase 11, al migrar `index`, `seleccionarTipoMapa()` pasará a las URL sin `.html`; las redirecciones 301 quedan solo para marcadores antiguos.
- **Compuerta de equivalencia:** los HTML originales ya no están en HEAD; el script usa por defecto `0c60734` para `index` y `0c7c594` para los mapas (configurable con `--ref=<pagina>:<commit>`).

## 9. Decisión de IA (2026-11-20)
La **Fase 14 (chat y corrector con IA) queda CANCELADA en este repositorio**: el chat se aplaza y el corrector se desarrollará solo en el proyecto biblioteca (ver `PENDIENTE_TRABAJO_IA.md`). La **Fase 15 no debe eliminar** `chat.php`, `chat copy.php`, `chat.js` ni `corrector.html` mientras no se retome ese trabajo; sí puede retirar el resto del PHP legado ya sustituido por la API.
