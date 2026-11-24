{{--
    Modal «SELECCIONE EL MAPA QUE DESEA EXPLORAR» (#modalOpcionesAtlas). Cada tarjeta llama a seleccionarTipoMapa(),
    definida en public/mapplic/js/general.js (compartida por la portada y los mapas): desde la Fase 11 navega directo a
    las URL de Laravel sin `.html` (`/`, `/etnias`…); las `*.html` antiguas solo redirigen (301) para marcadores viejos.
--}}
<div class="modal fade" id="modalOpcionesAtlas" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl">
      <div class="modal-content modal-opciones-atlas">
        <div class="modal-body">
            <button class="close cerrar_modal" data-dismiss="modal"><i class="fa-solid fa-xmark"></i></button>
            <h4 class="textto_con_borde" style="text-align: center; width: 100%;">SELECCIONE EL MAPA QUE DESEA EXPLORAR</h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="opcion_atlas general" role="button" tabindex="0" onclick="seleccionarTipoMapa('general')">
                        <div class="div_borroso"></div>
                        <h5 class="textto_con_borde texto_cat_mapa">MAPA GENERAL</h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="opcion_atlas hidrico" role="button" tabindex="0" onclick="seleccionarTipoMapa('hidrico')">
                        <div class="div_borroso"></div>
                        <h5 class="textto_con_borde texto_cat_mapa">MAPA HÍDRICO</h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="opcion_atlas coordilleras" role="button" tabindex="0" onclick="seleccionarTipoMapa('coordilleras')">
                        <div class="div_borroso"></div>
                        <h5 class="textto_con_borde texto_cat_mapa">SISTEMA MONTAÑOSO</h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="opcion_atlas climatico" role="button" tabindex="0" onclick="seleccionarTipoMapa('climatico')">
                        <div class="div_borroso"></div>
                        <h5 class="textto_con_borde texto_cat_mapa">MAPA CLIMÁTICO</h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="opcion_atlas parques_naturales" role="button" tabindex="0" onclick="seleccionarTipoMapa('parques_naturales')">
                        <div class="div_borroso"></div>
                        <h5 class="textto_con_borde texto_cat_mapa">PARQUES NATURALES</h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="opcion_atlas etnias" role="button" tabindex="0" onclick="seleccionarTipoMapa('etnias')">
                        <div class="div_borroso"></div>
                        <h5 class="textto_con_borde texto_cat_mapa">MAPA ÉTNICO</h5>
                    </div>
                </div>
                {{--
                    Mapa de volcanes: aún no existe (general.js muestra «Próximamente»). Se deja comentado como en el
                    original; al publicarlo, descomentar y añadir el mapa a config/mapas.php.
                <div class="col-md-3">
                    <div class="opcion_atlas volcanes" role="button" tabindex="0" onclick="seleccionarTipoMapa('volcanes')">
                        <div class="div_borroso"></div>
                        <h5 class="textto_con_borde texto_cat_mapa">VOLCANES DE COLOMBIA</h5>
                    </div>
                </div>
                --}}
            </div>
        </div>
      </div>
    </div>
</div>
