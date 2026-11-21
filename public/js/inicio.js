/**
 * JS propio de la PORTADA (`/`, vista `inicio`): el mapa general de departamentos.
 *
 * Lo común con los mapas temáticos (acordeón, modales, visores, accesibilidad, ocultar niveles) está en `mapa-comun.js`,
 * que se carga ANTES y de donde se toma `EXPLORA_MAPA_CFG` (configuración `window.EXPLORA_MAPA` impresa por la vista
 * `inicio.blade.php`; `json` es la URL absoluta de departamentos.json).
 *
 * Contiene: inicialización de Mapplic (versión `mapplic/mapplic.js`, no la genérica), el efecto de vuelo del búho
 * del test y el visor ampliado de las imágenes de la ficha.
 *
 * Script CLÁSICO: las funciones son globales porque el HTML las invoca con `onclick="..."`.
 */

// ---------------------------------------------------------------------------------------------------------------
// Mapplic. Las opciones son EXACTAMENTE las del index.html original (nótese `startscale` en minúscula y minscale 0.5,
// distintos de los mapas temáticos; no se «arreglan» aquí). Solo `source` pasa de relativa a absoluta.
// ---------------------------------------------------------------------------------------------------------------
$(document).ready(function () {
    $('#mapplic').mapplic({
        source: EXPLORA_MAPA_CFG.json,
        mapfill: true,
        height: 560,
        animate: true,
        sidebar: true,
        search: true,
        minimap: false,
        locations: true,
        deeplinking: true,
        fullscreen: true,
        hovertip: true,
        developer: true,
        maxscale: 20,
        minscale: 0.5,
        zoom: true,
        searchdescription: true,
        searcheverywhere: true,
        thumbholder: true,
        hovertipdesc: true,
        alphabetical: true,
        startscale: 0.9
    });

    // `.usage` y pestañas de la ficha (mapa-comun.js), tras iniciar Mapplic como en el original.
    enlazarControlesMapa();
});

// ---------------------------------------------------------------------------------------------------------------
// Búho del test: al pulsar «Comenzar Evaluación» vuela 2 s (clase `flying`, animada en css/utilidad.css).
// ---------------------------------------------------------------------------------------------------------------
document.getElementById('boton_comenzar').addEventListener('click', function () {
    const owl = this.querySelector('.owl');
    owl.classList.add('flying');

    setTimeout(() => {
        owl.classList.remove('flying');
    }, 2000);
});

// ---------------------------------------------------------------------------------------------------------------
// Visor ampliado de las imágenes de la ficha (.containerImagenContenido, parcial `lightbox-imagen`)
// ---------------------------------------------------------------------------------------------------------------
document.addEventListener("DOMContentLoaded", function () {
    // Selecciona todas las imágenes dentro de .bhoechie-tab-content
    const containers = document.querySelectorAll('.bhoechie-tab-content');

    containers.forEach(container => {
        container.addEventListener('click', function (e) {
            if (e.target.tagName === 'IMG') {
                // Acción al hacer clic en una imagen: se muestra ampliada con un fundido.
                $('.containerImagenContenido').css('display', 'flex');
                $('#imagenModalContenido').attr('src', e.target.src);
                setTimeout(() => {
                    $('.containerImagenContenido').css('opacity', '1');
                }, 100)
            }
        });
    });
});

/** Cierra el visor ampliado con un fundido de 0,6 s. */
function cerrarImagenContenido() {
    $('.containerImagenContenido').css('opacity', '0');
    setTimeout(() => {
        $('.containerImagenContenido').css('display', 'none');
    }, 600)
}
