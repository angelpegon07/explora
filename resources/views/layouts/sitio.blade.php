{{--
    Layout de las páginas del MAPA (portada y mapas temáticos); extiende a layouts/base desde la Fase 12 (Fase 10 para los mapas temáticos; la portada `inicio` lo reutiliza desde la Fase 11).

    Contiene lo que era IDÉNTICO en las cinco páginas de mapas: cabecera con todas las hojas de estilo y scripts del
    <head>, y el bloque de scripts del final. TODAS las URL de recursos usan asset(): así funcionan desde cualquier
    ruta (p. ej. `/climatico`, sin depender de rutas relativas ni de `../`).

    Secciones que rellena cada vista:
      @section('titulo')   texto del <title> (se escapa solo al imprimirlo con {{ }}).
      @section('hojas_orden') (opcional) las tres últimas hojas/enlaces del <head> en el orden propio de la página.
                           La cascada depende del orden de las hojas: los mapas temáticos cargaban utilidad.css ANTES
                           que general.css y la portada al revés. Por defecto se usa el orden de los mapas; la portada
                           redefine la sección entera (sin @parent) con su propio orden.
      @section('script_mapplic') (opcional) URL del script de Mapplic. Por defecto `mapplic_generico.js` (mapas
                           temáticos); la portada usa `mapplic.js`. Se escapa al imprimirse.
      @push('estilos')     <style> propio de la página; se imprime al final del <head>.
      @section('contenido') todo el <body> salvo los scripts.
      @push('scripts')     scripts propios (configuración + JS de la página). Se imprimen DESPUÉS de las librerías
                           comunes y ANTES de tutorial.js e iniciarSesion.js, el mismo orden que tenían los HTML.
--}}
@extends('layouts.base')

{{-- Fase 12: el esqueleto (doctype, <html>, <head>, <body>) vive en layouts/base; aquí solo lo propio de las páginas del mapa. --}}
{{-- Atributos en forma de BLOQUE: la forma de dos argumentos escaparía las comillas (&quot;). --}}
@section('atributos_html') style="max-height: 100vh; overflow: hidden;"@endsection
{{-- Fase 17F-C: sin maximum-scale ni user-scalable=no (WCAG 1.4.4: el usuario puede ampliar con el gesto de pellizco). Se usa el viewport por defecto de layouts/base. --}}
@section('atributos_body') style="overflow: hidden; max-height: 100vh;"@endsection

{{-- El favicon de estas páginas va dentro de `hojas_orden` (su posición respecto a las hojas es la original): se anula el de base. --}}
@section('favicon')
@endsection

@section('cabecera')
        <meta name="description" content="Vector Europe map with clickable states and pins, an example of using Mapplic, a premium custom interactive map plugin to display custom image or vector (SVG) maps.">
        <link rel="stylesheet" type="text/css" href="{{ asset_v('css/style.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset_v('css/map.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset_v('mapplic/mapplic.css') }}">
        <link rel="stylesheet" href="{{ asset_v('bootstrap/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset_v('vendor/fontawesome/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset_v('css/chat.css') }}">
        <link rel="stylesheet" href="{{ asset_v('mapplic/menu.css') }}">
        <link rel="stylesheet" href="{{ asset_v('mapplic/quiz.css') }}">
        <link rel="stylesheet" href="{{ asset_v('vendor/sweetalert2/sweetalert2.min.css') }}">
        <script src="{{ asset_v('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
        {{-- Orden de utilidad.css / general.css / icono: configurable por página (ver cabecera). Este es el de los mapas. --}}
        @section('hojas_orden')
        <link rel="stylesheet" href="{{ asset_v('css/utilidad.css') }}">
        <link rel="stylesheet" href="{{ asset_v('css/general.css') }}">
        <link rel="shortcut icon" href="{{ asset('juegos/images/Isotipo.png') }}">
        @show

        <script src="{{ asset_v('driver/driver.js.iife.js') }}"></script>
        <link rel="stylesheet" href="{{ asset_v('driver/driver.css') }}"/>

@endsection

@section('scripts_pie')
        {{-- Librerías comunes. El orden importa: jQuery primero, Mapplic antes de Bootstrap, quiz/general/chat al final. --}}
        <script type="text/javascript" src="{{ asset_v('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
        <script src="{{ asset_v('js/explora-api.js') }}"></script>
        <script type="text/javascript" src="{{ asset_v('js/hammer.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset_v('js/jquery.easing.js') }}"></script>
        <script type="text/javascript" src="{{ asset_v('js/jquery.mousewheel.js') }}"></script>
        <script type="text/javascript" src="@yield('script_mapplic', asset_v('mapplic/mapplic_generico.js'))"></script>
        <script type="text/javascript" src="{{ asset_v('bootstrap/bootstrap.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset_v('mapplic/verMas.js') }}"></script>
        <script type="text/javascript" src="{{ asset_v('mapplic/js/quiz.js') }}"></script>
        <script type="text/javascript" src="{{ asset_v('mapplic/js/general.js') }}"></script>
        <script type="text/javascript" src="{{ asset_v('mapplic/js/chat.js') }}"></script>

        {{-- Scripts propios de cada página (configuración y JS inline de la vista). --}}
        @stack('scripts')

        <script src="{{ asset_v('driver/tutorial.js') }}"></script>
        <script src="{{ asset_v('mapplic/js/iniciarSesion.js') }}"></script>
@endsection
