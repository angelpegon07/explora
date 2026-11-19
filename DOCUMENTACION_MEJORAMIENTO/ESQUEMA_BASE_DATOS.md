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
- `resultado_juegos` (`id`, `nombre_juego`, `nivel`, `tiempo` int, `jugador`, `avatar`, `grado`, `puntaje` int, `categoria`, `id_usuario` int, `fecha`) — **todas las columnas de texto son `text`, incluida `fecha`**; sin FK ni índices; 113 filas.
- `users` (nombre_usuario, login_usuario, pasword_usuario, tipo_usuario, email_usuario, foto_usuario, estado_usuario, remember_token, timestamps; utf8mb3) — **no la usa el código revisado**.

## 2. Base `pedigital` — tabla `users` (1 tabla en el dump)
`id`, `nombre_usuario`, `login_usuario`, `pasword_usuario varchar(100)`, `tipo_usuario`, `email_usuario`, `remember_token`, `created_at`, `updated_at`, `estado_usuario`, `grado_usuario varchar(2)`, `avatar_explora int DEFAULT 1`. utf8mb3. Sí existen **roles/estado** (`tipo_usuario`, `estado_usuario`); sus valores no se leyeron.

## 3. Observaciones para el diseño de la migración
1. Las convenciones (`remember_token`, `created_at`/`updated_at`) indican que el esquema nació de una aplicación tipo Laravel (probable panel de administración externo). **Posible escritor de estas tablas**: confirmar con el responsable, porque Laravel solo debe leer/escribir lo que corresponda.
2. Las FK de las tablas hijas apuntan a `departamento_descripcion.id`, no a `list_departamentos.id`: el modelo Eloquent debe reflejarlo (o normalizarse con migración no destructiva).
3. `avatar_explora` es una columna de `pedigital.users`; con `pedigital` en solo lectura se necesita la tabla propuesta `perfiles_explora` (decisión pendiente).
4. El peso de ~360 MB sugiere contenido grande (HTML/imágenes en `longtext`/`longblob`); por confirmar qué tabla lo concentra.

## 4. Capa Eloquent (Fase 2, `laravel/app/Models`)

| Modelo | Tabla | Notas |
|---|---|---|
| `Explora\Departamento` | `list_departamentos` | `hasOne descripcion` (FK `departamento`) |
| `Explora\DepartamentoDescripcion` | `departamento_descripcion` | Eje de la ficha: 12 `hasOne` (capital, climaDetalle, cultura, demografia, economia, etnografia, faunaFlora, gastronomia, geografiaDetalle, hidrografia, historia, referencia), 6 `hasMany` (etnias, fauna, flora, festividades, personajes, sitios) y `preguntas` |
| `Explora\Departamento{Capital,Clima,Cultura,Demografia,Economia,Etnografia,FaunaFlora,Gastronomia,Geografia,Hidrografia,Historia,Referencia}` | `departamento_*` | Base `SeccionDepartamento`; `belongsTo descripcion` |
| `Explora\{EtnografiaItem,FaunaItem,FloraItem,FestividadItem,PersonajeItem,SitioItem}` | `departamento_*_list` | Base `ItemDepartamento` (`UPDATED_AT = 'update_at'`) solo para fauna, flora, festividad, personaje y sitios; `EtnografiaItem` usa `updated_at` y hereda de `SeccionDepartamento` |
| `Explora\Pregunta`, `OpcionMultiple`, `OpcionVerfal` | `preguntas`, `opciones_multiple`, `opciones_verfal` | Dos relaciones según el tipo de pregunta |
| `Explora\ResultadoJuego` | `resultado_juegos` | Único modelo de `explora` con escritura (juegos) |
| `Pedigital\Usuario` | `pedigital.users` | **Solo lectura**; oculta hash, token y correo; `getAuthPassword()` apunta a `pasword_usuario` |

Decisiones: los modelos de contenido usan el trait `SoloLectura` (la fuente de esas tablas parece ser otra aplicación; se levanta cuando se confirme). Las relaciones `clima` y `geografia` se llaman `climaDetalle()` y `geografiaDetalle()` porque `departamento_descripcion` ya tiene columnas con esos nombres y Eloquent da prioridad al atributo (error detectado y corregido en las pruebas). No se modelaron `regiones_*` ni `explora.users` (sin uso en el código; pendientes de decisión, DAT-02/DAT-06).

## 5. Tabla nueva de la migración (2026-11-18)
`perfiles_explora` (`id`, `usuario_id` int unsigned **único** = `pedigital.users.id`, `avatar` smallint unsigned default 1, `created_at`, `updated_at`; InnoDB, utf8mb4). Sin FK (otra base de datos). Modelo `Explora\PerfilExplora` con `avatarDe(int $usuarioId)`: perfil propio → si no existe, `pedigital.users.avatar_explora` (lectura) → 1. Migración aditiva e idempotente (`2026_11_18_000002_crear_perfiles_explora.php`); `down()` solo borra la tabla si está vacía. Aplicada únicamente en `explora_test`; **su creación en producción requiere autorización** (Fase 8).
