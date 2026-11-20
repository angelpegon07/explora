{{--
    Juego «arrastra» (Fase 13): migración de public/juegos/juegos/arrastra/index.html (se eliminó) a Blade.
    Arrastra y suelta. No carga pantalla_completa.css (solo el .js), a diferencia de los demás; se conserva. Datos en data.js, lógica en game.js, panzoom.min.js local.

    URL canónica: /juegos/juegos/arrastra/ (CON barra final). Casi todo lo que carga el juego en ejecución (JSON, sonidos, imágenes
    por JS, url() de CSS) es RELATIVO a la URL de la página, por eso JuegoController redirige la forma sin barra.
    Los recursos del HTML pasan por asset() (URL absolutas). El orden de CSS y JS es el del original (cascada y
    dependencias: jQuery antes de explora-api.js y este antes de terminar_juego.js). Sin datos del servidor y sin salida sin escapar.
    El <style> inline se extrajo a estilo-pagina.css, enlazado en la MISMA posición del <head> (misma cascada).
--}}
@extends('layouts.juego')

@section('titulo', 'Arrastra y Suelta')
@section('viewport', 'width=device-width, initial-scale=1.0')

@push('cabecera')
        <link rel="stylesheet" href="{{ asset('juegos/librerias/bootstrap-5.3/css/bootstrap.min.css') }}">
        <script src="{{ asset('juegos/librerias/bootstrap-5.3/js/bootstrap.bundle.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset('juegos/juegos/assets/bootstrap/jquery.min.js') }}"></script>
        @include('juegos.partials.explora-api')
        <link rel="stylesheet" href="{{ asset('juegos/librerias/fontawesome/css/all.min.css') }}">
        @include('juegos.partials.terminar-css')
        <script src="{{ asset('juegos/juegos/assets/bootstrap/sweetalert.js') }}"></script>
        <link rel="stylesheet" href="{{ asset('juegos/juegos/arrastra/styles.css') }}">
        <script src="{{ asset('juegos/juegos/arrastra/panzoom.min.js') }}"></script>
        <link rel="stylesheet" href="{{ asset('juegos/juegos/arrastra/estilo-pagina.css') }}">
@endpush

@section('juego')
    <!-- Modal Bootstrap -->
    <div class="modal fade" id="categoryModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="categoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" style="width: 100%; text-align: center;" id="categoryModalLabel">
                        Selecciona una Categoría
                    </h3>
                </div>
                <div class="modal-body">
                    <div class="row row-cols-1 row-cols-md-2 g-4" id="categoriesGrid">
                        <!-- Las categorías se generarán dinámicamente -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="nivelModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-labelledby="nivelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" style="width: 100%; text-align: center;" id="nivelModalLabel">
                        Selecciona un Nivel
                    </h3>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-6" style="display: flex; justify-content: center; align-items: center;">
                            <div class="nivel-container" onclick="selectNivel(4)">
                                <img src="{{ asset('juegos/juegos/arrastra/img/nivel1.png') }}" class="nivel-imagen" alt="">
                                <p>Principiante</p>
                            </div>
                        </div>
                        <div class="col-lg-6" style="display: flex; justify-content: center; align-items: center;">
                            <div class="nivel-container" onclick="selectNivel(2)">
                                <img src="{{ asset('juegos/juegos/arrastra/img/nivel2.png') }}" class="nivel-imagen" alt="">
                                <p>Experto</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="display: flex; justify-content: center; align-items: center; width: 100%; position: relative;">
        <div class="game-container" style="margin-right: 20vw;">
            <div class="game-content">
                <div style="position: relative; margin-bottom: 8%;">
                    <h5 id="categoryTitle" style="position: relative; z-index: 2; color: #573b15; text-align: center;">
                        Arrastra y Suelta</h5>
                    <div class="options-container text-center">
                        <div class="row" style="width: 100%;" id="optionsContainer">

                        </div>
                    </div>
                    <img src="{{ asset('juegos/juegos/arrastra/img/title.png') }}" class="title_imagen" alt="">
                    <img src="{{ asset('juegos/juegos/arrastra/img/flotante2.png') }}" class="flotante2" alt="">
                </div>

                <div id="contenedorGameArea"
                    style="border: 3px solid #d7edff; overflow: hidden; border-radius: 10px; position: relative;  width: 555px; height: 585px;">
                    <h2 class="contador" id="contador">00:00</h2>

                    <div class="game-area" id="gameArea">

                    </div>
                    <div class="zoomControls">
                        <div class="zoomOut" id="zoomOut"></div>
                        <div class="zoomIn" id="zoomIn"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="menu">
            <img src="{{ asset('juegos/juegos/arrastra/img/cartel.png') }}" class="cartel" alt="">
            <div
                style="z-index: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 60%;">
                <br>
                <h3>MENÚ</h3>
                <button onclick="resetGame()" id="resetGame" class="btn btn-warning"><i class="fas fa-sync-alt"></i>
                    REINICIAR</button>
                <button onclick="window.location.href='../../index.html'" id="checkAnswers" class="btn btn-danger"><i
                        class="fas fa-arrow-left"></i> SALIR</button>
            </div>
            <img src="{{ asset('juegos/juegos/arrastra/img/flotante.png') }}" class="flotante" alt="">
        </div>

        <div class="panControls">
            <div class="panControlsContainer">
                <div id="panUp"></div>
                <div id="panLeft"></div>
                <div id="panRight"></div>
                <div id="panDown"></div>
            </div>
        </div>
    </div>
@endsection

@push('pie')
        @include('juegos.partials.terminar-js')
        @include('juegos.partials.pantalla-js')
        <script src="{{ asset('juegos/juegos/arrastra/data.js') }}"></script>
        <script src="{{ asset('juegos/juegos/arrastra/game.js') }}"></script>
@endpush
