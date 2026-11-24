{{--
    Layout BASE mínimo del sitio (Fase 12): solo el esqueleto HTML, sin ninguna hoja de estilo ni script de terceros.

    CUÁNDO USAR CADA LAYOUT
      - layouts/base   Páginas con su PROPIO conjunto de CSS/JS (perfil, portal de juegos y, en el futuro, los juegos).
                       La vista aporta su <head> completo (`cabecera`) y sus scripts finales (`scripts_pie`), cada uno en
                       el orden que necesite: la cascada CSS y la carga de scripts dependen de ese orden.
      - layouts/sitio  Páginas del mapa de Colombia (portada y mapas temáticos): extiende a `base` y le añade las ~15
                       hojas y las librerías que comparten esas seis páginas. Ver su cabecera.

    Contiene lo único que es IDÉNTICO en todas las páginas: doctype, <html lang="es">, <meta charset>, <title>,
    <meta viewport>, favicon y los puntos de extensión. Las URL de recursos de las vistas deben usar asset().

    Secciones (rellena cada vista; `@yield` NO vuelve a escapar lo que ya escapó Blade al definirlo):
      @section('titulo', '…')       texto del <title> (con la forma de dos argumentos Blade lo escapa; en bloque, usar {{ }}).
      @section('viewport', '…')     (opcional) contenido del <meta viewport>. Por defecto `width=device-width, initial-scale=1`.
      @section('sin_viewport', 'sí')  (opcional, Fase 13) NO emite el <meta viewport>: para páginas que no lo tenían (sopa y ahorcado).
      @section('atributos_html')    (opcional) atributos extra de <html>, p. ej. ` style="…"` (con espacio inicial).
      @section('atributos_body')    (opcional) atributos extra de <body>, con espacio inicial.
      @section('favicon')           (opcional) sustituye al <link rel="shortcut icon"> por defecto; vacío = sin favicon aquí.
      @section('cabecera')          resto del <head> (hojas de estilo y scripts), en su orden.
      @push('estilos')              <style> propios; se imprimen al final del <head>.
      @section('contenido')         el <body> sin los scripts finales.
      @section('scripts_pie')       scripts finales, en su orden.

    Fase 17F-C (accesibilidad y PWA básico): este layout emite el enlace «Saltar al contenido» (destino `#contenido-principal`,
    que cada vista marca con `role="main" id="contenido-principal" tabindex="-1"` sobre un contenedor YA existente, sin
    envolver nada para no alterar el DOM que esperan el CSS y el JS), el manifiesto, `theme-color`, el icono de Apple,
    `css/accesibilidad.css` y `js/teclado.js`.
--}}
<!DOCTYPE html>
<html lang="es"@yield('atributos_html')>

    <head>
        <meta charset="utf-8">
        <title>@yield('titulo')</title>
        @sectionMissing('sin_viewport')
        <meta name="viewport" content="@yield('viewport', 'width=device-width, initial-scale=1')">
        @endif
        @section('favicon')
        <link rel="shortcut icon" href="{{ asset('juegos/images/Isotipo.png') }}">
        @show
        {{-- Fase 17F-C: PWA básico (manifiesto + color de la barra + icono de pantalla de inicio; SIN service worker). --}}
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <meta name="theme-color" content="#007bff">
        <link rel="apple-touch-icon" href="{{ asset('icons/icono-192.png') }}">
        {{-- Fase 17F-C: enlace de salto, .sr-only, foco visible y movimiento reducido; va ANTES del CSS de la página. --}}
        <link rel="stylesheet" href="{{ asset_v('css/accesibilidad.css') }}">
        @yield('cabecera')

        @stack('estilos')
    </head>

    <body @yield('atributos_body')>

        {{-- Fase 17F-C: primer elemento enfocable; lleva al contenedor `#contenido-principal` (landmark main) de cada página. --}}
        <a class="saltar-contenido" href="#contenido-principal">Saltar al contenido</a>

        @yield('contenido')

        @yield('scripts_pie')
        {{-- Fase 17F-C: Enter/Espacio activan los elementos con role="button" que no son <button>. --}}
        <script src="{{ asset_v('js/teclado.js') }}"></script>
    </body>
</html>
