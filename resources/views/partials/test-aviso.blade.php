{{--
    Ventana del test (#contenedorTest) en los MAPAS TEMÁTICOS: solo avisa de que el test está disponible únicamente en el
    mapa general. La abre verTest() y la cierra cerrarTest() (public/mapplic/js/quiz.js). La portada usa `test-quiz`.

    Fase 11: el `.modal-body.quizmodal` ya no se repite anidado (antes lo hacían cuatro de los cinco mapas). Medido con
    Playwright en etnias, el único cambio visible es que el aviso gana 16 px de margen lateral (ver REGISTRO_CAMBIOS.md).
--}}
<div id="contenedorTest">
    <div class="content" style="background-color: #ffffff00; border: none; height: 100vh; display: flex ;justify-content: center;align-items: center;">
        <a style="position: absolute;color: wheat;border-radius: 50%;top: 2vh;right: 2vw;opacity: 1;font-size: 62px;z-index: 333333;" type="button"  href="#" aria-label="Cerrar" onclick="cerrarTest(); return false;">&times;</a>
        <br>
        <div class="container-fluid">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                    <div class="modal-header" style="align-items: center; justify-content: space-between; padding: .5rem .5rem;">
                        <div class="alert alert-primary" style="margin: 0px;" role="alert">
                            <strong>Departamento: </strong><span id="departamento_nombre"></span>
                          </div>
                        <div class="tiempo_restante">
                            Tiempo restante
                            <span id="reloj">
                                <i style="line-height: inherit; margin-top: 3px;" class="fa fa-clock" aria-hidden="true"></i>
                                <p style="margin: 0px; padding-left: 10px;" id="contador">00:00</p>
                            </span>
                        </div>
                    </div>
                    <div class="modal-body quizmodal" style="display: flex; justify-content: center; align-items: center; text-align: center;">
                        <h2>Esta función solo está disponible para el mapa general, por favor seleccione el mapa general para poder utilizar esta función</h2>
                        <div id="quiz_anuncio" class="sr-only" aria-live="polite"></div>
                    </div>
                    <div class="modal-footer text-muted" style="justify-content: space-between;">
                        <p style="color: rgb(62, 113, 255); font-weight: bold;" id="contador_preguntas">Pregunta 0 de 10</p>
                        <div style="font-size: 30px;">
                            @for ($i = 0; $i < 10; $i++)
                                <i id="carita{{ $i }}" class="fa facaritas fa-smile" aria-hidden="true"></i>
                            @endfor
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
