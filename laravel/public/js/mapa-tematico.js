/**
 * JS propio de las páginas de mapas temáticos (climatico, coordilleras, etnias, parques_naturales, vertientes): el panel
 * «Dato de interés» (solo climatico) y la inicialización de Mapplic con las opciones temáticas.
 *
 * Lo común con la portada (acordeón, modales, visores, accesibilidad, ocultar niveles) está en `mapa-comun.js`, que se
 * carga ANTES y de donde se toma `EXPLORA_MAPA_CFG` (la configuración `window.EXPLORA_MAPA` que imprime la vista
 * `mapas/tematico.blade.php`; ver su descripción en mapa-comun.js).
 *
 * Script CLÁSICO: las funciones son globales porque el HTML las invoca con `onclick="..."`.
 */

/**
 * Panel «Dato de interés» (solo climatico): lo desliza dentro de la pantalla (right: 20%) o lo esconde
 * (right: -800px). Si la página no lo tiene (#dato_interes ausente) no hace nada.
 */
function mostrarCerrarDatoInteres() {
    var datoInteres = document.getElementById("dato_interes");
    if (!datoInteres) return;
    if (datoInteres.style.right == "20%") {
        datoInteres.style.right = "-800px";
    } else {
        datoInteres.style.right = "20%";
    }
}

// ---------------------------------------------------------------------------------------------------------------
// Mapplic. Las opciones son EXACTAMENTE las de los HTML originales (también startScale/minZoom/maxZoom, que pueden
// no tener efecto en esta versión de Mapplic: no se «arreglan» aquí). Solo cambian `source` y `variante`.
// ---------------------------------------------------------------------------------------------------------------
$(document).ready(function () {
    var opcionesMapplic = {};
    // `variante` va primero, como en el HTML original de vertientes.
    if (EXPLORA_MAPA_CFG.variante) {
        opcionesMapplic.variante = EXPLORA_MAPA_CFG.variante;
    }
    opcionesMapplic.source = EXPLORA_MAPA_CFG.json;
    Object.assign(opcionesMapplic, {
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
        minscale: 1.4,
        zoom: true,
        searchdescription: true,
        searcheverywhere: true,
        thumbholder: true,
        hovertipdesc: true,
        alphabetical: true,
        startScale: 1.4,
        minZoom: 1.4,
        maxZoom: 20,
    });
    $('#mapplic').mapplic(opcionesMapplic);

    // `.usage` y pestañas de la ficha (mapa-comun.js), tras iniciar Mapplic como en el original.
    enlazarControlesMapa();
});
