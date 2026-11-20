<?php

/*
|--------------------------------------------------------------------------
| Servicios PHP legados RETIRADOS (Fase 15)
|--------------------------------------------------------------------------
|
| Rutas (relativas a la raíz del sitio, sin barra inicial) de los 7 scripts PHP del sitio original que la API Laravel
| sustituyó y cuyos archivos se eliminaron del repositorio (siguen en la etiqueta git `pre-retiro-legado`). Los
| clientes antiguos, marcadores o cachés que aún los pidan reciben `410 Gone` con un JSON fijo (ver `routes/web.php`),
| no el 404 genérico, y ya no existe nada que ejecutar: no hay PHP legado detrás de estas URL.
|
| UNA SOLA FUENTE DE VERDAD. Se lee desde:
|  - `routes/web.php`           (rutas 410, sensibles a la forma exacta del nombre pero NO a mayúsculas, como el
|                                `RewriteRule … [G,NC]` que había en los `.htaccess` ya eliminados);
|  - `servidor-desarrollo.php`  (router de desarrollo, mediante `require`: por eso es un arreglo PHP plano, SIN helpers
|                                de Laravel como env() o base_path());
|  - `tests/Feature/ServiciosRetiradosTest` (comprueba las 7 rutas con todos los métodos HTTP).
|
| `mapplic/php/chat.php` y `chat copy.php` NO están aquí a propósito: el chat con IA queda fuera de esta fase (decisión
| del responsable) y sigue siendo el archivo existente. Bajo Apache lo sirve PHP; en el router de desarrollo da 404.
*/

return [
    'mapplic/php/iniciarSesion.php',
    'mapplic/php/cambiarAvatar.php',
    'mapplic/php/consultarInfo.php',
    'mapplic/php/buscarPreguntas.php',
    'juegos/php/guardar_resultado.php',
    'juegos/php/consultar_ranking.php',
    'juegos/php/consultar_historial.php',
];
