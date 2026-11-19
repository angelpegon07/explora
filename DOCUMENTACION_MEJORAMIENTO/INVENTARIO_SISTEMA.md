# INVENTARIO DEL SISTEMA — Explora Colombia

Fase 1 (Reconocimiento). Solo lectura: no se modificó código, base de datos, dependencias ni configuración.
Fecha del análisis: 2026-11-18. Rama: `main`. Commits: `eae48c6` (Initial commit), `c29b29e` (proyecto sin dumps SQL ni node_modules).

## 1. Hallazgo principal de esta fase

**El sistema legado (raíz del repositorio) NO es una aplicación Laravel** (la carpeta `laravel/` se creó después, en la migración). En la raíz no existen `composer.json`, `artisan`, `package.json` raíz, `routes/`, `app/`, migraciones ni modelos Eloquent. Es un **sitio web estático (HTML + JavaScript + jQuery) con 12 scripts PHP "planos" (sin framework) y MySQL vía `mysqli`**. El documento de instrucciones asume Laravel; las secciones específicas de Laravel (Eloquent, Form Requests, Policies, Pint, Larastan, etc.) **no aplican** y deberán adaptarse a PHP plano. Se requiere decisión del responsable (ver preguntas pendientes).

## 2. Descripción del sistema

"Explora Colombia y aprende" es una plataforma educativa interactiva sobre geografía, cultura e historia de Colombia, con:

- Mapa interactivo de departamentos (Mapplic) con fichas de información por departamento (historia, geografía, clima, fauna/flora, gastronomía, etc.), quiz por departamento y chat de consulta asistido por IA local (Ollama).
- Mapas temáticos: climático, cordilleras, etnias, parques naturales, vertientes/hidrografía.
- Siete juegos educativos con ranking e historial por usuario.
- Línea de tiempo histórica y "ViajaPED" (épocas históricas, React).
- Inicio de sesión contra la base de datos de la plataforma **pednia** (proyecto *ambientes*; su BD se llama `pedigital`), perfil con avatar.

## 3. Tamaño y composición del repositorio

- 7.388 archivos versionados. Carpetas mayores: `juegos/` (4.772 archivos, 307 MB), `fontawesome/` (2.107, 27 MB), `maps_parques` (101), `mapplic` (93), `maps` (69), `images` (60, 16 MB).
- Por tipo: 6.201 SVG (mayoría Font Awesome y banderas), 552 PNG, 103 CSS, 101 JS, 61 JSON, 20 HTML, **12 PHP**, 12 MP3.
- Datos grandes: `parques_naturales.json` (6,2 MB).
- `.gitignore` excluye `*.sql*` y `node_modules/`. El dump `explora.sql` (~360 MB según comentario) **no está en el repositorio**: no es posible conocer el esquema real.
- Existe un archivo duplicado `mapplic/php/chat copy.php`. (Corrección de la revisión del 2026-11-18: `juegos/index.html` es un archivo normal; la «carpeta anómala» anotada inicialmente fue un error de lectura de una ruta con «ñ».)

## 4. Estado de Git (al inicio)

Rama `main`, 3 archivos con cambios sin confirmar **de otro autor/sesión** (no tocados): `juegos/php/conexion.php`, `mapplic/php/conexion.php`, `mapplic/php/conexion_pedigital.php`. No se revisó su diff para no exponer credenciales.

## 5. Pantallas (puntos de entrada)

| Archivo | Título |
|---|---|
| `index.html` | Explora Colombia y aprende (mapa de departamentos) |
| `climatico.html` | Mapa Climático de Colombia |
| `coordilleras.html` | Mapa de Coordilleras |
| `etnias.html` | Etnias de Colombia |
| `parques_naturales.html` | Parques Naturales |
| `vertientes.html` | Hidrografía de Colombia |
| `miPerfil.html` | Mi Perfil |
| `corrector.html` | Corrector de texto (usa Ollama directo desde el navegador) |
| `juegos/index.html` | Menú de juegos |
| `lineaTiempo/index.html` | Línea de tiempo histórica |
| `ViajaPED/index.html` | Épocas históricas |

## 6. Documentación existente

No hay README raíz ni manuales. Solo `juegos/juegos/laberinto/README.md` y `sopa_letras/wordfind-master/Readme.md` (de terceros). La documentación existente no permite contrastar con la implementación.

## 7. Observaciones preliminares de seguridad (no auditadas aún)

Registradas solo como alerta temprana; se validarán en la Fase 4:

- Consultas SQL con variables `$_GET/$_POST` concatenadas sin sanear en la mayoría de los PHP (solo `iniciarSesion.php` usa sentencia preparada).
- Credenciales de BD escritas en archivos PHP versionados, con usuario `root` (valores no reproducidos en este informe).
- `iniciarSesion.php` devuelve la fila completa de `users` al navegador (incluye el hash de contraseña).
- Sesión basada en `localStorage` (sin sesión ni token de servidor); endpoints sin autenticación ni autorización.
- `chat.php` y `corrector.html` llaman a Ollama en `localhost:11434` (el del navegador solo funciona en la máquina del usuario).

## 8. Información que falta (ver también resumen en el chat)

Esquema real de BD, versiones de PHP/MySQL/servidor web de producción, método de despliegue, existencia de respaldos, estado productivo vs. desarrollo, y si "Laravel" es un objetivo de migración o un error del documento de instrucciones.

## 9. Decisiones del responsable

- **2026-11-18 — Dirección:** el responsable decide **migrar el sistema a Laravel** (en lugar de mejorar el PHP plano). Esto constituye autorización de principio para el "reemplazo de arquitectura" (sección 12.3 de las instrucciones); la estrategia concreta (incremental vs. total, versión de Laravel, manejo de BD y usuarios `pedigital`) queda pendiente de definir antes del Punto de control 3.
- **2026-11-18 — Parámetros de la migración:** ~~destino **Laravel 11**~~ (sustituido, ver siguiente entrada) (PHP 8.2.31 y Composer 2.10 disponibles localmente; ~~MySQL no instalado localmente~~ MySQL 8.0.41 sí estaba instalado); estrategia **incremental** (proyecto Laravel nuevo junto al actual, migración por etapas); `pedigital` como **segunda conexión de solo lectura** para autenticación; el responsable **aportará el dump/esquema** (uso solo para leer estructura, en entorno aislado).
- **2026-11-18 — Cambio de versión destino (sustituye a «Laravel 11»):** la plataforma hermana **pednia** (proyecto *ambientes*) ya opera en **Laravel 10**; el responsable decide migrar Explora a **Laravel 10** para homogeneizar el ecosistema. Compatible con PHP 8.2.31 local (Laravel 10 exige ≥ 8.1). Las demás decisiones (incremental, `pedigital` solo lectura, dump entregado) se mantienen.

## 10. Nota de nomenclatura
La plataforma de usuarios es **pednia** (proyecto **ambientes**, Laravel 10). El nombre `pedigital` se conserva solo como identificador técnico: base de datos `pedigital`, archivo `conexion_pedigital.php` y los dumps (`pedigial.sql`). En los demás documentos, «pedigital» designa esa BD, no el nombre de la plataforma.

## 11. Registro Fase 0 (2026-11-18)
- Barrido complementario realizado (ver sección F de MATRIZ_HALLAZGOS.md; matriz = 40 hallazgos en ese momento; 41 tras la Fase 1).
- Creados: etiqueta de recuperación `pre-migracion-laravel` (sobre `main`, commit `c29b29e`) y rama `migracion-laravel`. No se confirmó ningún archivo; los 3 `conexion*.php` con cambios ajenos siguen sin confirmar.
- `composer create-project laravel/laravel:^10.0` creó el esqueleto en `laravel/` pero **Composer bloqueó la instalación de dependencias**: todas las versiones 10.x de `laravel/framework` (hasta 10.50.4) están afectadas por 8 avisos de seguridad públicos (p. ej. CVE-2026-48019 inyección CRLF en la regla `email`, PKSA-m5cs-t1y6-qpcs URL firmadas, CVE-2026-102279 XSS en la página de depuración) cuya corrección solo existe en ramas posteriores. **No se desactivó el bloqueo**; pendiente de decisión del responsable (ver chat). `laravel/` contiene solo el esqueleto, sin `vendor/`.
- **2026-11-18 — Decisión del responsable:** continuar con **Laravel 10 ignorando los avisos** (riesgo aceptado). Se ignoraron únicamente los 8 identificados en `laravel/composer.json`; instalado `laravel/framework` 10.50.4; pruebas del esqueleto en verde. Detalle y mitigaciones en [REGISTRO_CAMBIOS.md](REGISTRO_CAMBIOS.md).
- **2026-11-18 — Avatar:** el responsable decide guardarlo en una **tabla de `explora`** (no escribir en `pedigital`). Implementado como `perfiles_explora` (`usuario_id` único = `pedigital.users.id`, `avatar`); sin fila de perfil se usa el valor de solo lectura de `pedigital.users.avatar_explora`. Durante la coexistencia, `cambiarAvatar.php` (legado) sigue escribiendo en pedigital; el código Laravel dará prioridad al perfil propio, así que un cambio hecho en el legado tras crear el perfil no se verá en Laravel (se resuelve al retirar el endpoint legado).
- **2026-11-18 — Alcance de la IA:** el responsable decide **aplazar la Fase 6** completa (chat con IA y corrector de texto) para un trabajo posterior enfocado solo en IA. El chat legado se deja tal cual y se documenta como riesgo abierto (SEC-02/SEC-08). Detalle en [PENDIENTE_TRABAJO_IA.md](PENDIENTE_TRABAJO_IA.md).
