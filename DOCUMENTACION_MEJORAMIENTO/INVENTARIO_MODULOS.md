# INVENTARIO DE MÓDULOS

Estado de revisión Fase 1 (nivel inventario; la auditoría funcional es posterior). Ningún módulo fue ejecutado ni probado. Estados según sección 16 del documento de instrucciones.

## Módulos funcionales

| ID | Módulo | Archivos clave | Funcionalidad | Usuarios / roles | Entradas → Salidas | Dependencias | Estado |
|---|---|---|---|---|---|---|---|
| M01 | Mapa de departamentos | `index.html`, `mapplic/mapplic.js`, `jsons/*.json`, `maps/*` | Mapa interactivo de 33 divisiones; selección → ficha | Visitante | clic → ficha | M02, M03 | En revisión |
| M02 | Fichas de departamento | `mapplic/verMas.js`, `php/consultarInfo.php` | Historia, geografía, hidrografía, clima, demografía, etnografía, flora/fauna, economía, cultura, gastronomía, festividades, sitios, personajes, capital | Visitante | id → JSON | BD `explora` | En revisión |
| M03 | Quiz por departamento | `mapplic/js/quiz.js`, `php/buscarPreguntas.php`, `quiz.css` | Preguntas múltiples y verdadero/falso | Visitante/estudiante | id → preguntas | BD `explora` | En revisión |
| M04 | Chat asistente IA | `mapplic/js/chat.js`, `php/chat.php`, `css/chat.css` | Consulta en lenguaje natural sobre departamentos y categorías | Visitante | texto → respuesta | Ollama, BD | En revisión |
| M05 | Autenticación | `mapplic/js/iniciarSesion.js`, `php/iniciarSesion.php` | Login contra `pedigital.users` | Estudiante | usuario/contraseña → datos en localStorage | BD `pedigital` | En revisión |
| M06 | Mi Perfil / avatar | `miPerfil.html`, `miPerfil/*`, `php/cambiarAvatar.php` | Ver perfil, historial, cambiar avatar | Usuario autenticado | avatar → UPDATE | M05, M13 | En revisión |
| M07 | Mapa climático | `climatico.html/.json`, `maps_climatico` | Mapa temático | Visitante | — | `mapplic_generico.js` | En revisión |
| M08 | Mapa de cordilleras | `coordilleras.html/.json`, `maps_coordilleras` | Mapa temático | Visitante | — | idem | En revisión |
| M09 | Etnias | `etnias.html/.json`, `maps_etnias` | Mapa temático | Visitante | — | idem | En revisión |
| M10 | Parques naturales | `parques_naturales.html/.json` (6,2 MB), `maps_parques` | Mapa temático | Visitante | — | idem | En revisión |
| M11 | Hidrografía / vertientes | `vertientes.html/.json`, `maps_vertientes`, `mapplic_vertientes.js` | Mapa temático | Visitante | — | variante Mapplic propia | En revisión |
| M12 | Tutorial guiado | `driver/tutorial.js` | Recorrido con driver.js | Visitante | — | driver.js | En revisión |
| M13 | Portal de juegos + ranking/historial | `juegos/index.html`, `juegos/js/*`, `juegos/php/*` | Menú, guardar resultado, top 5, historial | Usuario autenticado | resultado → BD | M05 | En revisión |
| M14 | Juego Laberinto | `juegos/juegos/laberinto` | Juego | Estudiante | — | M13 | En revisión |
| M15 | Juego Banderas | `juegos/juegos/banderas-color` (2.217 archivos) | Juego | Estudiante | — | M13 | En revisión |
| M16 | Juego Puzzle | `juegos/juegos/puzzle` | Rompecabezas por categoría (etnias, fauna/flora, geografía) | Estudiante | — | M13 | En revisión |
| M17 | Juego Sopa de letras | `juegos/juegos/sopa_letras` | Juego | Estudiante | — | M13 | En revisión |
| M18 | Juego Ahorcado | `juegos/juegos/juego_ahorcado` | Juego | Estudiante | — | M13 | En revisión |
| M19 | Juego Arrastra | `juegos/juegos/arrastra` | Arrastrar y soltar (no enlazado desde el menú; verificar) | Estudiante | — | M13 | Pendiente de información |
| M20 | Juego Completa | `juegos/juegos/completa` | Completar (no enlazado desde el menú; verificar) | Estudiante | — | M13 | Pendiente de información |
| M21 | Juego Mapas | `juegos/juegos/mapas` (solo imágenes gris/marrón) | Posible recurso de juego sin `index` propio | — | — | — | Pendiente de información |
| M22 | Línea de tiempo | `lineaTiempo/*` | Eventos históricos animados (GSAP) | Visitante | `eventos.json` | — | En revisión |
| M23 | ViajaPED (épocas) | `ViajaPED/*` | Épocas históricas (React) | Visitante | `data.json` | React | En revisión |
| M24 | Corrector de texto | `corrector.html` | Corrección con IA (Ollama desde el navegador) | Visitante | texto → texto | Ollama | En revisión |

## Elementos redundantes / sospechosos de obsolescencia (por confirmar, no eliminar)

- `mapplic/php/chat copy.php` (534 líneas; ~dos veces `chat.php`).
- `mapplic_generico.js` y `mapplic_vertientes.js` vs `mapplic.js`.
- `js/smoothscroll.js` (no referenciado en las páginas inspeccionadas), `mapplic/mapplic-ie.css`, `js/html5shiv.js`.
- Fuentes/Font Awesome en 3–4 copias.

## Roles y permisos

No existe modelo de roles. Solo se distingue "con sesión" (existencia de `localStorage`) y sin ella. Por confirmar si `users` tiene columna de rol.

## Base de datos (inferida solo del código; esquema real NO disponible)

**BD `explora`:** `list_departamentos`, `departamento_descripcion`, `departamento_historia`, `departamento_geografia`, `departamento_hidrografia`, `departamento_clima`, `departamento_demografia`, `departamento_etnografia`, `departamento_etnografia_list`, `departamento_fauna_flora`, `departamento_flora_list`, `departamento_fauna_list`, `departamento_economia`, `departamento_cultura`, `departamento_gastronomia`, `departamento_festividad_list`, `departamento_sitios_list`, `departamento_personaje_list`, `departamento_capital`, `preguntas`, `opciones_multiple`, `opciones_verfal` (sic), `resultado_juegos` (columnas: nombre_juego, jugador, grado, avatar, puntaje, tiempo, nivel, categoria, id_usuario, fecha).

**BD `pedigital`:** `users` (columnas usadas: `id`, `login_usuario`, `pasword_usuario` (sic), `avatar_explora`; devuelve además otras no listables).

Pendiente: tipos, claves, índices, restricciones, migraciones/seeders (inexistentes en el repo).

## Cobertura del inventario

24 módulos identificados; 21 en "En revisión" (inventariados, sin auditar) y 3 "Pendiente de información" (M19–M21). Ninguno "Revisado" aún.
