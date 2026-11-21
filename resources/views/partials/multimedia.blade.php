{{--
    Visores a pantalla completa de video (#contenedorVideo) y de imágenes (#contenedorImagenes). Los muestran y ocultan
    verVideo/cerrarVideo y verImagenes/cerrarImagenes (public/js/mapa-comun.js). La ventana del test (#contenedorTest)
    vive en `test-aviso` (mapas temáticos) o `test-quiz` (portada).

    Fase 11: se eliminó el `</div>` sobrante que seguía a #contenedorVideo (sin apertura; el navegador lo ignoraba).
--}}
<div id="contenedorVideo">
    <div class="content" style="background-color: #ffffff00; border: none;">
        <a style="position: absolute;color: wheat;border-radius: 50%;top: 2vh;right: 2vw;opacity: 1;font-size: 62px;z-index: 333333;" type="button"  href="#" aria-label="Cerrar" onclick="cerrarVideo(); return false;">&times;</a>
        <div style="width: 100%; height: 100vh; top: 0px; left: 0px; position: absolute; display: flex; justify-content: center; align-items: center;">
            <video  controls style="width: 60%;" id="video_punto" src=""></video>
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
