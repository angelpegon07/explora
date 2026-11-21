{{--
    Visor ampliado de una imagen de la ficha del departamento (.containerImagenContenido). public/js/inicio.js lo abre al
    pulsar una <img> dentro de `.bhoechie-tab-content` (rellena #imagenModalContenido) y cerrarImagenContenido() lo cierra.
    Empieza oculto y transparente (display: none; opacity: 0) para el fundido.
--}}
<div class="containerImagenContenido" style="display: none; opacity: 0;">
    <button style="right: 1%; top: 4%;" class="close cerrar_modal" onclick="cerrarImagenContenido()"><i class="fa-solid fa-xmark"></i></button>
    <img src="" alt="" id="imagenModalContenido" style="height: 95%; width: auto;">
</div>
