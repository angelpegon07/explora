{{--
    Vista ÚNICA de los cinco mapas temáticos (climatico, coordilleras, etnias, parques_naturales, vertientes).

    La entrega MapaTematicoController con:
      $slug  clave del mapa en config/mapas.php.
      $mapa  arreglo de ese mapa (título, JSON, carpeta de imágenes, tutorial, variante, rasgos propios y CSS).

    Todo dato dinámico se imprime con {{ }} (escapado). La configuración que necesita el JS viaja en
    `window.EXPLORA_MAPA` mediante @json con las banderas HEX_*: nunca se concatenan datos en cadenas de JS.
    El JS compartido está en public/js/mapa-tematico.js.
--}}
@extends('layouts.sitio')

@section('titulo', $mapa['titulo'])

@push('estilos')
    @include('mapas.estilos', ['css' => $mapa['css'], 'datoInteres' => $mapa['dato_interes']])
@endpush

@section('contenido')
    @if ($mapa['dato_interes'])
        @include('mapas.dato-interes')
    @endif

    @include('partials.mapa-contenedor')
    @include('partials.panel-contenido')
    @include('partials.chat')
    @include('partials.modal-pantalla-completa', ['datoInteres' => $mapa['dato_interes']])
    @include('partials.modal-tipo-mapa')
    @include('partials.multimedia', ['quizAnidado' => $mapa['quiz_anidado']])
    @include('partials.menu-circular')
    @include('partials.menu-accesibilidad', ['tutorial' => $mapa['tutorial']])
    @include('partials.final')
@endsection

@push('scripts')
    {{--
        Configuración del mapa para public/js/mapa-tematico.js. Las URL ya son absolutas (asset()), de modo que el JS
        no depende de la ruta desde la que se sirve la página.
          json             URL del archivo de datos de Mapplic.
          carpetaImagenes  URL base de las imágenes del carrusel; «{carpeta}» se sustituye en el navegador por el
                           atributo `carpeta` del lugar (etnias y parques).
          variante         opción `variante` de Mapplic (null = no se envía).
          datoInteres      true = existe el panel #dato_interes.
          ocultarNiveles   true = ocultar .mapplic-levels a los 800 ms (vertientes).
    --}}
    @php
        $configuracionJs = [
            'slug' => $slug,
            'json' => asset($mapa['json']),
            'carpetaImagenes' => asset($mapa['carpeta_imagenes']),
            'variante' => $mapa['variante'],
            'datoInteres' => $mapa['dato_interes'],
            'ocultarNiveles' => $mapa['ocultar_niveles'],
        ];
    @endphp
    <script>
        window.EXPLORA_MAPA = @json($configuracionJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    </script>
    <script src="{{ asset('js/mapa-tematico.js') }}"></script>
@endpush
