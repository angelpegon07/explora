{{--
    Visores a pantalla completa que abre el JS del mapa: video (#contenedorVideo), carrusel de imágenes
    (#contenedorImagenes) y ventana del test (#contenedorTest). Los muestran/ocultan verVideo/cerrarVideo,
    verImagenes/cerrarImagenes (public/js/mapa-tematico.js) y verTest/cerrarTest (quiz.js).

    Parámetro:
      $quizAnidado (bool)  true = repite un `.modal-body.quizmodal` dentro de otro, tal como estaba en cuatro de las
                           cinco páginas (ver config/mapas.php). Falso = un solo contenedor, como en climatico e index.
--}}
@php($quizAnidado = $quizAnidado ?? false)
<div id="contenedorVideo">
    <div class="content" style="background-color: #ffffff00; border: none;">
        <a style="position: absolute;color: wheat;border-radius: 50%;top: 2vh;right: 2vw;opacity: 1;font-size: 62px;z-index: 333333;" type="button"  href="#" aria-label="Cerrar" onclick="cerrarVideo(); return false;">&times;</a>
        <div style="width: 100%; height: 100vh; top: 0px; left: 0px; position: absolute; display: flex; justify-content: center; align-items: center;">
            <video  controls style="width: 60%;" id="video_punto" src=""></video>
        </div>
      </div>
    </div>
</div>

<div id="contenedorImagenes">
    <div class="content" style="background-color: #ffffff00; border: none;">
        <a style="position: absolute;color: wheat;border-radius: 50%;top: 2vh;right: 2vw;opacity: 1;font-size: 62px;z-index: 333333;" type="button"  href="#" aria-label="Cerrar" onclick="cerrarImagenes(); return false;">&times;</a>
        <div id="carouselExampleIndicators" style="display: flex;align-items: center;height: 100vh;" class="carousel slide">
            <ol class="carousel-indicators">
            </ol>
            <div class="carousel-inner">
            </div>
        </div>
        <div>
            <a class="carousel-control-prev" href="#carouselExampleIndicators" role="button" data-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="sr-only">Previous</span>
            </a>
            <a class="carousel-control-next" href="#carouselExampleIndicators" role="button" data-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="sr-only">Next</span>
            </a>
        </div>
        <br>
    </div>
</div>

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
                    @if ($quizAnidado)
                        <div class="modal-body quizmodal">
                    @endif
                    <div class="modal-body quizmodal" style="display: flex; justify-content: center; align-items: center; text-align: center;">
                        <h2>Esta función solo está disponible para el mapa general, por favor seleccione el mapa general para poder utilizar esta función</h2>
                        <div id="quiz_anuncio" class="sr-only" aria-live="polite"></div>
                    </div>
                    @if ($quizAnidado)
                        </div>
                    @endif
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
