{{--
    Juego «sopa_letras» (Fase 13): migración de public/juegos/juegos/sopa_letras/index.html (se eliminó) a Blade.
    Sopa de letras. Particularidades: (1) carga pantalla_completa.js ANTES de terminar_juego.js (orden inverso al resto, se conserva); (2) el <script> inline de ~235 líneas se extrajo a juego.js, en el mismo sitio, sin cambiar el código (incluido el muerto `readText`); (3) el original no declaraba viewport ni lang: se conserva sin viewport (sección `sin_viewport`) y el layout añade lang="es".

    URL canónica: /juegos/juegos/sopa_letras/ (CON barra final). Casi todo lo que carga el juego en ejecución (JSON, sonidos, imágenes
    por JS, url() de CSS) es RELATIVO a la URL de la página, por eso JuegoController redirige la forma sin barra.
    Los recursos del HTML pasan por asset() (URL absolutas). El orden de CSS y JS es el del original (cascada y
    dependencias: jQuery antes de explora-api.js y este antes de terminar_juego.js). Sin datos del servidor y sin salida sin escapar.
    El <style> inline se extrajo a estilo-pagina.css, enlazado en la MISMA posición del <head> (misma cascada).
--}}
@extends('layouts.juego')

@section('titulo', 'Sopa de letras')
@section('sin_viewport', 'sí')
@section('atributos_body')style="display: flex; align-items: center; min-height: 100vh !important; overflow: hidden;"@endsection

@push('cabecera')
        <link rel="stylesheet" type="text/css" href="{{ asset_v('juegos/juegos/sopa_letras/style.css') }}" media="all">
        <link rel="stylesheet" href="{{ asset_v('juegos/librerias/bootstrap-5.3/css/bootstrap.min.css') }}">
        <script src="{{ asset_v('juegos/librerias/bootstrap-5.3/js/bootstrap.bundle.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset_v('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
        @include('juegos.partials.explora-api')
        <script type="text/javascript" src="{{ asset_v('juegos/juegos/sopa_letras/wordfind.js') }}"></script>
        <script type="text/javascript" src="{{ asset_v('juegos/juegos/sopa_letras/wordfindgame.js') }}"></script>
        <script type="text/javascript" src="{{ asset_v('juegos/juegos/sopa_letras/tag-it.js') }}"></script>
        <script src="{{ asset_v('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
        <link rel="stylesheet" href="{{ asset_v('vendor/fontawesome/css/all.min.css') }}">
        @include('juegos.partials.pantalla-css')
        @include('juegos.partials.terminar-css')
        <link rel="stylesheet" href="{{ asset_v('juegos/juegos/sopa_letras/estilo-pagina.css') }}">
@endpush

@section('juego')

  <div style="display: none" class="overlay">
    <div class="nube">
      <p class="borde2" id="bienvenida"></p>
    </div>
    <div style="z-index: 1003;position: absolute;top: 20pt;left: 20pt;">
      <button onclick="cerrar_anuncio()" id="btnomitir" class="btn btn-warning"
        style="width: auto; padding: 4pt; border-radius: 8pt; font-size: 30px; color: black;">Omitir <i
          style="color: black" class="fa fa-forward" aria-hidden="true"></i></button>
    </div>
  </div>

  <div
    style="display: none; background-color: rgba(255, 255, 255, 0.664); width: 100%; height: 100%; position: fixed; z-index: 100;"
    id="fondo_blanco">

  </div>

  <div
    style="text-align: center; display: none; justify-content: center; align-items: center; flex-direction: column; opacity: 0; transition: opacity 0.5s ease-in-out;"
    id="final" class="final">
    <img src="{{ asset('juegos/juegos/assets/images/victoria.webp') }}" alt="" style="width: 600px;">
    <br>
    <h1 style="font-size: 25pt; padding-bottom: 0px;" class="borde2" id="texto_final">Felicitaciones, has encontrado
      todas las palabras</h1>
    <br>
    <div class="row">
      <div class="col-12" style="padding-bottom: 0pt; margin-top: 0px;">
        <h1 style="font-size: 25pt; padding: 12px;" class="borde2">¿Quieres jugar nuevamente?</h1>
      </div>
      <div class="col-3"></div>
      <div class="col-3 text-center">
        <button style="width: 80%;border-radius: 20pt; font-size: 23px; -webkit-text-stroke: 1px #1a3b0c;"
          onclick="window.location.reload(1)" class="btn btn-success">Si, jugar</button>
      </div>
      <div class="col-3 text-center">
        <button style="width: 80%; border-radius: 20pt; font-size: 23px; -webkit-text-stroke: 1px #4d0d0d;"
          onclick="location.href='/juegos'" class="btn btn-danger">No,
          salir</button>
      </div>
      <div class="col-3"></div>
    </div>
  </div>

  <div id="principal" class="container" role="main" style="overflow: hidden;">
    <div class="row text-center" id="contenido-principal" tabindex="-1">
      <div style="margin-top: 20px; width: 100%;">
        <div class="row">
          <div class="panel panel-default"
            style="width: 100%; background-color: transparent; border-color: transparent; width: 1000pt;">
            <div class="panel-body">
              <div class="row" style="width: 98%;">
                <div id="pal" class="col-2">
                  <h3>Palabras</h3>
                  <hr>
                  <div id='Palabras'>

                  </div>
                </div>
                <div class="col-7">
                  <div id='juego' style="background-color: white"></div>
                </div>
                <div class="col-3"
                  style="display: flex; justify-content: flex-start; align-items: center; flex-direction: column;">

                  <h2 id="contador"
                    style="font-size: 40pt; color: rgb(255, 255, 255); -webkit-text-stroke: 1px #47260f;">00:00</h2>

                  <div class="opciones text-center">
                    <h3 style="font-size: 19pt; color: rgb(255, 255, 255); -webkit-text-stroke: 1px #47260f;">Opciones
                    </h3>
                    <hr>
                    <button onclick="elegir();" class="btn btn-success btn-lg"> <i class="fa fa-gamepad"></i> Crear
                      Juego</button>
                    <br>
                    <button id='solve' class="btn btn-warning btn-lg"> <i class="fa fa-check"></i> Resolver</button>
                    <br>
                    <button onclick="location.href='/juegos'" class="btn btn-danger btn-lg"> <i
                        class="fa fa-arrow-left"></i> Volver</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>


  <div class="modal fade" id="nivelModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
    aria-labelledby="nivelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header" style="border-bottom: none;">
          <h3 class="modal-title" style="width: 100%; text-align: center;" id="nivelModalLabel">
            Selecciona un Nivel
          </h3>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-lg-6" style="display: flex; justify-content: center; align-items: center;">
              <div class="nivel-container" role="button" tabindex="0" aria-label="Nivel 1" onclick="selectNivel(10)">
                <img src="{{ asset('juegos/juegos/sopa_letras/img/nivel1.png') }}" class="nivel-imagen" alt="">
                <p>Principiante</p>
              </div>
            </div>
            <div class="col-lg-6" style="display: flex; justify-content: center; align-items: center;">
              <div class="nivel-container" role="button" tabindex="0" aria-label="Nivel 2" onclick="selectNivel(5)">
                <img src="{{ asset('juegos/juegos/sopa_letras/img/nivel2.png') }}" class="nivel-imagen" alt="">
                <p>Experto</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('pie')
        @include('juegos.partials.pantalla-js')
        @include('juegos.partials.terminar-js')
        <script src="{{ asset_v('juegos/juegos/sopa_letras/juego.js') }}"></script>
@endpush
