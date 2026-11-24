{{--
    Línea de tiempo histórica de Colombia (`/lineaTiempo/`, Fase 15). Sustituye a `public/lineaTiempo/index.html`.

    Sirve `PaginaEstaticaController` (lista cerrada en config/paginas_estaticas.php) sobre `layouts/base`. La vista es HTML
    estático: la línea de tiempo la construye `script.js` con `eventos.json` (`fetch('eventos.json')`, relativo a la URL de la
    página, por eso la URL canónica lleva SIEMPRE barra final) y las imágenes `img/…` también son relativas desde el JS.
    Los recursos que aparecen en este HTML usan asset() (URL absolutas).

    Orden de los scripts (importa): gsap.min.js < ScrollTrigger.min.js (depende de GSAP) < script.js (usa ambos).
    El original cargaba los dos primeros a mitad del <body>; aquí van todos juntos al final (el HTML de los botones flotantes
    y del menú, que `script.js` busca por id, ya existe cuando se ejecutan).
--}}
@extends('layouts.base')

@section('titulo', 'Línea de Tiempo Histórica')
@section('viewport', 'width=device-width, initial-scale=1.0')

{{-- El favicon por defecto del layout (el de los juegos) se anula: esta página declara el suyo, y el original lo ponía al final del <head>. --}}
@section('favicon')
@endsection

@section('cabecera')
        <link rel="stylesheet" href="{{ asset_v('vendor/fontawesome/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset_v('lineaTiempo/styles.css') }}">
        <link rel="icon" type="image/jpg" href="{{ asset('lineaTiempo/favicon.png') }}" />
@endsection

@section('contenido')
    <div class="timeline-container" role="main" id="contenido-principal" tabindex="-1">
        <div class="pattern-overlay pattern-ancient"></div>
        <div class="timeline-header">
            <div class="header">
                <img src="{{ asset('lineaTiempo/img/explora.png') }}" width="150" alt="Colombia">
                <div class="timeline-header-content">
                    <h1>Línea de Tiempo Histórica de Colombia</h1>
                    <p>Explora la evolución de Colombia desde sus orígenes, pasando por sus momentos clave hasta la
                        actualidad.</p>
                </div>
            </div>
        </div>

        <div class="timeline" id="timeline">
            <div class="loading-message">
                <i class="fas fa-spinner fa-spin"></i> Cargando línea de tiempo...
            </div>
        </div>
    </div>

    {{-- Modal para información detallada --}}
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-container">
            <button class="modal-close" id="modalClose">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-content" id="modalContent">
                {{-- El contenido del modal se generará dinámicamente --}}
            </div>
        </div>
    </div>

    {{-- Botones de navegación flotantes --}}
    <div class="nav-buttons">
        <button class="nav-button go-top" id="goTop" title="Ir arriba">
            <i class="fas fa-arrow-up"></i>
        </button>
        <div class="owl-menu-button" id="toggleMenu" title="Menú de navegación">
            <div class="owl">
                <div class="owl-face">
                    <div class="owl-eyes">
                        <div class="owl-eye left"></div>
                        <div class="owl-eye right"></div>
                    </div>
                    <div class="owl-beak"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Menú de navegación --}}
    <div class="nav-menu" id="navMenu">
        <ul class="nav-menu-list" id="navMenuList">
            {{-- Los items del menú se generarán dinámicamente --}}
        </ul>
    </div>
@endsection

@section('scripts_pie')
        {{-- GSAP primero, luego ScrollTrigger y por último nuestro script (ver el orden en la cabecera de la vista). --}}
        <script src="{{ asset_v('lineaTiempo/gsap.min.js') }}"></script>
        <script src="{{ asset_v('lineaTiempo/ScrollTrigger.min.js') }}"></script>
        <script src="{{ asset_v('lineaTiempo/script.js') }}"></script>
@endsection
