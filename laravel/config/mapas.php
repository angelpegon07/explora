<?php

/*
|--------------------------------------------------------------------------
| Mapas temáticos de Explora (Fase 10)
|--------------------------------------------------------------------------
|
| Un arreglo por mapa; la CLAVE es el «slug» de la URL (`/climatico`, `/etnias`…). Las cinco páginas comparten
| la vista `mapas/tematico.blade.php` y solo difieren en los datos de este archivo (ver README, «Cómo añadir un mapa»).
| `routes/web.php` crea las rutas a partir de estas claves, de modo que añadir un mapa es añadir una entrada aquí.
|
| Claves de cada mapa:
|  - titulo:            texto de <title>. Se imprime siempre escapado con {{ }}.
|  - json:              archivo de datos de Mapplic (en public/). Con el SVG y las imágenes viven en public/maps_*.
|  - carpeta_imagenes:  carpeta (relativa a public/) de las imágenes del carrusel. Si contiene «{carpeta}», el JS lo
|                       sustituye por el atributo `carpeta` del lugar (etnias y parques: una subcarpeta por lugar).
|  - tutorial:          argumento de `iniciarTour()` (driver/tutorial.js). Los valores son los que usaba cada página.
|  - variante:          valor de la opción `variante` de Mapplic (solo vertientes: 'vertientes'); null = no se envía.
|  - dato_interes:      true solo en climatico: panel #dato_interes, su CSS y los onclick que lo abren.
|  - ocultar_niveles:   true solo en vertientes: a los 800 ms oculta el selector de niveles (.mapplic-levels).
|  - quiz_anidado:      true en las páginas que repiten un `.modal-body.quizmodal` dentro de otro (herencia del HTML
|                       original, conservada para no cambiar el aspecto; climatico e index tienen uno solo).
|  - css:               estilos propios de la página (ver mapas/estilos):
|        trazo               grosor del contorno al pasar el ratón / zona activa.
|        regla_activa        false = no emitir la regla `.mapplic-active` (vertientes no la tenía).
|        paneles             [barra lateral %, mapa %] del primer bloque de anchos.
|        paneles_ajuste      segundo bloque que sobrescribe al primero (30/70), o null si la página no lo tenía.
|        tooltip_ancho       max-width del globo informativo.
|        tooltip_descripcion true = estilos del contenido del globo (etnias y parques naturales).
*/

return [
    'climatico' => [
        'titulo' => 'Mapa Climático de Colombia',
        'json' => 'climatico.json',
        'carpeta_imagenes' => 'multimedia/imagenes/',
        'tutorial' => 'climatico',
        'variante' => null,
        'dato_interes' => true,
        'ocultar_niveles' => false,
        'quiz_anidado' => false,
        'css' => [
            'trazo' => '15px',
            'regla_activa' => true,
            'paneles' => [20, 80],
            'paneles_ajuste' => [30, 70],
            'tooltip_ancho' => '550px',
            'tooltip_descripcion' => false,
        ],
    ],

    'coordilleras' => [
        'titulo' => 'Mapa de Cordilleras de Colombia',
        'json' => 'coordilleras.json',
        'carpeta_imagenes' => 'multimedia/imagenes/',
        'tutorial' => 'sistema montañoso',
        'variante' => null,
        'dato_interes' => false,
        'ocultar_niveles' => false,
        'quiz_anidado' => true,
        'css' => [
            'trazo' => '9px',
            'regla_activa' => true,
            'paneles' => [20, 80],
            'paneles_ajuste' => [30, 70],
            'tooltip_ancho' => '550px',
            'tooltip_descripcion' => false,
        ],
    ],

    'etnias' => [
        'titulo' => 'Etnias de Colombia',
        'json' => 'etnias.json',
        'carpeta_imagenes' => 'maps_parques/imagenes_parques/{carpeta}/',
        'tutorial' => 'etnias',
        'variante' => null,
        'dato_interes' => false,
        'ocultar_niveles' => false,
        'quiz_anidado' => true,
        'css' => [
            'trazo' => '9px',
            'regla_activa' => true,
            'paneles' => [20, 80],
            'paneles_ajuste' => [30, 70],
            'tooltip_ancho' => '600px',
            'tooltip_descripcion' => true,
        ],
    ],

    'parques_naturales' => [
        'titulo' => 'Parques Naturales de Colombia',
        'json' => 'parques_naturales.json',
        'carpeta_imagenes' => 'maps_parques/imagenes_parques/{carpeta}/',
        'tutorial' => 'parques_naturales',
        'variante' => null,
        'dato_interes' => false,
        'ocultar_niveles' => false,
        'quiz_anidado' => true,
        'css' => [
            'trazo' => '9px',
            'regla_activa' => true,
            'paneles' => [20, 80],
            'paneles_ajuste' => [30, 70],
            'tooltip_ancho' => '600px',
            'tooltip_descripcion' => true,
        ],
    ],

    'vertientes' => [
        'titulo' => 'Hidrografía de Colombia',
        'json' => 'vertientes.json',
        'carpeta_imagenes' => 'multimedia/imagenes/',
        'tutorial' => 'hidrografia',
        'variante' => 'vertientes',
        'dato_interes' => false,
        'ocultar_niveles' => true,
        'quiz_anidado' => true,
        'css' => [
            'trazo' => '27.5px',
            'regla_activa' => false,
            'paneles' => [20, 80],
            'paneles_ajuste' => null,
            'tooltip_ancho' => '550px',
            'tooltip_descripcion' => false,
        ],
    ],
];
