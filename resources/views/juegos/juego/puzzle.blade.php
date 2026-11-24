{{--
    Juego «puzzle» (Fase 13): migración de public/juegos/juegos/puzzle/index.html (se eliminó) a Blade.
    Rompecabezas. Usa Bootstrap 5.3 compartido, popper.min.js (public/vendor/popper) y jquery-ui.min.js (local) (en ese orden, tras jQuery).

    URL canónica: /juegos/juegos/puzzle/ (CON barra final). Casi todo lo que carga el juego en ejecución (JSON, sonidos, imágenes
    por JS, url() de CSS) es RELATIVO a la URL de la página, por eso JuegoController redirige la forma sin barra.
    Los recursos del HTML pasan por asset() (URL absolutas). El orden de CSS y JS es el del original (cascada y
    dependencias: jQuery antes de explora-api.js y este antes de terminar_juego.js). Sin datos del servidor y sin salida sin escapar.
    El <style> inline se extrajo a estilo-pagina.css, enlazado en la MISMA posición del <head> (misma cascada).
--}}
@extends('layouts.juego')

@section('titulo', 'Rompecabezas Interactivo')
@section('viewport', 'width=device-width, initial-scale=1.0')
@section('atributos_body')style="margin: 0; padding: 0; min-height: 100vh;"@endsection

@push('cabecera')
        <link rel="stylesheet" href="{{ asset_v('juegos/juegos/puzzle/styles.css') }}">
        <link rel="stylesheet" href="{{ asset_v('vendor/fontawesome/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset_v('juegos/librerias/bootstrap-5.3/css/bootstrap.min.css') }}">
        <script src="{{ asset_v('juegos/librerias/bootstrap-5.3/js/bootstrap.bundle.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset_v('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
        @include('juegos.partials.explora-api')
        <script src="{{ asset_v('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
        @include('juegos.partials.pantalla-css')
        @include('juegos.partials.terminar-css')
        <script src="{{ asset_v('vendor/popper/popper.min.js') }}"></script>
        <script src="{{ asset_v('juegos/juegos/puzzle/jquery-ui.min.js') }}"></script>
        <link rel="stylesheet" href="{{ asset_v('juegos/juegos/puzzle/estilo-pagina.css') }}">
@endpush

@section('juego')
    <div class="container" role="main" id="contenido-principal" tabindex="-1" style="padding: 0px !important;">

        <div class="puzzle-container">
            <div class="contenedor_puzzle">
                <div id="puzzle-board"></div>
            </div>
            <div class="separador"></div>
            <div id="pieces-container"></div>
        </div>
        <div id="message" class="message"></div>
    </div>
    <div style="width: 30px"></div>

    <div class="opciones">
        <h6>Menú</h6>
        <p id="tiempo">00:00</p>
        <button id="btn_ayuda" class="rounded-circle btn btn-primary"><i class="fas fa-eye"></i></button>
        <button id="btn_reiniciar" class="rounded-circle btn btn-primary"><i class="fas fa-undo"></i></button>
        <button id="btn_salir" onclick="window.location.href = '/juegos'" class="rounded-circle btn btn-primary"><i class="fas fa-arrow-left"></i></button>
    </div>

    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="false"
        data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border: none; background-color: transparent;">
                <div class="modal-body" style="display: flex; justify-content: center; align-items: center;">
                    <div class="controls">
                        <h3>Selecciona el nivel de dificultad</h3>
                        <br>
                        <div class="row" style="width: 100%;">
                            <div class="col-md-4" id="nivel1" role="button" tabindex="0" aria-label="Nivel 1" onclick="seleccionar_nivel(4)">
                                <div class="card_nivel" style="background-image: url({{ asset('juegos/juegos/puzzle/img/1.png') }});">

                                </div>
                            </div>
                            <div class="col-md-4" id="nivel2" role="button" tabindex="0" aria-label="Nivel 2" onclick="seleccionar_nivel(6)">
                                <div class="card_nivel" style="background-image: url({{ asset('juegos/juegos/puzzle/img/2.png') }});">

                                </div>
                            </div>
                            <div class="col-md-4" id="nivel3" role="button" tabindex="0" aria-label="Nivel 3" onclick="seleccionar_nivel(8)">
                                <div class="card_nivel" style="background-image: url({{ asset('juegos/juegos/puzzle/img/3.png') }});">

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="myModal2" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="false"
        data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content" style="border: none; background-color: transparent;">
                <div class="modal-body" style="display: flex; justify-content: center; align-items: center;">
                    <div class="contenedor_principal_categorias controls">
                        <h3>Selecciona una categoría</h3>
                        <br>
                        <div class="row" style="width: 100%;" id="categorias">

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
        <script src="{{ asset_v('juegos/juegos/puzzle/script.js') }}"></script>
@endpush
