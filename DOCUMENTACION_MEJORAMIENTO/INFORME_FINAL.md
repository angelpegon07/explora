# INFORME FINAL (Fase 8 · cierre)

Fecha: 2026-11-19 · Rama `migracion-laravel` (etiqueta de recuperación `pre-migracion-laravel`) · Sin push a ningún remoto.

## 1. Qué se entrega
- **Laravel 10.50.4 en `laravel/`**: API de lectura (fichas, quiz), sesión por cookie + CSRF, perfil/avatar (`perfiles_explora`), resultados, ranking e historial. `pedigital` se usa solo en lectura.
- **Frontend conmutado** a esa API (`js/explora-api.js`); los 7 PHP legados sustituidos responden 410.
- **Fase 7B**: Font Awesome 83 MB → 3 MB, GIF 169 MB → 47 MB (WebP), MP4 30,8 → 19,0 MB, jQuery 3.5.1, Mapplic 3 → 2 variantes, build ligero `dist/`.
- **Pruebas**: PHPUnit 118 (1.136 aserciones) · Playwright 53 (Chromium) · `.htaccess` verificados con Apache 2.4.56 real.

## 2. Estado de los hallazgos (MATRIZ_HALLAZGOS.md, 44 filas de detalle)
| Estado | Hallazgos |
|---|---|
| **Cerrados** | SEC-01 (SQL), SEC-03 (IDOR), SEC-04 (hash/enumeración/límite), SEC-05 (XSS servidor y cliente), SEC-07 (errores internos), DAT-01 (cuenta inactiva), FUN-02, FUN-06, FUN-08 (flujos ya verificados), FUN-11, FUN-12, FUN-13, FUN-14, TEC-01, TEC-03, CAL-01, CAL-02, REN-01 |
| **Parciales** | SEC-09 (jQuery 3.5.1; Bootstrap 4.5.2/4.6.2 se mantienen), SEC-10 (CSRF hecho; HTTPS y cabeceras dependen del servidor real), TEC-02 (Mapplic 2 variantes, Font Awesome unificado, Bootstrap 3 versiones), TEC-05 (migración aditiva y pruebas; falta procedimiento de respaldo en producción), FUN-01/DAT-03 (la API ordena bien, pero la columna `fecha` sigue siendo texto) |
| **Abiertos por decisión** | SEC-02, SEC-08, SEC-11, FUN-03/04/05 (chat y corrector con IA: ver PENDIENTE_TRABAJO_IA.md) |
| **Aceptados** | SEC-06 (el puntaje lo calcula el navegador; no se puede garantizar su honestidad con la arquitectura actual) |
| **Requieren autorización sobre datos** | DAT-02, DAT-04, DAT-05, DAT-07 (imágenes base64: la ficha del depto 10 pesa ~46 MB), REN-02/REN-03 |
| **No revisados en esta ronda** | FUN-07, FUN-10, TEC-04, DAT-06, CAL-03, CAL-04 |

## 3. Riesgos residuales (lo que el responsable debe saber)
1. **El chat legado sigue activo** con credenciales de MySQL (usuario con privilegios altos) escritas en `chat.php` y `chat copy.php`. Rotarlas es obligatorio antes de publicar.
2. Los 3 `conexion*.php` tienen cambios locales ajenos sin confirmar.
3. Probado solo en Chromium y con Apache por CGI; faltan Firefox, Safari y mod_php/php-fpm.
4. El contenido editorial de las fichas se inserta como HTML por diseño: si esa fuente se comprometiera habría XSS.
5. Los datos antiguos con HTML siguen en la base (ahora se muestran como texto, pero no se limpiaron).
6. Servir el sitio en un subdirectorio no está soportado por el servidor.

## 4. Siguientes decisiones
- Autorizar el despliegue (ver PROPUESTA_DESPLIEGUE.md).
- Abrir el trabajo de IA (chat y corrector) como proyecto aparte.
- Decidir la migración de datos (fecha a `datetime`, FK, imágenes base64 a archivos).
