/**
 * Teclado para elementos clicables que no son botones nativos (Fase 17F-C).
 *
 * Muchas piezas del sitio son <div>, <span> o <a> sin href con un `onclick` (selectores de nivel, tarjetas de juego,
 * cierre del chat, controles del tooltip de Mapplic...). Un <button> nativo se activa con Enter y Espacio sin ayuda,
 * pero esos elementos no. Las vistas los marcan con `role="button" tabindex="0"` (para que reciban el foco y se
 * anuncien como botón) y este script completa el comportamiento:
 *
 *   - Enter o Espacio sobre un elemento con `role="button"` dispara `.click()`, es decir, EXACTAMENTE el mismo manejador
 *     que el ratón (`onclick`, jQuery, Bootstrap...). Con ratón nada cambia.
 *   - Se ignoran los elementos que ya se comportan como botón (<button>, <a href>, <input>, <select>, <textarea>,
 *     <summary>) para no activar dos veces.
 *   - Espacio hace `preventDefault()` para que la página no se desplace; Enter también, por coherencia.
 *   - Se ignoran las teclas con Ctrl, Alt o Meta, y las repeticiones por mantener pulsado (evento `repeat`).
 *
 * Además corrige el destino del enlace «Saltar al contenido» (`.saltar-contenido`): si la página no tiene
 * `#contenido-principal`, el foco pasa al primer elemento con `role="main"`.
 *
 * Funciona por delegación en `document`: no depende de jQuery y alcanza a los elementos creados después (Mapplic crea
 * el tooltip por JavaScript). Sin dependencias; se carga una sola vez desde layouts/base.blade.php.
 */
(function () {
    'use strict';

    /** Etiquetas que ya son controles nativos: el navegador los activa solo con Enter/Espacio. */
    var NATIVOS = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'SUMMARY'];

    /**
     * Indica si el elemento es un «botón ARIA» que necesita ayuda del teclado.
     *
     * @param {Element} elemento Destino del evento de teclado.
     * @returns {boolean} true si tiene role="button" y no es un control nativo ni un enlace con href.
     */
    function necesitaAyuda(elemento) {
        if (!elemento || elemento.getAttribute('role') !== 'button') {
            return false;
        }
        if (NATIVOS.indexOf(elemento.tagName) !== -1) {
            return false;
        }
        if (elemento.tagName === 'A' && elemento.hasAttribute('href')) {
            return false;
        }
        return true;
    }

    document.addEventListener('keydown', function (evento) {
        if (evento.ctrlKey || evento.altKey || evento.metaKey) {
            return;
        }
        var esEnter = evento.key === 'Enter';
        var esEspacio = evento.key === ' ' || evento.key === 'Spacebar';
        if (!esEnter && !esEspacio) {
            return;
        }
        var elemento = evento.target;
        if (!(elemento instanceof Element) || !necesitaAyuda(elemento)) {
            return;
        }
        // Evita el desplazamiento de la página con Espacio (y el doble disparo con Enter).
        evento.preventDefault();
        if (!evento.repeat) {
            elemento.click();
        }
    });

    // Enlace «Saltar al contenido»: si el destino por id no existe, se enfoca el primer role="main".
    document.addEventListener('click', function (evento) {
        var enlace = evento.target instanceof Element ? evento.target.closest('.saltar-contenido') : null;
        if (!enlace || document.getElementById('contenido-principal')) {
            return;
        }
        var principal = document.querySelector('[role="main"]');
        if (principal) {
            evento.preventDefault();
            if (!principal.hasAttribute('tabindex')) {
                principal.setAttribute('tabindex', '-1');
            }
            principal.focus();
        }
    });
})();
