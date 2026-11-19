# ARQUITECTURA ACTUAL

## 1. Visión general

Arquitectura de **sitio estático + endpoints PHP + MySQL**, sin framework ni patrón MVC.

```
Navegador (HTML + jQuery + Mapplic + localStorage)
   │  fetch/AJAX
   ├──► mapplic/php/*.php ──► MySQL: explora (contenido) / pedigital (usuarios)
   │                      └──► Ollama localhost:11434 (chat.php)
   └──► juegos/php/*.php  ──► MySQL: explora (resultado_juegos)
Archivos estáticos: JSON/GeoJSON, SVG/WebP, imágenes, sonidos
```

## 2. Estructura de directorios

| Directorio | Función |
|---|---|
| raíz (`*.html`, `*.json`) | Páginas de mapas temáticos y su data |
| `mapplic/` | Motor de mapas (3 variantes de `mapplic*.js`), quiz, chat, login, "ver más", `php/` con API |
| `mapplic/js/` | `general.js`, `quiz.js`, `chat.js`, `iniciarSesion.js` |
| `juegos/` | Portal de juegos, `php/` con API de resultados, `juegos/<juego>/` cada juego autocontenido |
| `lineaTiempo/`, `ViajaPED/` | Módulos independientes (GSAP / React) |
| `miPerfil/` | Lógica de la página de perfil |
| `css/`, `js/`, `bootstrap/`, `fontawesome/`, `sweetalert/`, `driver/` | Recursos compartidos |
| `jsons/`, `maps*`, `images/`, `img/`, `multimedia/`, `sounds/`, `fonts/` | Datos y medios |

## 3. Componentes

- **Controladores/MVC:** no existen. Cada PHP es un script que incluye `conexion.php`, lee `$_GET/$_POST`, consulta y devuelve JSON (`echo json_encode`).
- **Modelos:** no hay; SQL directo con `mysqli->query`.
- **Servicios / repositorios / middleware / providers / eventos / jobs / colas / comandos programados:** no existen.
- **Rutas:** son las URLs de los archivos `.php`; no hay enrutador.
- **Autenticación:** `iniciarSesion.php` valida usuario/contraseña con `password_verify` y devuelve la fila; el cliente guarda datos en `localStorage`. No hay sesión PHP ni token. **Autorización:** inexistente en servidor.
- **Capa de datos duplicada:** `conexion.php` triplicado (`juegos/php`, `mapplic/php`) más `conexion_pedigital.php`.
- **Duplicación de código:** `mapplic.js`, `mapplic_generico.js`, `mapplic_vertientes.js` (~1.300 líneas c/u casi idénticas); `chat copy.php`; librerías copiadas varias veces.

## 4. Flujos principales

1. **Ficha de departamento:** clic en mapa → `consultarInfo.php?id=` → ~17 consultas secuenciales (descripción, historia, geografía, hidrografía, clima, demografía, etnografía, flora/fauna, economía, cultura, gastronomía, festividades, sitios, personajes, capital) → JSON.
2. **Quiz:** `buscarPreguntas.php?id=` → `preguntas` + `opciones_multiple` / `opciones_verfal`.
3. **Chat IA:** `chat.php?cadena=` → Ollama (`qwen2.5:7b`) corrige/interpreta consulta (departamento + categoría) → consulta tablas `departamento_*` → respuesta.
4. **Login:** `iniciarSesion.php` → `localStorage` → habilita juegos/perfil.
5. **Juegos:** al terminar → `guardar_resultado.php` (INSERT en `resultado_juegos`); ranking → `consultar_ranking.php` (top 5 por juego/nivel/categoría); historial → `consultar_historial.php?id_usuario=`.
6. **Avatar:** `cambiarAvatar.php` actualiza `users.avatar_explora` en `pedigital`.

## 5. Despliegue

No hay evidencia de configuración (sin Docker/CI/.env). Entorno asumido: servidor local PHP+MySQL (XAMPP-like) con Ollama en la misma máquina. **Por confirmar.**

## 6. Evaluación preliminar (no concluyente)

Funciona como aplicación sencilla de bajo acoplamiento, pero con duplicación alta, sin capa de seguridad en servidor y sin pruebas. Decisión del responsable (2026-11-18): migrar incrementalmente a Laravel 10 (ver INVENTARIO_SISTEMA.md §9).
