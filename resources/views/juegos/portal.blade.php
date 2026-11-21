{{--
    Portal de juegos (`/juegos`, Fase 12): migración de `public/juegos/index.html` (se eliminó) a Blade.
    Lo entrega PortalJuegosController y recibe un único dato: `$haySesion` (bool, SESIÓN DE SERVIDOR con cuenta activa).

    Usa `layouts/base` (no `layouts/sitio`): este portal tiene su propio Bootstrap 5.3, AOS y CSS en `public/juegos/`.
    Como la URL es `/juegos` (sin «/» final) las rutas relativas del HTML original (`css/…`, `images/…`, `juegos/…`)
    ya no resolverían: TODAS las URL pasan por asset(). Los enlaces `#` de las fichas son un defecto heredado (FUN-07)
    y se conservan tal cual. Orden de CSS y JS idéntico al original.

    Estado de la barra: con sesión se ven «Mi Perfil» y «Salir»; sin ella, «Iniciar sesión». Todo esto lo pinta el
    servidor para que no haya parpadeo; el JS (`mapplic/js/iniciarSesion.js`) lo mantiene al día tras login/logout.
--}}
@extends('layouts.base')

@section('titulo', 'Juegos Educativos Explora')
@section('viewport', 'width=device-width, initial-scale=1.0')
@section('atributos_body') class="bg-light"@endsection

{{-- El icono va entre las hojas y los scripts (posición original), con su `type`: se anula el de base y se emite abajo. --}}
@section('favicon')
@endsection

@section('cabecera')
        <!-- Bootstrap CSS -->
        <link href="{{ asset('juegos/librerias/bootstrap-5.3/css/bootstrap.min.css') }}" rel="stylesheet">
        <!-- Font Awesome -->
        <link href="{{ asset('juegos/librerias/fontawesome/css/all.min.css') }}" rel="stylesheet">
        <!-- AOS CSS para animaciones -->
        <link href="{{ asset('juegos/librerias/aos/aos.css') }}" rel="stylesheet">
        <!-- Custom CSS -->
        <link rel="stylesheet" href="{{ asset('juegos/css/styles.css') }}">
        <link rel="stylesheet" href="{{ asset('juegos/css/clouds.css') }}">
        <link rel="shortcut icon" href="{{ asset('juegos/images/Isotipo.png') }}" type="image/x-icon">
        <script src="{{ asset('sweetalert/sweetalert2.all.min.js') }}"></script>
        <script type="text/javascript" src="{{ asset('bootstrap/jquery.min.js') }}"></script>
        <script src="{{ asset('js/explora-api.js') }}"></script>
@endsection

@push('estilos')
    {{-- Estilos de las opciones de avatar (los reutiliza el selector de avatares de otras páginas). --}}
    <style>
        .avatar_container{
            width: 100%;
            height: 100%;
            border-radius: 50%;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .avatar_container img{
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar_container:hover{
            transform: scale(1.05);
            cursor: pointer;
        }

        .avatar_container.active{
            border: 4px solid #48ff00;
        }
    </style>
@endpush

@section('contenido')

    <!-- Contenedor de nubes -->
    <div class="cloud-container">
        <div class="cloud cloud-1"></div>
        <div class="cloud cloud-2"></div>
        <div class="cloud cloud-3"></div>
        <div class="cloud cloud-4"></div>
        <div class="cloud cloud-5"></div>
        <div class="cloud cloud-6"></div>
        <div class="cloud cloud-7"></div>
        <div class="cloud cloud-8"></div>
        <div class="cloud cloud-9"></div>
        <div class="cloud cloud-10"></div>
    </div>

    {{-- Barra superior. Los tres botones de sesión los decide el SERVIDOR ($haySesion); iniciarSesion.js los actualiza
         después de iniciar o cerrar sesión desde esta página (usa `display: block`, igual que aquí, para no saltar). --}}
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" style="display: flex; align-items: center;" href="#">
                <img src="{{ asset('juegos/images/logo.png') }}" alt="Logo" width="60" height="60" class="d-inline-block align-text-top">
                Juegos Educativos Explora
            </a>
            <div class="d-flex justify-content-end gap-3">
               
                <a href="{{ url('/perfil') }}" style="display: {{ $haySesion ? 'block' : 'none' }};" id="btn-perfil" class="btn btn-outline-warning">
                    <i class="fas fa-user"></i> Mi Perfil
                </a>
                <a href="#" type="button" onclick="cerrarSesion()" id="btn-cerrar" aria-label="Cerrar sesión" style="display: {{ $haySesion ? 'block' : 'none' }};" class="btn btn-outline-primary">
                     <i class="fas fa-sign-out-alt"></i> Salir
                </a>
                <a href="#" onclick="mostrarModalIniciarSesion(2)" type="button" id="btn-iniciar" style="display: {{ $haySesion ? 'none' : 'block' }};" class="btn btn-outline-warning">
                    <i class="fas fa-sign-out-alt"></i> Iniciar sesión
                </a>
                <a href="/" class="btn btn-outline-danger">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section con Parallax -->
    <section id="inicio" class="parallax-section">
        <div class="container h-100">
            <div class="row h-100 align-items-center">
                <div class="col-12 text-center" data-aos="fade-up">
                    <h1 class="display-4 fw-bold">¡Explora Colombia Jugando!</h1>
                    <p class="lead">Descubre las maravillas de nuestro país mientras aprendes y te diviertes</p>
                    <div class="colombian-flag">
                        <span class="flag-stripe yellow"></span>
                        <span class="flag-stripe blue"></span>
                        <span class="flag-stripe red"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="scroll-indicator" onclick="document.getElementById('juegos').scrollIntoView({behavior: 'smooth'})"></div>
    </section>

    <!-- Sección de Juegos -->
    <section id="juegos" class="py-5">
        <div class="container">
            <h2 class="text-center mb-2" id="letrerojuegos" style="color: black !important; opacity: 0 !important;" data-aos="fade-up">Nuestros Juegos</h2>
            <div class="puzzle-container initial">
                <!-- Pieza 1 -->
                <div class="puzzle-piece no-action" data-game="matematicas" data-aos="fade-up" data-aos-delay="100">
                    <div class="flip-card">
                        <div class="flip-side flip-front">
                            <img src="{{ asset('juegos/images/ficha1.png') }}" alt="Banderas" class="puzzle-image">
                            <div class="header-puzzle">
                                <div class="puzzle-icon">🚀</div>
                                <h3 class="puzzle-title">Navega y aprende</h3>
                            </div>
                        </div>
                        <div class="flip-side flip-back">
                            <img src="{{ asset('juegos/images/ficha1.png') }}" alt="Banderas" class="puzzle-image">
                            <div class="body-puzzle" style="z-index: 100;">
                                <p class="puzzle-description">LLeva la nave a las estaciones espaciales y responde las preguntas.</p>
                                <a href="{{ asset('juegos/juegos/laberinto') }}/" class="btn btn-warning mt-3" style="color: black !important">¡Jugar!</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pieza 2 -->
                <div class="puzzle-piece no-action" data-game="lenguaje" data-aos="fade-up" data-aos-delay="200">
                    <div class="flip-card">
                        <div class="flip-side flip-front">
                            <img src="{{ asset('juegos/images/ficha6.png') }}" alt="Banderas" class="puzzle-image">
                            <div class="header-puzzle">
                                <div class="puzzle-icon">
                            <img src="{{ asset('juegos/images/pinta.png') }}" alt="Banderas" width="80" height="80">
                        </div>
                                <h3 class="puzzle-title">Conoce las banderas</h3>
                            </div>
                        </div>
                        <div class="flip-side flip-back">
                            <img src="{{ asset('juegos/images/ficha6.png') }}" alt="Banderas" class="puzzle-image">
                            <div class="body-puzzle" style="z-index: 100;">
                                <p class="puzzle-description">Pinta o adivina las banderas de los departamentos de Colombia.</p>
                                <a href="{{ asset('juegos/juegos/banderas-color') }}/" class="btn btn-success mt-3">¡Jugar!</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pieza 3 -->
                <div class="puzzle-piece no-action" data-game="ciencias" data-aos="fade-up" data-aos-delay="300">
                    <div class="flip-card">
                        <div class="flip-side flip-front">
                            <img src="{{ asset('juegos/images/ficha3.png') }}" alt="Banderas" class="puzzle-image">
                            <div class="header-puzzle">
                                <div class="puzzle-icon">
                                    <img src="{{ asset('juegos/images/arma.png') }}" alt="Banderas" width="80" height="80">
                                </div>
                                <h3 class="puzzle-title">Rompecabezas</h3>
                            </div>
                        </div>
                        <div class="flip-side flip-back">
                            <img src="{{ asset('juegos/images/ficha3.png') }}" alt="Banderas" class="puzzle-image">
                            <div class="body-puzzle" style="z-index: 100;">
                                <p class="puzzle-description">Sigue las instrucciones y arma el rompecabezas.</p>
                                <a href="{{ asset('juegos/juegos/puzzle') }}/" class="btn btn-info mt-3">¡Jugar!</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pieza 4 -->
                <div class="puzzle-piece no-action" data-game="historia" data-aos="fade-up" data-aos-delay="400">
                    <div class="flip-card">
                        <div class="flip-side flip-front">
                            <img src="{{ asset('juegos/images/ficha4.png') }}" alt="Banderas" class="puzzle-image">
                            <div class="header-puzzle">
                                <div class="puzzle-icon">
                                    <img src="{{ asset('juegos/images/sopa.png') }}" alt="Banderas" width="80" height="80">
                                </div>
                                <h3 class="puzzle-title">Sopa de letras</h3>
                            </div>
                        </div>
                        <div class="flip-side flip-back">
                            <img src="{{ asset('juegos/images/ficha4.png') }}" alt="Banderas" class="puzzle-image">
                            <div class="body-puzzle">
                                <p class="puzzle-description">Explora la rica historia de Colombia a través de juegos interactivos.</p>
                                <a href="{{ asset('juegos/juegos/sopa_letras') }}/" class="btn btn-danger mt-3">¡Jugar!</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pieza 5 -->
                <div class="puzzle-piece no-action" data-game="geografia" data-aos="fade-up" data-aos-delay="500">
                    <div class="flip-card">
                        <div class="flip-side flip-front">
                            <img src="{{ asset('juegos/images/ficha5.png') }}" alt="Banderas" class="puzzle-image">
                            <div class="header-puzzle">
                                <div class="puzzle-icon">
                                    <img src="{{ asset('juegos/images/arrastra.png') }}" alt="Banderas" width="97" height="80">
                                </div>
                                <h3 class="puzzle-title">Ubica los puntos</h3>
                            </div>
                        </div>
                        <div class="flip-side flip-back">
                            <img src="{{ asset('juegos/images/ficha5.png') }}" alt="Banderas" class="puzzle-image">
                            <div class="body-puzzle">
                                <p class="puzzle-description">Aprende sobre las regiones, ríos y montañas de Colombia.</p>
                                <a href="#" type="button" class="btn btn-info mt-3" data-bs-toggle="modal" data-bs-target="#myModal">¡Jugar!</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pieza 6 -->
                <div class="puzzle-piece no-action" data-game="cultura" data-aos="fade-up" data-aos-delay="600">
                    <div class="flip-card">
                        <div class="flip-side flip-front">
                            <img src="{{ asset('juegos/images/ficha2.png') }}" alt="Banderas" class="puzzle-image">
                            <div class="header-puzzle">
                                <div class="puzzle-icon">
                                    <img src="{{ asset('juegos/images/cruci.png') }}" alt="Banderas" width="120" height="80">
                                </div>
                                <h3 class="puzzle-title" style="text-align: center;">Adivina la palabra</h3>
                            </div>
                        </div>
                        <div class="flip-side flip-back">
                            <img src="{{ asset('juegos/images/ficha2.png') }}" alt="Banderas" class="puzzle-image">
                            <div class="body-puzzle">
                                <p class="puzzle-description">Sigue las pistas y completa la palabra, antes de agotar tus intentos.</p>
                                <a href="{{ asset('juegos/juegos/juego_ahorcado') }}/" class="btn btn-purple mt-3">¡Jugar!</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <h6 style="opacity: 0;">PO</h6>
        </div>
    </section>


    <!-- The Modal -->
    <div class="modal fade" id="myModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
        
                <!-- Modal Header -->
                <div class="modal-header">
                    <h4 style="width: 100%; text-align: center; font-weight: bold; color: #ff5e00;" class="modal-title">Selecciona el tipo de juego</h4>
                </div>
        
                <!-- Modal body -->
                <div class="modal-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="card-juego" onclick="location.href = '{{ asset('juegos/juegos/arrastra') }}/'">
                                <img style="width: 110%; height: 110%" src="{{ asset('juegos/images/drag.png') }}" alt="Banderas">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card-juego" onclick="location.href = '{{ asset('juegos/juegos/completa') }}/'">
                                <img style="width: 110%; height: 110%" src="{{ asset('juegos/images/completa.png') }}"  alt="Banderas">
                            </div>
                        </div>
                    </div>
                </div>
        
                <!-- Modal footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal"><i class="fas fa-times"></i> Cerrar</button>
                </div>
        
            </div>
        </div>
    </div>
   
    <!-- Footer -->
    <footer class="bg-white py-4 shadow-sm">
        <div class="container text-center">
            <p class="mb-0">&copy; <span id="year"></span> Juegos Educativos Colombia. Todos los derechos reservados.</p>
        </div>
    </footer>
@endsection

@section('scripts_pie')
    {{-- El año del pie lo escribe un archivo estático (antes era un script inline), en la posición original. --}}
    <script src="{{ asset('juegos/js/anio-pie.js') }}"></script>
    <!-- Scripts -->
    <script src="{{ asset('juegos/librerias/bootstrap-5.3/js/bootstrap.bundle.js') }}"></script>
    <script src="{{ asset('juegos/librerias/aos/aos.js') }}"></script>
    <script src="{{ asset('juegos/js/main.js') }}"></script>
    <script src="{{ asset('juegos/js/clouds.js') }}"></script>
    <script src="{{ asset('mapplic/js/iniciarSesion.js') }}"></script>
    <!-- iniciarSesion.js refresca los botones de sesión al cargar y cuando ExploraApi.sesionLista se resuelve. -->
@endsection
