{{--
    Portada del sitio (`/`): mapa general de departamentos + ficha del departamento + test (quiz). Fase 11: migración de
    `public/index.html` (se eliminó) a Blade, reutilizando el layout y los parciales de los mapas temáticos (Fase 10).

    La entrega InicioController; no recibe datos: no hay nada dinámico en el HTML (la ficha y el quiz se piden a la API
    desde el navegador: verMas.js y quiz.js, contrato de ids intacto).

    Diferencias frente a los mapas temáticos (por eso se parametrizan los parciales):
      - Orden de CSS propio (general.css ANTES que utilidad.css): la cascada depende de él.
      - Usa `mapplic/mapplic.js` (los mapas temáticos usan mapplic_generico.js).
      - Chat completo, relleno de 20 px en #myModal, ficha del departamento, test real (`test-quiz`), visor ampliado.
--}}
@extends('layouts.sitio')

@section('titulo', 'Explora Colombia y aprende')

{{-- Orden original del <head> de la portada: general.css, icono y, al final, utilidad.css. --}}
@section('hojas_orden')
        <link rel="stylesheet" href="{{ asset_v('css/general.css') }}">
        <link rel="shortcut icon" href="{{ asset('juegos/images/Isotipo.png') }}">
        <link rel="stylesheet" href="{{ asset_v('css/utilidad.css') }}">
@endsection

@section('script_mapplic', asset_v('mapplic/mapplic.js'))

@push('estilos')
    {{-- Ajustes de los diálogos de SweetAlert2 (ventana oscura, texto centrado y en negrita) propios de la portada. --}}
    <style>
        div:where(.swal2-container) div:where(.swal2-popup) {
            display: none;
            position: relative;
            box-sizing: border-box;
            grid-template-columns: minmax(0, 100%);
            width: var(--swal2-width);
            max-width: 100%;
            padding: var(--swal2-padding);
            border: 5px solid #5e5e5e;
            border-radius: 25px;
            background: #000000d9 !important;
            color: #ffffff !important;
            font-family: inherit !important;
            font-size: 1rem !important;
            font-weight: bold !important;
        }


        div:where(.swal2-container) div:where(.swal2-html-container) {
            z-index: 1;
            justify-content: center;
            margin: 0;
            padding: 1em 1.6em .3em;
            overflow: auto;
            color: inherit;
            font-size: 1.425em !important;
            font-weight: bold !important;
            line-height: normal !important;
            text-align: center !important;
            word-wrap: break-word !important;
            word-break: break-word !important;
            cursor: initial !important;
        }
    </style>
@endpush

@section('contenido')
    {{-- Fase 17F-C: encabezado de nivel 1 solo para lectores de pantalla (la página no muestra un título visible). --}}
    <h1 class="sr-only">Explora Colombia: mapa interactivo de los departamentos</h1>

    @include('partials.mapa-contenedor')
    @include('partials.panel-contenido')
    @include('partials.chat', ['chatCompleto' => true])
    @include('partials.modal-pantalla-completa', ['relleno' => true])
    @include('partials.modal-tipo-mapa')
    @include('partials.ficha-departamento')
    @include('partials.multimedia')

    {{-- Departamento elegido: lo escribe mapplic.js al abrir una ficha y lo lee quiz.js para pedir sus preguntas. --}}
    <input type="hidden" id="departamento_seleccionado">

    @include('partials.test-quiz')
    @include('partials.menu-circular')
    @include('partials.menu-accesibilidad', ['tutorial' => 'inicio'])
    @include('partials.final')
    @include('partials.lightbox-imagen')
@endsection

@push('scripts')
    {{--
        Configuración para public/js/mapa-comun.js e inicio.js (misma forma que `window.EXPLORA_MAPA` de los mapas):
          json             URL absoluta del archivo de datos de Mapplic.
          carpetaImagenes  URL base del carrusel de imágenes (`verImagenes`).
          ocultarNiveles   true: a los 800 ms se oculta el selector de niveles de Mapplic (siempre, en la portada).
        @json con las banderas HEX_*: los datos nunca se concatenan en cadenas de JS.
    --}}
    @php
        $configuracionJs = [
            'slug' => 'inicio',
            'json' => asset('departamentos.json'),
            'carpetaImagenes' => asset('multimedia/imagenes'),
            'variante' => null,
            'datoInteres' => false,
            'ocultarNiveles' => true,
        ];
    @endphp
    <script>
        window.EXPLORA_MAPA = @json($configuracionJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    </script>
    <script src="{{ asset_v('js/mapa-comun.js') }}"></script>
    <script src="{{ asset_v('js/inicio.js') }}"></script>
@endpush
