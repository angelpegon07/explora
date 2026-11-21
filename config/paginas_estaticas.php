<?php

/*
|--------------------------------------------------------------------------
| Páginas propias servidas desde una carpeta con assets (Fase 15)
|--------------------------------------------------------------------------
|
| Lista CERRADA de «slugs» de `public/<slug>/` cuyo `index.html` se migró a la vista `resources/views/paginas/<slug>.blade.php`
| (layout `layouts/base`). Se respeta la capitalización de la carpeta: la URL canónica es `/<slug>/` (CON barra final), porque
| el JS y el CSS de cada página cargan sus archivos con rutas RELATIVAS (`fetch('eventos.json')`, `data.json`, `img/…`,
| `url(img/fondo.png)`) que dependen de ella. No se usa `<base>`.
|
| UNA SOLA FUENTE DE VERDAD. Se lee desde:
|  - `routes/web.php`            (`where()` de las rutas);
|  - `servidor-desarrollo.php`   (router de desarrollo, mediante `require`: por eso es un arreglo PHP plano, SIN helpers de
|                                 Laravel como env() o base_path());
|  - `tests/Feature/PaginasEstaticasTest` (comprueba que `public/.htaccess` lista EXACTAMENTE estos slugs: las reglas de Apache
|                                 no pueden leer este archivo, así que se copian a mano y la prueba vigila la copia).
|
| Para añadir una página: crear su carpeta y su vista, añadir el slug aquí y a la regla `(lineaTiempo|ViajaPED)` de `.htaccess`.
*/

return [
    'lineaTiempo',
    'ViajaPED',
];
