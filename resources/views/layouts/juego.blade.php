{{--
    Layout COMÚN de las páginas de juego (Fase 13): extiende `layouts/base` y se usa en los 7 juegos
    (`resources/views/juegos/juego/<slug>.blade.php`). A diferencia de `layouts/sitio` NO añade ningún recurso: cada juego
    tiene su propio conjunto de CSS/JS y el ORDEN importa (cascada y dependencias), así que la vista declara el suyo.
    Lo que sí fija este layout: favicon vacío (los originales no tenían), y tres puntos de extensión.

    Lo que rellena cada vista:
      @section('titulo', '…')          <title> (escapado por Blade).
      @section('viewport', '…')        contenido del <meta viewport>; o bien @section('sin_viewport', 'sí') para NO emitirlo
                                       (sopa_letras y juego_ahorcado no lo tenían; se conserva el comportamiento).
      @section('atributos_body')       atributos de <body> (style="…").
      @push('cabecera')                hojas de estilo y scripts del <head>, EN SU ORDEN (CSS y JS en la misma pila para
                                       respetar el orden relativo del original). El <style> inline original va como
                                       <link> a `estilo-pagina.css` en su posición.
      @section('juego')                el cuerpo de la página (sin los scripts finales).
      @push('pie')                     scripts del final del <body>, en su orden (jQuery < explora-api < terminar_juego).
    Bloques comunes: parciales en `juegos/partials/` (terminar-css/js, explora-api, pantalla-css/js).

    IMPORTANTE: la URL canónica de cada juego termina en «/» (`/juegos/juegos/<slug>/`) porque el JS y el CSS del juego
    resuelven sus archivos relativos (`datos.json`, `../sounds/…`) contra ella. No se usa <base>.
--}}
@extends('layouts.base')

{{-- Sin favicon: los HTML originales de los juegos no lo declaraban. --}}
@section('favicon')
@endsection

@section('cabecera')
        @stack('cabecera')
@endsection

@section('contenido')
@yield('juego')
@endsection

@section('scripts_pie')
        @stack('pie')
@endsection
