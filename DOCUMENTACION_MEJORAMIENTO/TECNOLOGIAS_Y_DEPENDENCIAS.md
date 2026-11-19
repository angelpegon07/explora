# TECNOLOGÍAS Y DEPENDENCIAS

Fuente: inspección de cabeceras de archivos y código. No hay gestores de dependencias en la raíz (sin `composer.json`/`package.json`); las librerías están **copiadas manualmente** en el repositorio.

## 1. Backend

| Elemento | Detalle | Evidencia |
|---|---|---|
| Lenguaje | PHP plano (versión no determinable; usa `??`, por lo que ≥ 7.0) | `mapplic/php/chat.php` |
| Framework | Ninguno en el legado de la raíz; **Laravel 10.50.4** en `laravel/` (migración en curso) | ausencia de `composer.json`/`artisan` en la raíz; `laravel/composer.json` |
| Acceso a BD | `mysqli` (procedural/OO) | `conexion*.php` |
| Motor BD | MySQL 8.0.41 (según los dumps y la instalación local; producción por confirmar) | uso de `mysqli`, charset `utf8` |
| Bases de datos | `explora` (contenido, juegos) y `pedigital` (usuarios) | `conexion.php`, `conexion_pedigital.php` |
| Contraseñas | `password_verify` (hash bcrypt/PHP) | `iniciarSesion.php` |
| IA | Ollama local, modelo `qwen2.5:7b`, vía cURL a `http://localhost:11434` | `chat.php` |
| Servidor web | No determinable (probable XAMPP/Apache por `root`@`localhost`) | — |

## 2. Frontend (librerías incluidas en el repo)

| Librería | Versión | Ubicación |
|---|---|---|
| jQuery | 3.5.1 en raíz, perfil y juegos/index (antes 1.11.3, Fase 7B); 3.3.1/3.7.1 en juegos | `bootstrap/jquery.min.js`, `juegos/juegos/assets/bootstrap/` |
| Bootstrap | 4.5.2 (css/js) | `bootstrap/` |
| Bootstrap | 5.3 | `juegos/librerias/bootstrap-5.3`, `juegos/juegos/*/bootstrap` |
| Font Awesome Free | 6.2.1 (raíz) y 6.7.2 (juegos, compartida con banderas-color); solo `css/` y `webfonts/` desde la Fase 7B | `fontawesome/`, `juegos/librerias/fontawesome` |
| Mapplic (mapa interactivo) | plugin comercial, 2 variantes modificadas (vertientes fusionada en generico, Fase 7B) | `mapplic/mapplic.js`, `mapplic_generico.js` |
| SweetAlert2 | 11.17.2 | `sweetalert/` |
| driver.js (tutorial guiado) | no identificada | `driver/` |
| Hammer.js, jquery.easing, jquery.mousewheel, html5shiv, smoothscroll | varias | `js/` |
| GSAP + ScrollTrigger | min (versión no leída) | `lineaTiempo/` |
| AOS | no identificada | `juegos/librerias/aos` |
| jQuery UI, popper, panzoom, tag-it, wordfind | varias | carpetas de juegos |
| React 19.1 / react-dom 19.1 / @babel/preset-react | `^19.1.0`, `^7.27.1` | `ViajaPED/package.json` (único gestor npm; `node_modules` ignorado) |

## 3. Datos estáticos

- GeoJSON/JSON por departamento: `jsons/*.json` (33 archivos + `colombia.json`), `departamentos.json`.
- Mapas temáticos: `climatico.json`, `coordilleras.json`, `etnias.json`, `vertientes.json`, `parques_naturales.json` (6,2 MB).
- SVG/WebP de mapas: `maps/`, `maps_climatico`, `maps_coordilleras`, `maps_etnias`, `maps_parques`, `maps_vertientes`.
- Datos de juegos: `datos.json`, `pistas.json`, `categorias.json`, `completa.json`, `json_banderas*.json`, `colores.json`, `arrastra/data.js`.
- `lineaTiempo/eventos.json`, `ViajaPED/data.json`.

## 4. Herramientas de calidad / build / despliegue

No se encontraron: PHPUnit, Pest, Pint, PHPStan, CI/CD, Docker, `.env`, archivos de configuración de servidor, scripts de build ni migraciones. `ViajaPED` usa React (probablemente cargado vía CDN/Babel en navegador; no verificado).

## 5. Integraciones externas

- Ollama (IA local) desde `chat.php` (servidor) y desde `corrector.html` (navegador).
- Base de datos de la plataforma pednia (proyecto ambientes; BD `pedigital`) (tabla `users`).
- No se verificaron CDN externos en cada HTML (pendiente).

## 6. Almacenamiento en cliente

`localStorage` (estado del inventario del 2026-11-18, **histórico**): `usuario`, `id_usuario`, `nombre`, `grado`, `avatar`, `tipo_modal`. Desde la Fase 7 `tipo_modal` ya no existe y el resto es solo una caché de compatibilidad.

## 7. Dependencias vulnerables / obsoletas (indicio, sin auditar)

jQuery 1.11.3 y Bootstrap 4.5.2 tienen vulnerabilidades públicas conocidas; se confirmarán en la Fase 4. No se ejecutó ninguna herramienta de auditoría ni se actualizó nada.
