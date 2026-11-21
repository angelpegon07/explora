{{--
    Panel «Dato de interés» (#dato_interes), solo del mapa climático. Texto estático (las entidades HTML se conservan
    tal cual estaban). Empieza fuera de pantalla (right: -600px) y mostrarCerrarDatoInteres() lo desliza; ver
    public/js/mapa-tematico.js. El GIF «pensando» usa asset() como el resto de recursos.
--}}
<div id="dato_interes" class="dato_interes">
    <img src="{{ asset('img/pensando.webp') }}" width="200px" id="pensando_gif" alt="">
    <div class="dato_interes_header">
        <h2>Dato de interes</h2>
        <i onclick="mostrarCerrarDatoInteres()" class="btn_cerrar_dato_interes fa-solid fa-circle-xmark"></i>
    </div>
    <div class="dato_interes_body">
        <p>El mapa clim&aacute;tico de Colombia se divide en varias regiones seg&uacute;n su clima predominante. Cada regi&oacute;n incluye diferentes departamentos del pa&iacute;s.</p>
        <p>Las regiones clim&aacute;ticas son:</p>
        <ul style="margin-bottom: 0px !important;">
            <li><strong>Caribe</strong> (zona &aacute;rida y semi&aacute;rida).</li>
            <li><strong>Andina </strong>(templado y de monta&ntilde;a).</li>
            <li><strong>Pac&iacute;fica</strong> (h&uacute;medo y lluvioso).</li>
            <li><strong>Orinoqu&iacute;a </strong>(sabana y clima tropical).</li>
            <li><strong>Amazon&iacute;a </strong>(selva h&uacute;meda y tropical).</li>
            <li><strong>Insular </strong>(clima mar&iacute;timo y tropical).</li>
        </ul>
    </div>
</div>
