{{--
    Modal que sugiere la pantalla completa (#myModal); se abre sola al cargar (mostrarModal() en mapa-tematico.js).

    Parámetro opcional:
      $datoInteres (bool, por defecto false)  true en climatico: ambos botones también abren/cierran el panel
                                              «Dato de interés» (#dato_interes, ver mapas/dato-interes).
--}}
@php($datoInteres = $datoInteres ?? false)
<div class="modal fade" id="myModal" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-opciones-atlas">
        <!-- Modal body -->
        <div class="modal-body">
            <h3 class="textto_con_borde">Para mejorar la experiencia de usuario se recomienda activar la pantalla completa</h3>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <button style="font-size: .89rem; width: 100%;" onclick="pantallaCompleta(){{ $datoInteres ? '; mostrarCerrarDatoInteres();' : '' }}" class="btn btn-success"><i class="fa-solid fa-expand"></i> Ver pantalla completa</button>
                </div>
                <div class="col-md-6">
                    <button @if ($datoInteres) onclick="mostrarCerrarDatoInteres();" @endif style="font-size: .89rem; width: 100%;" data-dismiss="modal" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> Seguir en modo normal</button>
                </div>
            </div>
        </div>
      </div>
    </div>
</div>
