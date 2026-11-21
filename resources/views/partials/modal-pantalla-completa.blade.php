{{--
    Modal que sugiere la pantalla completa (#myModal); se abre sola al cargar (mostrarModal() en mapa-comun.js).

    Parámetros opcionales:
      $relleno (bool, por defecto false)      true en la portada: `.modal-content` lleva el relleno de 20 px que tenía su
                                              HTML original (los mapas temáticos no lo llevaban). Se emite tal cual
                                              estaba (` style=" padding: 20px;"`, con el espacio inicial) para que la
                                              compuerta de equivalencia compare byte a byte.
      $datoInteres (bool, por defecto false)  true en climatico: ambos botones también abren/cierran el panel
                                              «Dato de interés» (#dato_interes, ver mapas/dato-interes).
--}}
@php($datoInteres = $datoInteres ?? false)
@php($relleno = $relleno ?? false)
<div class="modal fade" id="myModal" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content modal-opciones-atlas"@if ($relleno) style=" padding: 20px;"@endif>
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
