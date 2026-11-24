{{--
    Ventana del test (#contenedorTest) de la PORTADA (mapa general): cabecera con departamento y reloj, el botón del búho
    «Comenzar Evaluación» (#boton_comenzar), la barra de carga (#loadbar), el contenedor de preguntas (#quiz) y las
    10 caritas de progreso. La abre verTest() y la maneja quiz.js (public/mapplic/js/quiz.js): los ids son su contrato.
    comenzarTest() también es de quiz.js; el efecto de vuelo del búho (clase `flying`) lo añade public/js/inicio.js.
    Los mapas temáticos usan `test-aviso` (solo un mensaje).
--}}
<div id="contenedorTest">
    <div class="content" style="background-color: #ffffff00; border: none; height: 100vh; display: flex ;justify-content: center;align-items: center;">
        <a style="position: absolute;color: wheat;border-radius: 50%;top: 2vh;right: 2vw;opacity: 1;font-size: 62px;z-index: 333333;" type="button" href="#" aria-label="Cerrar" onclick="cerrarTest(); return false;">&times;</a>
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
                    <div class="modal-body quizmodal">
                        <div id="loadbar" style="display: none;">
                            <span class="loader"></span>
                        </div>
                        <button onclick="comenzarTest()" id="boton_comenzar" class="owl-container" style="background: none; border: none; cursor: pointer;">
                            <div class="owl">
                                <div class="wing-left"></div>
                                <div class="wing-right"></div>
                                <div class="eyes">
                                    <div class="eye"></div>
                                    <div class="eye"></div>
                                </div>
                                <div class="beak"></div>
                            </div>
                            <div class="speech-bubble">
                                <i class="fa fa-play" aria-hidden="true"></i> Comenzar Evaluación
                            </div>
                        </button>
                        <div style="display: none; width: 100%;" class="quiz" id="quiz" data-toggle="buttons">
                        </div>
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
