{{--
    Ficha de un departamento (#modal_sitios): modal con un menú de pestañas (Descripción, Historia, Geografía…) y su
    contenido. SOLO existe en la portada. La rellena public/mapplic/verMas.js (contrato de ids) con los datos de
    GET /api/departamentos/{id}/ficha, y las pestañas las conmuta el JS común (`.bhoechie-tab-*`, public/js/mapa-comun.js).

    Está COPIADA TAL CUAL del HTML original de la portada (Fase 11), sin convertirla en bucles y sin tocar ids: verMas.js
    depende de ellos. Defectos heredados que se conservan a propósito y solo se reportan (no se «arreglan» aquí):
      - ids duplicados (`desEtnografia` dos veces, varios `justified-tabs-preview`);
      - verMas.js escribe en un `#modal-content-info` que no existe.
    Todo el texto es estático: el contenido dinámico lo inserta verMas.js en el navegador (escapado allí).
--}}
<div class="modal" id="modal_sitios">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal body -->
            <div class="modal-body" style="padding: 0px;">
                <button type="button" class="close cerrar_modal" data-dismiss="modal" aria-label="Cerrar">&times;</button>
                <div class="container" style="margin: 0px; max-width: 100%;">
                    <div class="row">
                        <div class="col-lg-12 row bhoechie-tab-container">
                            <div class="col-lg-3 col-md-3 col-sm-3 col-xs-3 bhoechie-tab-menu">
                                <div class="list-group">
                                    <a href="#" style="padding: .15rem 1.25rem"
                                        class="list-group-item active text-left">
                                        <i class="fa fa-list-alt" aria-hidden="true"></i> Descripción
                                    </a>
                                    <a href="#" style="padding: .15rem 1.25rem" class="list-group-item text-left">
                                        <i class="fa fa-hourglass-end" aria-hidden="true"></i> Historia
                                    </a>
                                    <a href="#" style="padding: .15rem 1.25rem" class="list-group-item text-left">
                                        <i class="fa fa-globe" aria-hidden="true"></i> Geografía
                                    </a>
                                    <a href="#" style="padding: .15rem 1.25rem" class="list-group-item text-left">
                                        <i class="fa fa-tint" aria-hidden="true"></i> Hidrografía
                                    </a>
                                    <a href="#" style="padding: .15rem 1.25rem" class="list-group-item text-left">
                                        <i class="fa fa-thermometer-three-quarters" aria-hidden="true"></i> Clima
                                    </a>
                                    <a href="#" style="padding: .15rem 1.25rem" class="list-group-item text-left">
                                        <i class="fa fa-users" aria-hidden="true"></i> Demografía
                                    </a>
                                    <a href="#" style="padding: .15rem 1.25rem" class="list-group-item text-left">
                                        <i class="fa fa-sitemap" aria-hidden="true"></i> Etnografía
                                    </a>
                                    <a href="#" style="padding: .15rem 1.25rem" class="list-group-item text-left">
                                        <i class="fa fa-paw" aria-hidden="true"></i> Fauna y flora
                                    </a>
                                    <a href="#" style="padding: .15rem 1.25rem" class="list-group-item text-left">
                                        <i class="fa fa-usd" aria-hidden="true"></i> Economía
                                    </a>
                                    <a href="#" style="padding: .15rem 1.25rem" class="list-group-item text-left">
                                        <i class="fa fa-child" aria-hidden="true"></i> Cultura
                                    </a>
                                    <a href="#" style="padding: .15rem 1.25rem" class="list-group-item text-left">
                                        <i class="fa fa-cutlery" aria-hidden="true"></i> Gastronomía
                                    </a>
                                    <a href="#" style="padding: .15rem 1.25rem" class="list-group-item text-left">
                                        <i class="fa fa-music" aria-hidden="true"></i> Festividades
                                    </a>
                                    <a href="#" style="padding: .15rem 1.25rem" class="list-group-item text-left">
                                        <i class="fa fa-map-signs" aria-hidden="true"></i> Sitios
                                    </a>
                                    <a href="#" style="padding: .15rem 1.25rem" class="list-group-item text-left">
                                        <i class="fa fa-user" aria-hidden="true"></i> Personajes célebres
                                    </a>
                                    <a href="#" style="padding: .15rem 1.25rem" class="list-group-item text-left">
                                        <i class="fa fa-map-marker" aria-hidden="true"></i> Capital
                                    </a>
                                </div>
                            </div>
                            <div class="col-lg-9 col-md-9 col-sm-9 col-xs-9 bhoechie-tab">
                                <h3 style="padding: 10px 20px; margin: 0;margin-top: 0;margin-top: 0;color:#55518a"
                                    id="nombreDepartamento"></h3>
                                <!-- flight section -->
                                <div class="bhoechie-tab-content active">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Descripción</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <div class="text-start mt-3">
                                                <div id="contenidoDepartameto"></div>
                                                <p class="text-muted mb-2 "><strong class="text-dark">Capital
                                                        :</strong> <span class="ms-2" id="descCapital"></span></p>
                                                <p class="text-muted mb-2 "><strong class="text-dark">Población
                                                        :</strong><span class="ms-2" id="descPoblacion"></span></p>
                                                <p class="text-muted mb-2 "><strong class="text-dark">Clima
                                                        :</strong> <span class="ms-2 " id="descClima"></span></p>
                                                <p class="text-muted mb-1 "><strong class="text-dark">Temperatura
                                                        :</strong> <span class="ms-2" id="descTemperatura"></span>
                                                </p>
                                                <p class="text-muted mb-1 "><strong class="text-dark">Extensión
                                                        :</strong> <span class="ms-2" id="descExtension"></span></p>
                                            </div>
                                            <div class="tab-content">
                                                <ul class="nav nav-pills bg-nav-pills nav-fill mt-3 mb-3">
                                                    <li class="nav-item">
                                                        <a href="#divBadera" data-toggle="pill"
                                                            aria-expanded="false" class="nav-link rounded-0 active"
                                                            id="aBadera">
                                                            <span class="d-none d-md-block">Bandera</span>
                                                        </a>
                                                    </li>
                                                    <li class="nav-item">
                                                        <a href="#divEscudo" data-toggle="pill" aria-expanded="true"
                                                            class="nav-link rounded-0" id="aEscudo">
                                                            <span class="d-none d-md-block">Escudo</span>
                                                        </a>
                                                    </li>
                                                </ul>

                                                <div class="tab-content px-3">
                                                    <div class="tab-pane active" id="divBadera">
                                                        <div id="bandera"></div>
                                                    </div>
                                                    <div class="tab-pane" id="divEscudo">
                                                        <div id="escudo"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- train section -->
                                <div class="bhoechie-tab-content">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Historia</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <div id="desHistoria">

                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- hotel search -->
                                <div class="bhoechie-tab-content">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Geografía</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <div id="contUbi">
                                                <div id="desUbicacion"></div>
                                            </div>
                                            <div style="display: none;" id="contCoord">
                                                <h5 class="card-title fw-600">Coordenadas.</h5>
                                                <div id="desCoordenadas"></div>
                                            </div>
                                            <div style="display: none;" id="contLimi">
                                                <h5 class="card-title fw-600">Limites.</h5>
                                                <div id="desLimites"></div>
                                            </div>
                                            <div style="display: none;" id="contReli">
                                                <h5 class="card-title fw-600">Relieve.</h5>
                                                <div id="desRelieve"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- hotel search -->
                                <div class="bhoechie-tab-content">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Hidrografía</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <div id="desHidrografia"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bhoechie-tab-content">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Clima</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <div id="desClima"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bhoechie-tab-content">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Demografía</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <div id="desPoblacion"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bhoechie-tab-content">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Etnografía</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <div id="desEtnografia"></div>
                                            <div class="tab-content" id="justified-tabs-preview">
                                                <ul class="nav nav-pills nav-justified mb-3 bg-light"
                                                    id="navEtnias">
                                                </ul>
                                                <div class="tab-content px-2" id="contNavEtnias">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bhoechie-tab-content">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Fauna y flora</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <h5 class="card-title fw-600">Fauna</h5>
                                            <div id="desFauna"></div>
                                            <div class="tab-content" id="justified-tabs-preview">
                                                <ul class="nav nav-pills nav-justified mb-3 bg-light" id="navFauna">
                                                </ul>
                                                <div class="tab-content px-2" id="contNavFauna">
                                                </div>
                                            </div>
                                            <hr class="m-4">
                                            <h5 class="card-title fw-600">Flora</h5>
                                            <div id="desFlora"></div>
                                            <div id="desEtnografia"></div>
                                            <div class="tab-content" id="justified-tabs-preview">
                                                <ul class="nav nav-pills nav-justified mb-3 bg-light" id="navFlora">
                                                </ul>
                                                <div class="tab-content px-2" id="contNavFlora">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bhoechie-tab-content">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Economía</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <div id="desEconomia"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bhoechie-tab-content">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Cultura</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <div id="contCultura">
                                                <h5 class="card-title fw-600">Descripción.</h5>
                                                <div id="desCultura"></div>
                                            </div>
                                            <div id="contArte" style="display: none;">
                                                <h5 class="card-title fw-600">Arte.</h5>
                                                <div id="desArte"></div>
                                            </div>
                                            <div id="contDanza" style="display: none;">
                                                <h5 class="card-title fw-600">Danza.</h5>
                                                <div id="desDanza"></div>
                                            </div>
                                            <div id="contMusica" style="display: none;">
                                                <h5 class="card-title fw-600">Música.</h5>
                                                <div id="desMusica"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bhoechie-tab-content">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Gastronomía</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <div id="desGastronomia"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bhoechie-tab-content">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Festividades</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <div class="tab-content" id="justified-tabs-preview">
                                                <ul class="nav nav-pills nav-justified mb-3 bg-light"
                                                    id="navFestividades">
                                                </ul>
                                                <div class="tab-content px-2" id="contNavFestividades">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bhoechie-tab-content">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Sitios de interés</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <div class="tab-content" id="justified-tabs-preview">
                                                <ul class="nav nav-pills nav-justified mb-3 bg-light"
                                                    id="navSitios">
                                                </ul>
                                                <div class="tab-content px-2" id="contNavSitios">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bhoechie-tab-content">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Personajes célebres</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <div class="tab-content" id="justified-tabs-preview">
                                                <ul class="nav nav-pills nav-justified mb-3 bg-light"
                                                    id="navPersonajes">
                                                </ul>
                                                <div class="tab-content px-2" id="contNavPersonajes">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bhoechie-tab-content">
                                    <div>
                                        <h4 style="margin-top: 0;color:#55518a">Capital</h4>
                                        <div class="card-body" style="height: 80vh; overflow-y: auto;">
                                            <div id="desCapital"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
