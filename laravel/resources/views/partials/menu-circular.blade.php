{{--
    Menú circular flotante (#circularMenu). Los enlaces a otras secciones usan asset() para que funcionen desde
    cualquier ruta. El botón «+» alterna la clase `active` (animación en mapplic/menu.css).
--}}
<div id="circularMenu" class="circular-menu">
    <a class="floating-btn custom-button button-orange" id="btn_menu_circular" onclick="document.getElementById('circularMenu').classList.toggle('active');">
        <i class="fa fa-plus"></i>
    </a>

    <menu class="items-wrapper">
        <a id="btn_menu_opciones_atlas" href="#" class="menu-item itemm1" data-toggle="modal" data-target="#modalOpcionesAtlas">
        </a>
        <a id="btn_menu_juegos" href="{{ asset('juegos/index.html') }}" target="_blank" class="menu-item itemm2">
        </a>
        <a id="btn_menu_test" href="#" class="menu-item itemm3" type="button" onclick="verTest()">
        </a>
        <a id="btn_menu_chat" href="#" class="menu-item itemm4" onclick="mostrarChat()">
        </a>
        <a id="btn_menu_linea_tiempo" href="{{ asset('lineaTiempo/index.html') }}" target="_blank" class="menu-item itemm5">
        </a>
        <a id="btn_menu_viaja_ped" href="{{ asset('ViajaPED/index.html') }}" target="_blank" class="menu-item itemm6">
        </a>
    </menu>
</div>
