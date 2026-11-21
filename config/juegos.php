<?php

/*
|--------------------------------------------------------------------------
| Páginas de juego de Explora (Fase 13)
|--------------------------------------------------------------------------
|
| Lista CERRADA de «slugs»: cada uno es una carpeta de `public/juegos/juegos/<slug>/` cuyo `index.html` se migró a la
| vista `resources/views/juegos/juego/<slug>.blade.php` (layout `layouts/juego`). La URL canónica de cada juego es
| `/juegos/juegos/<slug>/` (CON barra final: el JS y el CSS del juego cargan sus archivos con rutas relativas a ella).
|
| UNA SOLA FUENTE DE VERDAD. Se lee desde:
|  - `routes/web.php`            (`where()` de las rutas del juego);
|  - `servidor-desarrollo.php`   (router de desarrollo, mediante `require` de este archivo: por eso es un arreglo PHP
|                                 plano, SIN helpers de Laravel como env() o base_path());
|  - `tests/Feature/JuegosPaginasTest` (comprueba que `public/.htaccess` lista EXACTAMENTE estos slugs: las reglas de
|                                 Apache no pueden leer este archivo, así que se copian a mano y la prueba vigila la copia).
|
| Para añadir un juego: crear su carpeta y su vista, añadir el slug aquí y a la regla `juegos/juegos/(…)` de `.htaccess`.
*/

return [
    'laberinto',
    'banderas-color',
    'puzzle',
    'sopa_letras',
    'juego_ahorcado',
    'arrastra',
    'completa',
];
