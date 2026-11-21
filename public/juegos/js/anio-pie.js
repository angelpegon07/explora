/**
 * juegos/js/anio-pie.js
 * =============================================================================
 * Escribe el año actual en el pie del portal de juegos (`<span id="year">`).
 * Antes era un <script> inline en juegos/index.html; se movió a un archivo
 * estático al migrar el portal a Blade (Fase 12). No depende de ninguna librería.
 * =============================================================================
 */
(function () {
    'use strict';
    var elemento = document.getElementById('year');
    if (elemento) {
        elemento.textContent = String(new Date().getFullYear());
    }
})();
