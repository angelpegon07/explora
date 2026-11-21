{{--
    «Viaja y explora el tiempo» (`/ViajaPED/`, Fase 15). Sustituye a `public/ViajaPED/index.html`.

    Sirve `PaginaEstaticaController` (lista cerrada en config/paginas_estaticas.php) sobre `layouts/base`. La vista es HTML
    estático: `script.js` carga `data.json` (`xhttp.open("GET", "data.json")`) y las imágenes (`img/…`, `epocas/…`) con rutas
    RELATIVAS a la URL de la página, igual que `styles.css` (`url('img/fondo.png')`, `./fonts/…`); por eso la URL canónica
    lleva SIEMPRE barra final. Los recursos de este HTML usan asset().

    Los atributos `style="display: none;"` son del original: `script.js` los muestra al elegir una época.
    No se conserva un `</div>` sobrante que el original traía tras `.container` (sin apertura; el navegador lo ignoraba).
--}}
@extends('layouts.base')

@section('titulo', 'Épocas Históricas')
@section('viewport', 'width=device-width, initial-scale=1.0')

{{-- Sin favicon: el HTML original no lo declaraba. --}}
@section('favicon')
@endsection

@section('cabecera')
        <link rel="stylesheet" href="{{ asset('ViajaPED/styles.css') }}">
@endsection

@section('contenido')
    <div id="background" style="display: none;" class="background-container">

    </div>
    <div id="tooltip" class="tooltip">

  </div>
    <div class="header-container">
        <div class="header-title">
            <h1 id="header-title">Viaja y explora el tiempo</h1>
        </div>
        <div class="header-button">
        </div>
    </div>
    <div class="container">
        {{-- Sección de eras --}}
        <div class="eras-section" id="eras-section">
            <div class="eras-circle">
                <div class="era-item" data-era="prehistoria">
                    <img src="{{ asset('ViajaPED/img/epocaPrehistoria.png') }}" alt="Prehistoria">
                    <h3 class="era-title">Prehistoria</h3>
                </div>
                <div class="era-item" data-era="antigua">
                    <img src="{{ asset('ViajaPED/img/epocaAntigua.png') }}" alt="Edad Antigua">
                    <h3 class="era-title">Edad Antigua</h3>
                </div>
                <div class="era-item" data-era="media">
                    <img src="{{ asset('ViajaPED/img/epocaMedia.png') }}" alt="Edad Media">
                    <h3 class="era-title">Edad Media</h3>
                </div>
                <div class="era-item" data-era="moderna">
                    <img src="{{ asset('ViajaPED/img/epocaModerna.png') }}" alt="Edad Moderna">
                    <h3 class="era-title">Edad Moderna</h3>
                </div>
                <div class="era-item" data-era="contemporanea">
                    <img src="{{ asset('ViajaPED/img/epocaContemporanea.png') }}" alt="Edad Contemporánea">
                    <h3 class="era-title">Edad Contemporánea</h3>
                </div>
            </div>
        </div>
        <div class="machine-container">
            <img src="{{ asset('ViajaPED/img/maquina.png') }}" alt="Máquina del tiempo" class="machine">
        </div>
        {{-- Contenedor del túnel del tiempo (20 círculos, uno por iteración) --}}
        <div class="time-tunnel-container">
            <div class="time-tunnel">
                @for ($i = 0; $i < 20; $i++)
                <div class="tunnel-circle"></div>
                @endfor
            </div>
        </div>

        {{-- Sección de detalles de la era (ahora en un div separado) --}}
        <div id="era-details-section" style="display: none;" class="era-details-section">

        </div>

    </div>

    {{-- Modal para mostrar detalles de elementos --}}
    <div id="element-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 id="modal-title"></h2>
            <div id="modal-content"></div>
            {{-- agregar botones para aumentar la fuente del contenido --}}
            <div class="container-btn">
                <div class="btn-aumentar"></div>
                <div class="btn-disminuir"></div>
            </div>

        </div>
    </div>
@endsection

@section('scripts_pie')
        <script src="{{ asset('ViajaPED/script.js') }}"></script>
@endsection
