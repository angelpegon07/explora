{{--
    Juego «completa» (Fase 13): migración de public/juegos/juegos/completa/index.html (se eliminó) a Blade.
    Completa la oración. Datos en completa.json; la fuente TitanOne se carga desde completa/fuente/. El original traía un segundo <title> duplicado: se omite.

    URL canónica: /juegos/juegos/completa/ (CON barra final). Casi todo lo que carga el juego en ejecución (JSON, sonidos, imágenes
    por JS, url() de CSS) es RELATIVO a la URL de la página, por eso JuegoController redirige la forma sin barra.
    Los recursos del HTML pasan por asset() (URL absolutas). El orden de CSS y JS es el del original (cascada y
    dependencias: jQuery antes de explora-api.js y este antes de terminar_juego.js). Sin datos del servidor y sin salida sin escapar.
    El <style> inline se extrajo a estilo-pagina.css, enlazado en la MISMA posición del <head> (misma cascada).
--}}
@extends('layouts.juego')

@section('titulo', 'Completa la Oración')
@section('viewport', 'width=device-width, initial-scale=1.0')

@push('cabecera')
        <link rel="stylesheet" href="{{ asset('juegos/librerias/bootstrap-5.3/css/bootstrap.min.css') }}">
        <script src="{{ asset('juegos/librerias/bootstrap-5.3/js/bootstrap.bundle.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset('juegos/juegos/assets/bootstrap/jquery.min.js') }}"></script>
        @include('juegos.partials.explora-api')
        <link rel="stylesheet" href="{{ asset('juegos/librerias/fontawesome/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset('juegos/juegos/completa/styles.css') }}">
        <script src="{{ asset('juegos/juegos/assets/bootstrap/sweetalert.js') }}"></script>
        @include('juegos.partials.pantalla-css')
        @include('juegos.partials.terminar-css')
        <link rel="stylesheet" href="{{ asset('juegos/juegos/completa/estilo-pagina.css') }}">
@endpush

@section('juego')
    <div class="container">
        <!-- Header del Juego -->


        <!-- Sección de Categorías -->
        <section id="seccion-categorias" style="position: relative;" class="seccion">
            <h1>Selecciona una Categoría</h1>
            <button id="btn-volver" onclick="location.href = '/juegos'"
                style="position: absolute; top: -13px; right: -10px;" class="btn-menu"></button>
            <div id="categorias-container" class="categorias row">
                <!-- Las categorías se cargarán dinámicamente aquí -->
            </div>
        </section>

        <!-- Sección de Niveles -->
        <section id="seccion-niveles" class="seccion oculto">
            <h2 style="font-size: 3.5rem; width: 100%; margin-bottom: 9rem !important;">Selecciona un Nivel</h2>
            <div id="niveles-container" class="niveles row" style="width: 100%;">
                <!-- Los niveles se cargarán dinámicamente aquí -->
            </div>
        </section>
        <!-- Sección del Juego -->
        <section id="seccion-juego" style="height: 100%; padding: 4vh;" class="seccion oculto">
            <div class="row" style="height: 100%;">
                <div class="col-9"
                    style="padding-right: 20px; display: flex;flex-direction: column;justify-content: space-between;">
                    <div class="pregunta-container">
                        <div id="pregunta-actual"></div>
                    </div>
                    <div id="opciones-container" class="opciones-container"></div>
                    <button id="btn-siguiente" class="btn-siguiente" disabled>Siguiente</button>
                </div>
                <div class="col-3" style="padding: 0px;">
                    <div class="game-header oculto">
                        <div style="width: 100%; display: flex; justify-content: space-around;">
                            <button id="btn-volver" onclick="location.href = '/juegos'" class="btn-menu">
                            </button>
                            <button id="btn-actualizar" class="btn-menu">
                            </button>
                        </div>
                        <div class="pregunta-counter" style="width: 100%;">
                            Pregunta <br> <span id="pregunta-actual-numero">1</span> de 10
                        </div>
                        <div class="juego-header" style="width: 100%;">
                            <div id="puntuacion">Puntaje <br> <span id="puntuacion-numero">0</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sección de Resultados -->
        <section id="seccion-resultados" class="seccion oculto">
            <h2>¡Juego Terminado!</h2>
            <div id="resultado-final"></div>
            <button id="btn-reiniciar" class="btn-reiniciar">Jugar de Nuevo <i
                    class="fa-solid fa-rotate-left"></i></button>
            <button id="btn-volver-final" onclick="location.href = '/juegos'" class="btn-reiniciar">Volver <i
                    class="fas fa-arrow-left"></i></button>
        </section>
    </div>
@endsection

@push('pie')
        @include('juegos.partials.terminar-js')
        @include('juegos.partials.pantalla-js')
        <script src="{{ asset('juegos/juegos/completa/script.js') }}"></script>
@endpush
