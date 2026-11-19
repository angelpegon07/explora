# REVISIÓN INTEGRAL DE LAS FASES 0 A 2

Fecha: 2026-11-18. Revisión por **5 agentes independientes, de solo lectura**, cada uno con una función y un modelo distintos; el coordinador contrastó los informes y aplicó las correcciones. Los informes de los agentes son salida de modelo y se verificaron antes de actuar.

| Agente | Modelo | Función | Veredicto |
|---|---|---|---|
| Seguridad | Opus | Código y configuración nuevos | Sí, con condiciones |
| Esquema y modelos | Opus¹ | Migración y modelos contra el esquema real y el uso del legado | Sí, con condiciones |
| QA / pruebas | Sonnet | Ejecución real de la suite y calidad de las pruebas | Sí, con condiciones |
| Documentación | Haiku | Veracidad y consistencia de los documentos | 16 discrepancias |
| Git y entorno | Haiku | Integridad del repositorio y de lo que se versionaría | Sí, con condiciones |

¹ Se asignó primero Fable 5.2, que falló por falta de créditos de uso; se relanzó con Opus.

## Verificado y correcto
- **Migración base:** 39/39 tablas, 32/32 FK, índices, nulabilidad, `useCurrent`, charset/collation e idempotencia coinciden con el esquema real (comparación independiente por script, además de la nuestra por `information_schema`).
- **Git:** solo 3 `conexion*.php` ajenos modificados (no tocados); lo no versionado es solo `laravel/` y `DOCUMENTACION_MEJORAMIENTO/`; un `git add` por ruta incluiría 128 archivos (~0,5 MB) sin `.env`, `vendor`, `.sql` ni logs.
- **Suite:** dos ejecuciones completas idénticas (sin pruebas inestables) y sin servidor legado las dependientes se omiten sin fallar.
- **Secretos:** ninguno en documentación ni en código no ignorado; `.env.example` sin valores sensibles.
- **Avisos de Composer:** lista exacta de 5 IDs (la auditoría no se desactivó); no se usan URLs firmadas, Concurrency ni la regla `email` en el código nuevo.

## Defectos hallados y corregidos (código nuevo)
| # | Origen | Defecto | Corrección | Estado |
|---|---|---|---|---|
| 1 | Esquema | La relación `DepartamentoDescripcion::departamento()` chocaba con la columna `departamento` | Renombrada `listaDepartamento()` | Corregido |
| 2 | Esquema | `SeccionDepartamento::descripcion()` chocaba con la columna `departamento_cultura.descripcion` | Renombrada `ficha()` | Corregido |
| 3 | Esquema | `OpcionMultiple/OpcionVerfal::pregunta()` chocaba con la columna `pregunta` | Renombrada `preguntaPadre()` | Corregido |
| 4 | Esquema | Faltaba la collation de `users.email_usuario` | Añadida en la migración | Corregido |
| 5 | Seguridad | `chat copy.php` se servía con credenciales no redirigidas a `*_test` | Se elimina de la copia; el arnés aborta si queda alguna conexión `mysqli` sin redirigir (verificado: 404) | Corregido |
| 6 | Seguridad | `serve.sh` ejecutaba `.env` como shell y exportaba todos los secretos; el `trap` de limpieza no corría por `exec` | Solo carga `LEGACY_DB_*` (y exige que no estén vacías); sin `exec` | Corregido |
| 7 | Seguridad | Las pruebas no impedían correr contra una base real o un servidor remoto | Guarda en `TestCase` (bases `*_test`, hosts locales) y bases forzadas en `phpunit.xml` | Corregido (la guarda no se probó con una configuración errónea) |
| 8 | Seguridad | `SoloLectura` no cubría `update`/`increment` | Añadido `updating`; documentado qué **no** cubre (consultas masivas, `DB::table`) | Corregido |
| 9 | Seguridad | `remember_token` salía al serializar modelos | `$hidden` en las bases de contenido, preguntas y opciones | Corregido |
| 10 | Seguridad | `.env.example` con `APP_DEBUG=true` (contradecía la mitigación de los avisos) | `APP_DEBUG=false` | Corregido |
| 11 | QA | `assertTrue(true)` inflaba aserciones | Sustituido por una aserción real | Corregido |
| 12 | Esquema | Faltaba una defensa contra futuros choques relación/columna | Nueva prueba `ModelosSinChoquesTest` que recorre todos los modelos (al construirla destapó un error propio: `forceDeleting` no existe sin borrado lógico; corregido) | Corregido |
| 13 | Documentación | 16 discrepancias (cifras, estados obsoletos, nombres) | Corregidas en 8 documentos. Incluye un **error mío de la Fase 1**: «carpeta `juegos/index.html/`» era un archivo normal (la ruta con «ñ» se leyó mal). | Corregido |

Suite tras las correcciones: **34 pruebas, 206 aserciones, todas correctas** (con el servidor legado activo).

## Condiciones aceptadas para la Fase 3 (aún no cumplidas: se cumplirán dentro de ella)
1. **Pruebas de contrato antes de los endpoints:** equivalencia completa legado ↔ Laravel de la ficha (todas las claves que lee `verMas.js`, los 33 departamentos o claves y tipos) y del quiz (30 preguntas, opciones, caso inexistente).
2. **Respuestas con API Resources y lista blanca**, nunca modelos serializados: los nombres de relaciones no coinciden con las claves del legado (`clima`, `geografia`, `etnografia.lista`, `faunaFlora.lista_fauna`…); fechas ISO y enteros frente a cadenas `Y-m-d H:i:s` del legado; sin `remember_token`; el campo `correcta` se mantiene solo de forma transitoria para no romper `quiz.js` (SEC-06 se corrige al migrar el cliente).
3. Quitar la ruta de ejemplo `/user` de `routes/api.php`; validación de entrada, consultas por Eloquent y límite de peticiones.
4. En despliegue: usuario de BD con solo `SELECT` sobre el contenido (hoy `explora_app` tiene todos los privilegios, solo sobre `explora_test`), `APP_DEBUG=false` y `composer install --no-dev`.

## Pendiente (fuera de la Fase 3)
- ~~Decisión sobre el avatar~~ **Resuelta (2026-11-18):** el responsable eligió una tabla en `explora`; implementada como `perfiles_explora` (ver REGISTRO_CAMBIOS.md).
- Fase 4: hacer `Usuario` autenticable y reemplazar el `User` del esqueleto y `config/auth.php`.
- Fase 5: validar tipos al escribir (`strict` rechaza `''` en columnas enteras) y tomar `id_usuario` de la sesión.
- Al versionar: añadir `**/.env` al `.gitignore` raíz y hacer `git add` solo por ruta (`laravel`, `DOCUMENTACION_MEJORAMIENTO`), nunca con `-A`.
- Verificar por fuente oficial el fin de soporte de seguridad de Laravel 10 (hoy es una afirmación basada en mi conocimiento).

## Limitaciones de esta revisión
Los agentes no tenían credenciales de MySQL (no pudieron inspeccionar residuos en las bases); no se verificaron los conteos de aserciones citados en los documentos más allá de la suite actual ni el contenido de los dumps.
