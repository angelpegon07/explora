{{--
    Menú de accesibilidad (#menuAccesibilidad): tamaño de texto, modo dislexia y tutorial.

    Parámetro:
      $tutorial (string)  argumento de iniciarTour() (driver/tutorial.js): cada mapa tiene su propio recorrido.
                          Se imprime dentro de un atributo HTML Y de una cadena JS, por eso se pasa por Js::from(), que
                          lo convierte en un literal JS seguro (comillas y «<>&'"» en \uXXXX) antes de que {{ }} lo
                          emita dentro del atributo. El valor sale de config/mapas.php, nunca de la petición.
--}}
<div id="menuAccesibilidad" style="z-index: 9999999;" class="menu-accesibilidad">
    <button class="btn-accesibilidad" id="btn_menu_accesibilidad" onclick="toggleMenuAccesibilidad()">
        <i class="fa fa-universal-access"></i>
    </button>
    <div class="contenido-accesibilidad">
        <h4>Accesibilidad</h4>
        <div class="opciones-accesibilidad">
            <div class="row" style="margin: 0px;">
                <div class="col-6" style="padding-left: 10px; padding-right: 10px;">
                    <button onclick="aumentarFuente()" id="btn_aumentar_fuente" class="btn-opcion">
                        <i class="fa fa-text-height"></i> Aumentar texto
                    </button>
                </div>
                <div class="col-6" style="padding-left: 10px; padding-right: 10px;">
                    <button onclick="disminuirFuente()" id="btn_disminuir_fuente" class="btn-opcion">
                        <i class="fa fa-text-height"></i> Disminuir texto
                    </button>
                </div>
            </div>
            <div class="row" style="margin: 0px;">
                <div class="col-6" style="padding-left: 10px; padding-right: 10px;">
                    <button onclick="toggleDislexia()" id="btn_modo_dislexia" class="btn-opcion">
                        <i class="fa fa-book"></i> Modo dislexia
                    </button>
                </div>
                <div class="col-6" style="padding-left: 10px; padding-right: 10px;">
                    <button onclick="iniciarTour({{ Illuminate\Support\Js::from($tutorial) }})" id="btn_tutorial" class="btn-opcion">
                        <i class="fa fa-question-circle"></i> Tutorial de uso
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
