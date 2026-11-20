{{--
    Pantalla de derrota (#final), oculta por defecto. La muestra quiz.js al fallar el test y rellena #texto_final.
    «Si» recarga la página; «No, salir» la oculta. Coordilleras no la tenía (omisión del HTML original): ahora la
    llevan las cinco páginas.
--}}
<div style="display: none; text-align: center;" id="final" class="final">
    <img id="imagen_final" style="height: 55vh;" src="{{ asset('images/derrota.webp') }}" alt="">
    <br>
    <hr>
    <div class="row" style="padding: 7vh;">
        <div class="col-12" style="padding-bottom: 6vh; margin-top: 0px;">
            <h1 style="color: #5d5656; font-weight: 700; font-size: 25pt; padding-bottom: 0px;" class="borde2" id="texto_final"></h1>
            <h1 style="color: #5d5656; font-weight: 700; font-size: 25pt;" class="borde2">¿Quieres intentar nuevamente?</h1>
        </div>
        <div class="col-3"></div>
        <div class="col-3 text-center">
            <button style="width: 80%;border-radius: 20pt; font-size: 23px;" onclick="window.location.reload(1)"
                class="btn btn-success">Si</button>
        </div>
        <div class="col-3 text-center">
            <button style="width: 80%; border-radius: 20pt; font-size: 23px;" onclick="$('#final').fadeToggle(1000);" class="btn btn-danger">No,
                salir</button>
        </div>
        <div class="col-3"></div>
    </div>
</div>
