{{--
    Layout base de las páginas del sitio (Fase 10; la portada `index` lo reutilizará en la Fase 11).

    Contiene lo que era IDÉNTICO en las cinco páginas de mapas: cabecera con todas las hojas de estilo y scripts del
    <head>, y el bloque de scripts del final. TODAS las URL de recursos usan asset(): así funcionan desde cualquier
    ruta (p. ej. `/climatico`, sin depender de rutas relativas ni de `../`).

    Secciones que rellena cada vista:
      @section('titulo')   texto del <title> (se escapa solo al imprimirlo con {{ }}).
      @push('estilos')     <style> propio de la página; se imprime al final del <head>.
      @section('contenido') todo el <body> salvo los scripts.
      @push('scripts')     scripts propios (configuración + JS de la página). Se imprimen DESPUÉS de las librerías
                           comunes y ANTES de tutorial.js e iniciarSesion.js, el mismo orden que tenían los HTML.
--}}
<!DOCTYPE html>
<html lang="es" style="max-height: 100vh; overflow: hidden;">

    <head>
        <meta charset="utf-8">
        <title>@yield('titulo')</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no">
        <meta name="description" content="Vector Europe map with clickable states and pins, an example of using Mapplic, a premium custom interactive map plugin to display custom image or vector (SVG) maps.">
        <link rel="stylesheet" type="text/css" href="{{ asset('css/style.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('css/map.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('mapplic/mapplic.css') }}">
        <link rel="stylesheet" href="{{ asset('bootstrap/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('fontawesome/css/all.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/chat.css') }}">
        <link rel="stylesheet" href="{{ asset('mapplic/menu.css') }}">
        <link rel="stylesheet" href="{{ asset('mapplic/quiz.css') }}">
        <link rel="stylesheet" href="{{ asset('sweetalert/sweetalert2.min.css') }}">
        <script src="{{ asset('sweetalert/sweetalert2.all.min.js') }}"></script>
        <link rel="stylesheet" href="{{ asset('css/utilidad.css') }}">
        <link rel="stylesheet" href="{{ asset('css/general.css') }}">
        <link rel="shortcut icon" href="{{ asset('juegos/images/Isotipo.png') }}">

        <script src="{{ asset('driver/driver.js.iife.js') }}"></script>
        <link rel="stylesheet" href="{{ asset('driver/driver.css') }}"/>

        @stack('estilos')
    </head>

    <body style="overflow: hidden; max-height: 100vh;">

        @yield('contenido')

        {{-- Librerías comunes. El orden importa: jQuery primero, Mapplic antes de Bootstrap, quiz/general/chat al final. --}}
        <script type="text/javascript" src="{{ asset('bootstrap/jquery.min.js') }}"></script>
        <script src="{{ asset('js/explora-api.js') }}"></script>
        <script type="text/javascript" src="{{ asset('js/hammer.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset('js/jquery.easing.js') }}"></script>
        <script type="text/javascript" src="{{ asset('js/jquery.mousewheel.js') }}"></script>
        <script type="text/javascript" src="{{ asset('mapplic/mapplic_generico.js') }}"></script>
        <script type="text/javascript" src="{{ asset('bootstrap/bootstrap.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset('mapplic/verMas.js') }}"></script>
        <script type="text/javascript" src="{{ asset('mapplic/js/quiz.js') }}"></script>
        <script type="text/javascript" src="{{ asset('mapplic/js/general.js') }}"></script>
        <script type="text/javascript" src="{{ asset('mapplic/js/chat.js') }}"></script>

        {{-- Scripts propios de cada página (configuración y JS inline de la vista). --}}
        @stack('scripts')

        <script src="{{ asset('driver/tutorial.js') }}"></script>
        <script src="{{ asset('mapplic/js/iniciarSesion.js') }}"></script>
    </body>
</html>
