<?php

/**
 * Router de `php artisan serve` para Explora (solo desarrollo).
 *
 * `artisan serve` usa este archivo en lugar de su router por defecto cuando existe en la raíz del proyecto. El router por
 * defecto devuelve `false` para cualquier ruta que coincida con una CARPETA real de `public/`, y entonces `php -S`
 * responde «Not Found» sin llegar a Laravel. En Explora eso rompe `/juegos` y `/juegos/juegos/<slug>/` (carpetas reales
 * con archivos estáticos que además las sirve una vista Blade).
 *
 * Delegamos en `servidor-desarrollo.php`, que ya conoce esas rutas, protege los archivos sensibles y aplica la guarda
 * de bases `_test`. Es el mismo router que usan Playwright y las pruebas del servidor, de modo que `php artisan serve`
 * y `php -S … servidor-desarrollo.php` se comportan igual.
 *
 * Importante: se devuelve el valor del router (`false` = que `php -S` sirva el archivo estático; `true` = ya respondió).
 */
return require __DIR__.'/servidor-desarrollo.php';
