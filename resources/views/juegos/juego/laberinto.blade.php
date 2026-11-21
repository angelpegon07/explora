{{--
    Juego «laberinto» (Fase 13): migración de public/juegos/juegos/laberinto/index.html (se eliminó) a Blade.
    Laberinto Espacial (nombre enviado a /api/resultados: «laberinto»). Datos en js/preguntas.json (fetch relativo). Estilo propio en estilo-pagina.css (antes <style> inline). El original traía un segundo <title> duplicado: se omite (el layout ya emite uno).

    URL canónica: /juegos/juegos/laberinto/ (CON barra final). Casi todo lo que carga el juego en ejecución (JSON, sonidos, imágenes
    por JS, url() de CSS) es RELATIVO a la URL de la página, por eso JuegoController redirige la forma sin barra.
    Los recursos del HTML pasan por asset() (URL absolutas). El orden de CSS y JS es el del original (cascada y
    dependencias: jQuery antes de explora-api.js y este antes de terminar_juego.js). Sin datos del servidor y sin salida sin escapar.
    El <style> inline se extrajo a estilo-pagina.css, enlazado en la MISMA posición del <head> (misma cascada).
--}}
@extends('layouts.juego')

@section('titulo', 'Laberinto Espacial')
@section('viewport', 'width=device-width, initial-scale=1.0')
@section('atributos_body')style="overflow: hidden;"@endsection

@push('cabecera')
        <link rel="stylesheet" href="{{ asset('juegos/juegos/laberinto/css/css2.css') }}">
        <link rel="stylesheet" href="{{ asset('juegos/librerias/bootstrap-5.3/css/bootstrap.min.css') }}">
        <script src="{{ asset('juegos/librerias/bootstrap-5.3/js/bootstrap.bundle.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset('juegos/juegos/assets/bootstrap/jquery.min.js') }}"></script>
        @include('juegos.partials.explora-api')
        @include('juegos.partials.pantalla-css')
        <link rel="stylesheet" href="{{ asset('juegos/librerias/fontawesome/css/all.min.css') }}">
        @include('juegos.partials.terminar-css')
        <link rel="stylesheet" href="{{ asset('juegos/juegos/laberinto/estilo-pagina.css') }}">
@endpush

@section('juego')
<div id="contenedor_general" class="contenedor_general">
    <div class="game-container" id="game-container" style="position: relative;">
        <table id="mapa"></table>
        <img id="player" class="player" style="transform: rotate(45deg);" src="{{ asset('juegos/juegos/laberinto/css/mario.gif') }}">
    </div>
    <div style="display: flex; justify-content: space-around; align-items: center; height: 83vh; flex-direction: column;  width: 240px;">
        <div id="timer">
            <h1 id="timer_text" class="borde_letra">5:00</h1>
            <h5 style="color: #ffffff; font-size: 20px; font-family: 'DS-Digital'; font-weight: bold; width: 100%; text-align: center;">Marcador</h5>
            <div style="display: flex; justify-content: center; align-items: center; height: 50px; width: 100%;">
                <div id="marcador1">0</div>
                <div id="marcador2">0</div>
            </div>
        </div>
    <div class="controles">
            <div style="top: -6px; left: -6px;position: relative; transform: rotate(-45deg); width: 178px; height: 178px;">
                <div class="subir" onclick="mover_nave('ArrowUp')">
                </div>
                <div class="bajar" onclick="mover_nave('ArrowDown')">
                </div>
                <div class="izquierda" onclick="mover_nave('ArrowLeft')">
                </div>
                <div class="derecha" onclick="mover_nave('ArrowRight')">
                </div>
            </div>
        </div>  
        <br>
        <div class="controles2">
            <div onclick="playPause()" id="play_pause" class="play">
            </div>
            <div class="replay" onclick="replay()">
            </div>
        </div>
    </div>    
</div>


<div class="pregunta" id="pregunta">
    <div class="contenedor_pregunta" style="width: 70%; position: relative; z-index: 10; display: flex; align-items: center;">
        <img src="{{ asset('juegos/juegos/laberinto/css/nave2.webp') }}" style="width: 25%; height: 25%;" alt="">
        <div class="contenedor_respuesta">
            <img src="{{ asset('juegos/juegos/laberinto/css/bandera.png') }}" style="width: 115%; height: 100%; z-index: -1; position: absolute; top: 0; left: 0; pointer-events: none;" alt="">
            <img src="{{ asset('juegos/juegos/laberinto/css/perdio_gano.png') }}" id="perdio_gano" alt="">
            <h1 id="pregunta_text">Pregunta</h1>
            <br>
            <div id="opciones_container" style="width: 100%;" class="row"></div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="miModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="miModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background-color: transparent !important; border-radius: 20px; border: 5px solid #ffffff96;">
            <div class="modal-body" style="background-color: #000000a8 !important; border-radius: 20px;">
                <h5 class="modal-title" id="miModalLabel" style="color: #ffffff; font-size: 40px; font-family: 'DS-Digital'; font-weight: bold; width: 100%; text-align: center;">Selecciona un nivel</h5>
                <div style="display: flex; justify-content: space-around; align-items: center; height: 100%; padding: 40px;">
                    <div class="card_nivel" onclick="seleccionar_nivel(1)" style="background-image: url({{ asset('juegos/juegos/laberinto/css/Recurso_1.png') }});"></div>
                    <div class="card_nivel" onclick="seleccionar_nivel(2)" style="background-image: url({{ asset('juegos/juegos/laberinto/css/Recurso_3.png') }});"></div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Modal -->
<div class="modal fade" id="miModal2" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="miModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="background-color: transparent !important; border-radius: 20px; border: 5px solid #ffffff96;">
            <div class="modal-body" style="background-color: #000000a8 !important; border-radius: 20px;">
                <h5 class="modal-title" id="miModalLabel" style="color: #ffffff; font-size: 40px; font-family: 'DS-Digital'; font-weight: bold; width: 100%; text-align: center;">Selecciona una categoría</h5>
                <div style="padding: 20px;" class="row" id="categorias_container">
                   
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('pie')
        @include('juegos.partials.terminar-js')
        <script src="{{ asset('juegos/juegos/assets/bootstrap/sweetalert.js') }}"></script>
        @include('juegos.partials.pantalla-js')
        <script src="{{ asset('juegos/juegos/laberinto/js/js2.js') }}"></script>
@endpush
