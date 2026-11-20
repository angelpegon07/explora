{{--
    Juego «banderas-color» (Fase 13): migración de public/juegos/juegos/banderas-color/index.html (se eliminó) a Blade.
    Pinta la bandera / Adivina la bandera. Usa SU PROPIO Bootstrap 4.6.2 + jQuery 3.7.1 + SweetAlert 11.17 (carpetas bootstrap/, jquery.min.js y assets/sweetalert/), NO el Bootstrap 5.3 compartido. El original declaraba <html lang="en">; el layout usa lang="es" (el contenido está en español).

    URL canónica: /juegos/juegos/banderas-color/ (CON barra final). Casi todo lo que carga el juego en ejecución (JSON, sonidos, imágenes
    por JS, url() de CSS) es RELATIVO a la URL de la página, por eso JuegoController redirige la forma sin barra.
    Los recursos del HTML pasan por asset() (URL absolutas). El orden de CSS y JS es el del original (cascada y
    dependencias: jQuery antes de explora-api.js y este antes de terminar_juego.js). Sin datos del servidor y sin salida sin escapar.
    El <style> inline se extrajo a estilo-pagina.css, enlazado en la MISMA posición del <head> (misma cascada).
--}}
@extends('layouts.juego')

@section('titulo', 'La Bandera de Colombia')
@section('viewport', 'width=device-width, initial-scale=1.0')
@section('atributos_body')style="display: flex; justify-content: space-between; align-items: center; height: 100vh !important; margin: 0; flex-direction: column; overflow: hidden;"@endsection

@push('cabecera')
        <link rel="stylesheet" href="{{ asset('juegos/juegos/banderas-color/style.css') }}">
        <link rel="stylesheet" href="{{ asset('juegos/librerias/fontawesome/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset('juegos/juegos/banderas-color/bootstrap/bootstrap.min.css') }}">
        <script src="{{ asset('juegos/juegos/banderas-color/jquery.min.js') }}"></script>
        @include('juegos.partials.explora-api')
        <script src="{{ asset('juegos/juegos/banderas-color/assets/sweetalert/sweetalert.js') }}"></script>
        <script src="{{ asset('juegos/juegos/banderas-color/bootstrap/bootstrap.bundle.min.js') }}"></script>
        <script src="{{ asset('juegos/juegos/banderas-color/bootstrap/popper.min.js') }}"></script>
        @include('juegos.partials.pantalla-css')
        @include('juegos.partials.terminar-css')
        <link rel="stylesheet" href="{{ asset('juegos/juegos/banderas-color/estilo-pagina.css') }}">
@endpush

@section('juego')
    <div id="contenedor_juego1" style="display: none;">
        <div class="header" style="background-color: #a2b81b;">
            <h6 style="width: 99%; text-align: right;">Pregunta <span id="pregunta"></span> de 10</h6>
            <h3>Pinta la bandera de <span style="color: #e000ff;" id="departamento"></span></h3>
            <div class="botones_header" style="right: 0px; top: 6vh;">
                <button id="boton_volver" onclick="location.href='../../index.html'" style="-webkit-text-stroke: transparent;" class="btn btn-danger"><i class="fas fa-arrow-left"></i></button>
                <button id="boton_actualizar" onclick="window.location.reload(1)" style="-webkit-text-stroke: transparent;" class="btn btn-info"><i class="fas fa-sync-alt"></i></button>
            </div>
        </div>
        <div class="contenedor" style="position: relative; margin-top: 4vh;" id="contenedor_bandera" style="width: 280px; height: 180px; margin-top: 20px;"></div>
        <br>
        <div class="botones">
            <button id="boton_validar" onclick="verificarBanderas()" class="boton_acciones"><i class="fas fa-check"></i> Validar</button>
            <button id="boton_limpiar" onclick="limpiarBanderas()" class="boton_acciones"><i class="fas fa-eraser"></i> Limpiar</button>
        </div>
        <div class="paleta-colores">
            
        </div>
    </div>
    <div id="contenedor_juego2" style="display: none;">
        <div class="header" style="background-color: #ffd600; padding-top: 10px; height: 130px; display: flex; justify-content: center; align-items: center;">
            <h1>ADIVINA LA BANDERA</h1>
            <div class="botones_header">
                <button id="boton_volver" onclick="location.href='../../index.html'" style="-webkit-text-stroke: transparent;" class="btn btn-danger"><i class="fas fa-arrow-left"></i></button>
                <button id="boton_actualizar" onclick="window.location.reload(1)" style="-webkit-text-stroke: transparent;" class="btn btn-info"><i class="fas fa-sync-alt"></i></button>
            </div>
        </div>
        <div class="row" style="width: 100%; height: 100%;">

            <div class="col-md-3" style="background-color: #004284; display: flex; justify-content: center; align-items: center; flex-direction: column;">
                <h4 style="color: #ffffff; -webkit-text-stroke: 2px #012644;">BANDERA <span id="pregunta_adivina"></span>/10</h4>
                <br>
                <br>
                <h3 style="text-align: center; color: #ffffff; -webkit-text-stroke: 2px #012644;">Tiempo restante <br> <span style="font-size: 3rem;" id="timer_text_adivina">00:00</span></h3>
                <br>
                <br>
                <br>
                <h4 style="color: #ffffff; -webkit-text-stroke: 2px #012644;">Puntuación</h4>
                <br>
                <div class="row" style="width: 80%; display: flex; justify-content: center; align-items: center;">
                   <div class="col-6 text-center" style="display: flex; justify-content: center; align-items: center; flex-direction: column;">
                        <div style="width: 70px; height: 70px; background-color: #018f37; border-radius: 10px; border: 6px solid #ffffff9c; display: flex; justify-content: center; align-items: center;">
                            <h2 id="correctas_adivina" style="color: #ffffff; -webkit-text-stroke: 2px #012644; margin: 0;">0</h2>
                        </div>
                        <br>
                        <h6 style="color: #ffffff; -webkit-text-stroke: 2px #012644;">Bien</h6>
                   </div>
                   <div class="col-6 text-center" style="display: flex; justify-content: center; align-items: center; flex-direction: column;"> 
                        <div style="width: 70px; height: 70px; background-color: #ff0000; border-radius: 10px; border: 6px solid #ffffff9c; display: flex; justify-content: center; align-items: center;">
                            <h2 id="incorrectas_adivina" style="color: #ffffff; -webkit-text-stroke: 2px #012644; margin: 0;">0</h2>
                        </div>
                        <br>
                        <h6 style="color: #ffffff; -webkit-text-stroke: 2px #012644;">Mal</h6>
                   </div>
                </div>
            </div>
            <div class="col-md-9" style="display: flex; justify-content: center; align-items: center; flex-direction: column;">
                <img src="{{ asset('juegos/juegos/banderas-color/assets/fondo2.png') }}" style="width: 100%; height: 100%; position: absolute; top: 0; left: 0; z-index: -1; opacity: 0.5; pointer-events: none;" alt="fondo">
                <h5 style="-webkit-text-stroke: 2px #012644; width: 75%;color: #ffffff; background-color: #004386;padding: 15px;border-radius: 10px; border: 6px solid #3a3a3a9c; text-align: center;">Selecciona la bandera de <br> <span id="departamento_adivinar">Prueba</span></h5>
                <br>
                <div class="contenedor_banderas" style="width: 55%">
                    <div class="row" id="contenedor_banderas_row"></div>
                </div>
            </div>
        </div>
    </div>
   
      
    <!-- The Modal -->
    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModal" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border: none; padding: 20px; border-radius: 20px; background-color: #000000b0;">
                <!-- Modal Header -->
                <div class="modal-header" style="border: none;">
                    <h4 class="modal-title text-center" style="font-size: 1.5rem; width: 100%; color: #ffffff;">Selecciona el tipo de juego</h4>
                </div>
                
                <!-- Modal body -->
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6" style="display: flex; justify-content: center; align-items: center;">
                            <div class="cajon_juego" onclick="iniciarJuego('adivina')">
                                <img src="{{ asset('juegos/juegos/banderas-color/assets/juego2.png') }}" style="width: 100%; height: 100%;" alt="bandera correcta">
                            </div>
                        </div>
                        <div class="col-md-6" style="display: flex; justify-content: center; align-items: center;">
                            <div class="cajon_juego" onclick="iniciarJuego('pinta')">
                                <img src="{{ asset('juegos/juegos/banderas-color/assets/juego1.png') }}" style="width: 100%; height: 100%;" alt="bandera correcta">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('pie')
        @include('juegos.partials.terminar-js')
        @include('juegos.partials.pantalla-js')
        <script src="{{ asset('juegos/juegos/banderas-color/script.js') }}"></script>
@endpush
