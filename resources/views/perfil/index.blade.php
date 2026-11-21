{{--
    Página «Mi perfil» (`/perfil`, Fase 12): migración de `public/miPerfil.html` (se eliminó) a Blade.
    Lo entrega PerfilController. SEGURIDAD: los datos salen de la sesión del servidor; no se recibe ningún id.

    Variable `$datos`:
      - array con `nombre`, `usuario` (login), `grado` (texto), `avatar` (1..max) y `avatares` (max)  -> hay sesión activa.
      - null                                                                                          -> sin sesión (o cuenta inactiva).

    Con sesión se pinta el formulario, el historial (lo rellena script.js con GET /api/historial) y el modal de avatares.
    Sin sesión se pinta SOLO el aviso «Debe iniciar sesión…»: ni formulario, ni historial, ni datos, y no se carga
    `script.js` (por tanto nadie pide `/api/historial`). Todo valor del usuario se imprime con {{ }} (escapado): el
    nombre y el login vienen de una base externa y podrían contener HTML.

    Usa `layouts/base` (esta página tiene su propio conjunto de CSS/JS; ver la cabecera de ese layout). El orden de las
    hojas y de los scripts es el del HTML original.
--}}
@extends('layouts.base')

@section('titulo', 'Mi Perfil')

@section('cabecera')
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
    <link rel="stylesheet" href="{{ asset('css/general.css') }}">
    <link rel="stylesheet" href="{{ asset('miPerfil/style.css') }}">
@endsection

@section('contenido')
    <div class="container-fluid">
        <div class="row">
            <!-- Menú lateral -->
            <div class="col-md-3 pt-4" style="background-color: #007bff; height: 100vh;">
                <div class="text-center">
                    <img src="{{ asset('img/logo.png') }}" alt="logo" style="width: 200px; height: auto;">
                </div>
                @if ($datos !== null)
                <div class="nav flex-column nav-pills" id="menu-perfil" role="tablist">
                    <a class="nav-link active" id="link-datos" href="#" onclick="mostrarSeccion('datos', this)">
                        <i class="fas fa-user"></i> Mis Datos
                    </a>
                    <a class="nav-link" id="link-historial" href="#" onclick="mostrarSeccion('historial', this)">
                        <i class="fas fa-history"></i> Mi Historial
                    </a>
                    <hr>
                    <a style="color: white; background-color: #dd6118 !important; border: 3px solid #974514 !important;" class="nav-link" id="link-cerrar-sesion" href="#" aria-label="Cerrar sesión" onclick="cerrarSesion()">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
                @endif
            </div>

            <!-- Contenido principal -->
            <div class="col-md-9 py-4">
                @if ($datos === null)
                {{-- Sin sesión (o cuenta inactiva): solo el aviso, con el mismo texto y estructura que mostrarAvisoSinSesion() de script.js. --}}
                <div id="aviso-sesion" class="alert alert-warning text-center" role="alert">Debe iniciar sesión para ver su perfil. <a href="/">Ir al inicio</a></div>
                @else
                <!-- Sección Mis Datos -->
                <div id="datos" style=" display: flex; flex-direction: column; height: 100%; justify-content: space-evenly; padding-left: 100px; padding-right: 100px;">
                    <form  class="card p-4 shadow-lg rounded-lg bg-white border-0 rounded">
                        <!-- Avatar -->
                        <div class="mb-3 text-center">
                            <img  src="{{ asset('juegos/images/avatars/'.$datos['avatar'].'.png') }}" alt="Avatar" id="avatar-preview" class="avatar-preview mb-2"> <br>
                            <button type="button" class="btn btn-primary" onclick="mostrarModalAvatar()"> <i class="fas fa-pencil-alt"></i> Cambiar Avatar</button>
                        </div>

                        <!-- Nombre -->
                        <div class="form-group">
                            <label for="nombre">Nombre</label>
                            <input disabled type="text" class="form-control" id="nombre" placeholder="Ingresa tu nombre" value="{{ $datos['nombre'] }}">
                        </div>

                        <!-- Usuario -->
                        <div class="form-group">
                            <label for="usuario">Usuario</label>
                            <input disabled type="text" class="form-control" id="usuario" placeholder="Nombre de usuario" value="{{ $datos['usuario'] }}">
                        </div>


                        <!-- Grado -->
                        <div class="form-group">
                            <label for="grado">Grado</label>
                            <select disabled class="form-control" id="grado">
                                {{-- «Selecciona tu grado» queda marcada solo si el grado del usuario no es uno de 1..11. --}}
                                <option @selected(! in_array($datos['grado'], array_map('strval', range(1, 11)), true)) disabled>Selecciona tu grado</option>
                                @foreach (range(1, 11) as $grado)
                                <option value="{{ $grado }}" @selected($datos['grado'] === (string) $grado)>{{ $grado }}°</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="d-flex justify-content-center" style="gap: 20px; margin-top: 20px;">
                            <button id="btn-guardar" onclick="guardarCambios()" style="width: 40%;" type="button" class="btn btn-success">
                                <i class="fas fa-save"></i>
                                Guardar Cambios
                            </button>
                            <button style="width: 40%;" type="button" onclick="location.href='{{ url('/juegos') }}'" class="btn btn-danger"> <i class="fas fa-arrow-left"></i> Volver</button>
                        </div>
                    </form>
                </div>

                <!-- Sección Mi Historial (la tabla la rellena script.js con textContent) -->
                <div id="historial" class="card p-5 shadow-lg rounded-lg bg-white border-0 rounded" style="display: none; height: 100%; overflow-y: auto;">
                    <h2 style="color: #007bff; font-weight: bold;">Mi Historial</h2>
                    <p>Historial de juegos jugados, puntajes, etc.</p>
                    <hr>
                    <table class="table table-bordered table-historial">
                        <thead>
                            <tr>
                                <th>Juego</th>
                                <th>Categoría</th>
                                <th>Nivel</th>
                                <th>Puntaje</th>
                                <th>Tiempo</th>
                                <th>Fecha</th>
                                <th>Hora</th>
                            </tr>
                        </thead>
                        <tbody id="historial-body">

                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    @if ($datos !== null)
    {{-- Selector de avatares. La lista la genera Blade con `config('explora.avatares_max')` (ya no son 35 fijos en JS).
         Cada opción conserva el contrato: [data-avatar] con role="button" y tabindex="0" (lo usan script.js y las pruebas). --}}
    <div class="modal fade" id="modal-avatar" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Cambiar Avatar</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="avatars-preview" class="row">
                        @foreach (range(1, $datos['avatares']) as $n)
                        <div class="col-lg-1" style="padding: 10px;">
                            <div class="avatar_container" role="button" tabindex="0" id="avatar_{{ $n }}" data-avatar="{{ $n }}" aria-label="Avatar {{ $n }}">
                                <img class="img-fluid" src="{{ asset('juegos/images/avatars/'.$n.'.png') }}" alt="" loading="lazy">
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection

@section('scripts_pie')
    <script type="text/javascript" src="{{ asset('bootstrap/jquery.min.js') }}"></script>
    {{-- explora-api.js también corre sin sesión: su GET /api/sesion deja limpia la caché de localStorage de una sesión ya caducada. --}}
    <script src="{{ asset('js/explora-api.js') }}"></script>
    @if ($datos !== null)
    <script src="{{ asset('bootstrap/bootstrap.min.js') }}"></script>
    <script src="{{ asset('miPerfil/script.js') }}"></script>
    <script src="{{ asset('miPerfil/cerrar-sesion.js') }}"></script>
    @endif
@endsection
