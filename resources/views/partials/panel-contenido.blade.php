{{-- Panel lateral «CONTENIDO» (#opciones_mapa): tres acordeones que Mapplic rellena con los lugares. El JS del acordeón está en public/js/mapa-comun.js. --}}
<div class="options scroll-container" id="opciones_mapa">
    <i onclick="cerrarOpciones()" style="cursor: pointer; color: #c94141; font-size: 27px; position: absolute; right: 4px; top: 24px;" class="fa-solid fa-circle-xmark"></i>
    <br>
    <h4 style="color: grey;">CONTENIDO</h4>
    <hr>
    <button style="background-color: #c0ffcf !important;" class="accordion">Sitios de interes</button>
    <div class="panel">
        <ul style="padding-left: 7px;" id="sitios_interes_panel"></ul>
    </div>

    <button style="background-color: #bbd7f5 !important;" class="accordion">Festividades</button>
    <div class="panel">
        <ul style="padding-left: 7px;" id="festividades_panel"></ul>
    </div>

    <button style="background-color: #f5e1bb !important;" class="accordion">Etnias</button>
    <div class="panel">
        <ul style="padding-left: 7px;" id="etnias_panel"></ul>
    </div>
    <br>
</div>
