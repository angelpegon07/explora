# ESQUEMA DE BASE DE DATOS (real)

Fuente: dumps `explora.sql` (MySQL 8.0.41, SQLyog; ~360 MB) y `pedigial.sql` (~322 KB) entregados por el responsable. **Solo se extrajeron las sentencias `CREATE TABLE`; no se leyeron ni se reproducen datos** (los dumps contienen datos personales reales de estudiantes). Los archivos permanecen en `~/Documents`, fuera del repositorio, y `*.sql` está en `.gitignore`.

## 1. Base `explora` — 39 tablas

### Contenido por departamento (InnoDB, utf8mb4)
- `list_departamentos` (`id`, `nombre`, `capital`) — 33 registros (AUTO_INCREMENT 34).
- `departamento_descripcion` (`id`, `departamento`, `poblacion`, `clima`, `temperatura`, `extension`, `estado`, `estado_registro`, `contenido`, `geografia`, `actividad_economica`, `bandera`, `escudo`, timestamps). **Sin FK** hacia `list_departamentos`.
- Tablas 1:1 con FK `departamento → departamento_descripcion.id` (ON DELETE CASCADE): `capital`, `clima`, `cultura` (descripcion, arte, danza, musica), `demografia` (poblacion, densidad, subregiones), `economia`, `etnografia` (`contenido` **longblob**), `fauna_flora`, `gastronomia`, `geografia` (ubicacion, coordenadas, limites, relieve), `hidrografia`, `historia`, `referencia` (referencia, webgrafia).
- Listas 1:N (mismo FK): `etnografia_list` (nombre, nombre_alterno, lengua, contenido), `fauna_list`, `flora_list`, `festividad_list`, `personaje_list`, `sitios_list` (nombre, destacada `char(2)`, contenido; columna `update_at` en lugar de `updated_at`).

### Quiz
- `preguntas` (`id`, `departamento`, `tipo_pregunta`, `pregunta`, `origen_pregunta`) — sin FK.
- `opciones_multiple` (`pregunta` FK CASCADE, `opcion`, `correcta char(1)`).
- `opciones_verfal` (`pregunta`, `respuesta char(1)`) — sin FK, nombre con error tipográfico.

### Regiones (no usado por el código revisado)
`regiones` (7 registros) y 13 tablas `regiones_*` (clima, cultura, demografia, economia, etnografia, fauna_flora, festividades, gastronomia, geografia, hidrografia, historia, referencias, sitios).

### Juegos y usuarios
- `resultado_juegos` (`id`, `nombre_juego`, `nivel`, `tiempo` int, `jugador`, `avatar`, `grado`, `puntaje` int, `categoria`, `id_usuario` int, `fecha`) — **todas las columnas de texto son `text`, incluida `fecha`**; sin FK; 113 filas en producción. **Cambios de la Fase 16B (aplicados SOLO a `explora_test`/`explora_migtest`, NO a la base real):** columna nueva `creado_en DATETIME NULL` (rellenada desde `fecha`; `fecha` y los tipos existentes no cambian) e índices `idx_resultado_ranking (nombre_juego(32), nivel(16), categoria(100), puntaje DESC, tiempo ASC)` e `idx_resultado_historial (id_usuario, id)` (migración `2026_11_21_000003`). `id_usuario` apunta a `pedigital.users.id` (otra base: sin FK posible).
- `users` (nombre_usuario, login_usuario, pasword_usuario, tipo_usuario, email_usuario, foto_usuario, estado_usuario, remember_token, timestamps; utf8mb3) — **no la usa el código revisado**.

## 2. Base `pedigital` — tabla `users` (1 tabla en el dump)
`id`, `nombre_usuario`, `login_usuario`, `pasword_usuario varchar(100)`, `tipo_usuario`, `email_usuario`, `remember_token`, `created_at`, `updated_at`, `estado_usuario`, `grado_usuario varchar(2)`, `avatar_explora int DEFAULT 1`. utf8mb3. Sí existen **roles/estado** (`tipo_usuario`, `estado_usuario`); sus valores no se leyeron.

## 3. Observaciones para el diseño de la migración
1. Las convenciones (`remember_token`, `created_at`/`updated_at`) indican que el esquema nació de una aplicación tipo Laravel (probable panel de administración externo). **Posible escritor de estas tablas**: confirmar con el responsable, porque Laravel solo debe leer/escribir lo que corresponda.
2. Las FK de las tablas hijas apuntan a `departamento_descripcion.id`, no a `list_departamentos.id`: el modelo Eloquent debe reflejarlo (o normalizarse con migración no destructiva).
3. `avatar_explora` es una columna de `pedigital.users`; con `pedigital` en solo lectura se necesita la tabla propuesta `perfiles_explora` (decisión pendiente).
4. El peso de ~360 MB sugiere contenido grande (HTML/imágenes en `longtext`/`longblob`); por confirmar qué tabla lo concentra.

## 4. Capa Eloquent (Fase 2, `app/Models`)

| Modelo | Tabla | Notas |
|---|---|---|
| `Explora\Departamento` | `list_departamentos` | `hasOne descripcion` (FK `departamento`) |
| `Explora\DepartamentoDescripcion` | `departamento_descripcion` | Eje de la ficha: 12 `hasOne` (capital, climaDetalle, cultura, demografia, economia, etnografia, faunaFlora, gastronomia, geografiaDetalle, hidrografia, historia, referencia), 6 `hasMany` (etnias, fauna, flora, festividades, personajes, sitios) y `preguntas` |
| `Explora\Departamento{Capital,Clima,Cultura,Demografia,Economia,Etnografia,FaunaFlora,Gastronomia,Geografia,Hidrografia,Historia,Referencia}` | `departamento_*` | Base `SeccionDepartamento`; `belongsTo descripcion` |
| `Explora\{EtnografiaItem,FaunaItem,FloraItem,FestividadItem,PersonajeItem,SitioItem}` | `departamento_*_list` | Base `ItemDepartamento` (`UPDATED_AT = 'update_at'`) solo para fauna, flora, festividad, personaje y sitios; `EtnografiaItem` usa `updated_at` y hereda de `SeccionDepartamento` |
| `Explora\Pregunta`, `OpcionMultiple`, `OpcionVerdaderoFalso` (antes `OpcionVerfal`; la tabla conserva su nombre) | `preguntas`, `opciones_multiple`, `opciones_verfal` | Dos relaciones según el tipo de pregunta |
| `Explora\ResultadoJuego` | `resultado_juegos` | Único modelo de `explora` con escritura (juegos) |
| `Pedigital\Usuario` | `pedigital.users` | **Solo lectura**; oculta hash, token y correo; `getAuthPassword()` apunta a `pasword_usuario` |

Decisiones: los modelos de contenido usan el trait `SoloLectura` (la fuente de esas tablas parece ser otra aplicación; se levanta cuando se confirme). Las relaciones `clima` y `geografia` se llaman `climaDetalle()` y `geografiaDetalle()` porque `departamento_descripcion` ya tiene columnas con esos nombres y Eloquent da prioridad al atributo (error detectado y corregido en las pruebas). No se modelaron `regiones_*` ni `explora.users` (sin uso en el código; pendientes de decisión, DAT-02/DAT-06).

## 5. Tabla nueva de la migración (2026-11-18)
`perfiles_explora` (`id`, `usuario_id` int unsigned **único** = `pedigital.users.id`, `avatar` smallint unsigned default 1, `created_at`, `updated_at`; InnoDB, utf8mb4). Sin FK (otra base de datos). Modelo `Explora\PerfilExplora` con `avatarDe(int $usuarioId)`: perfil propio → si no existe, `pedigital.users.avatar_explora` (lectura) → 1. Migración aditiva e idempotente (`2026_11_18_000002_crear_perfiles_explora.php`); `down()` solo borra la tabla si está vacía. Aplicada únicamente en `explora_test`; **su creación en producción requiere autorización** (Fase 8).

## 6. Cambios de integridad de la Fase 16B (2026-11-21; NO aplicados a la base real)
Migración `2026_11_21_000004` (aditiva, idempotente, con `down()`), aplicada solo a `explora_test` y `explora_migtest`:
- Índices `idx_preguntas_departamento` (`preguntas.departamento`) e `idx_opciones_verfal_pregunta` (`opciones_verfal.pregunta`): no tenían ninguno.
- FK `fk_departamento_descripcion_departamento`: `departamento_descripcion.departamento → list_departamentos.id`, ON UPDATE CASCADE, ON DELETE RESTRICT (más el índice de apoyo `idx_departamento_descripcion_departamento`). Se crea solo si no hay huérfanas (33 filas, 0 huérfanas en la copia de prueba).
- Total de FK: 32 existentes (18 `departamento_*` → `departamento_descripcion.id` CASCADE; `opciones_multiple.pregunta` → `preguntas.id`; 13 `regiones_*` autorreferenciadas) + 1 nueva = 33.

**Medido y NO cambiado** (se reporta con `php artisan explora:auditar-integridad`):
- `opciones_verfal.pregunta → preguntas.id`: 486 filas, **4 huérfanas** (opciones 223 → pregunta 590, 277 → 719, 278 → 720, 279 → 721). Sin FK hasta que el dueño del contenido decida qué hacer con ellas.
- `preguntas.departamento` es **polimórfica**: 986 filas con `origen_pregunta='departamentos'` apuntan a `departamento_descripcion.id` y 150 con `'regiones'` a `regiones.id` (0 huérfanas en ambas ramas): no admite una FK única.
- Duplicados de `departamento` en tablas 1:1 (capital 1, cultura 1, etnografia 1, fauna_flora 1, geografia 1, hidrografia 2, historia 2): impiden `UNIQUE(departamento)`.
- FK autorreferenciadas de `regiones_*` (DAT-02), colaciones mezcladas (`utf8mb4_0900_ai_ci` 38 tablas, `utf8mb4_unicode_ci` 1, `utf8mb4_spanish_ci` 1 [`resultado_juegos`], `utf8mb3_spanish_ci` 1 [`users`]) y las relaciones entre bases (`resultado_juegos.id_usuario`, `perfiles_explora.usuario_id` → `pedigital.users`).
