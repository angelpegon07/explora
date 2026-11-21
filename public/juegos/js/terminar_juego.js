/**
 * terminar_juego.js
 * =============================================================================
 * Modal «Guardar resultado» y ranking compartidos por los juegos de Explora.
 *
 * Se comunica con la API Laravel mediante window.ExploraApi (js/explora-api.js,
 * que debe cargarse ANTES que este archivo):
 *   - POST /api/resultados  guarda el puntaje. Con sesión, el servidor toma
 *     nombre, grado, avatar e id de la sesión; solo los invitados envían
 *     nombre, grado y avatar.
 *   - GET  /api/ranking     devuelve los mejores jugadores (máx. 5).
 *
 * Funciones globales que usan los juegos: guardar_resultado,
 * guardar_resultado_base_datos, cerrar_modal_guardar_resultado,
 * seleccionar_avatar, consultar_ranking y validar_guardar_resultado.
 * =============================================================================
 */

/** Datos de la partida en curso (los fija guardar_resultado). */
var nombre_juego_query = '';
var preguntas_correctas_query = '';
var tiempo_query = '';
var nivel_query = '';
var categoria_query = '';

/** Avatar elegido en el modal (1..35). */
var avatar_seleccionado = 1;

/** Instancia de bootstrap.Modal del modal de guardado (BS 5 y BS 4.6). */
var instancia_modal_guardar = null;

/** Milisegundos tras los que se fuerza la limpieza si no llega «hidden.bs.modal». */
var TIEMPO_RESPALDO_CIERRE = 600;

/**
 * Muestra un aviso al jugador con SweetAlert (o alert si no está cargado).
 * El texto se pasa como texto plano, nunca como HTML.
 * @param {string} mensaje Texto en español.
 * @param {string} [icono] Icono de SweetAlert ('error', 'warning'...).
 * @returns {void}
 */
function avisar_jugador(mensaje, icono) {
    // Con el modal de guardado abierto el aviso va DENTRO del modal: un Swal quedaba detrás de él (z-index) y,
    // además, sustituía al Swal «¡Felicidades!» pendiente cuyo .then redirigía la página (banderas-color).
    var aviso = document.getElementById('aviso_guardar');
    if (aviso) {
        aviso.textContent = mensaje;
        aviso.className = 'alert ' + (icono === 'error' ? 'alert-danger' : 'alert-warning');
        aviso.style.display = 'block';
        return;
    }
    if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: icono || 'error', text: mensaje });
    } else {
        alert(mensaje);
    }
}

/**
 * Quita el modal de guardado (si existe) junto con los restos que Bootstrap
 * deja en la página: fondo oscuro, clase modal-open y estilos del body.
 * No hace nada si ya hay OTRO modal con el mismo id (una apertura posterior).
 * @param {HTMLElement|null} elemento Modal concreto que se limpia (o null).
 * @returns {void}
 */
function limpiar_modal_guardar(elemento) {
    if (elemento) {
        $(elemento).remove();
    }
    if (document.getElementById('modal_guardar_resultado')) {
        return; // Hay un modal nuevo abierto: no se le quita su fondo.
    }
    $('.modal-backdrop').remove();
    document.body.classList.remove('modal-open');
    document.body.style.paddingRight = '';
    document.body.style.overflow = '';
    instancia_modal_guardar = null;
}

/**
 * Abre el modal para guardar el resultado de la partida y carga el ranking.
 * @param {string} nombre_juego Nombre del juego (p. ej. 'Ahorcado').
 * @param {(number|string)} preguntas_correctas Puntaje obtenido.
 * @param {number} contador_juego Duración en segundos.
 * @param {string} nivel Nivel del juego.
 * @param {string} categoria Categoría del juego.
 * @returns {void}
 */
function guardar_resultado(nombre_juego, preguntas_correctas, contador_juego, nivel, categoria) {

    nombre_juego_query = nombre_juego;
    preguntas_correctas_query = preguntas_correctas;
    tiempo_query = contador_juego;
    nivel_query = nivel;
    categoria_query = categoria;

    // Evita dos modales con el mismo id si se abre otra vez antes de cerrar el anterior.
    var anterior = document.getElementById('modal_guardar_resultado');
    if (anterior) {
        if (instancia_modal_guardar && typeof instancia_modal_guardar.dispose === 'function') {
            instancia_modal_guardar.dispose();
        }
        $(anterior).remove();
        limpiar_modal_guardar(null);
    }

    var tiempo_en_minutos = Math.floor(contador_juego / 60);
    var tiempo_en_segundos = contador_juego % 60;

    // Avatares: accesibles con teclado (role=button, tabindex, aria-label).
    var div_avatar = '';
    for (var i = 1; i <= 35; i++) {
        div_avatar += `<div class="col-lg-2" style="padding: 10px;">
            <div class="avatar_container" onclick="seleccionar_avatar(${i})" id="avatar_${i}" role="button" tabindex="0" aria-label="Avatar ${i}">
                <img src="../../images/avatars/${i}.png" alt="" class="img-fluid">
            </div>
        </div>`;
    }

    const modalHtml = `
        <div class="modal fade" id="modal_guardar_resultado" tabindex="-1" aria-labelledby="modal_guardar_resultado_titulo" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content" style="overflow: hidden; border-radius: 20px;">
                    <div class="modal-body" style="padding: 0px;">
                        <div class="row" id="row_guardar_resultado" style="margin: 0px; background: linear-gradient(90deg, rgba(224, 242, 255, 1) 57%, rgba(229, 204, 255, 1) 55%, rgba(229, 204, 255, 1) 2%, rgba(229, 204, 255, 1) 100%);">
                            <div class="col-lg-7" style="padding: 20px; padding-right: 60px;">
                                <h2 class="text-color-claro" id="modal_guardar_resultado_titulo" style="text-align: center;">GUARDA TU RESULTADO</h2>
                                <br>
                                <div class="row" style="margin: 0px;">
                                    <div class="col-lg-6" style="padding: 0px; padding-right: 10px;">
                                        <label for="nombre_jugador" class="h5 d-block text-color-claro">Ingresa tu nombre: </label>
                                        <input oninput="validar_guardar_resultado('nombre_jugador')" class="form-control" type="text" id="nombre_jugador" placeholder="Nombre">
                                    </div>
                                    <div class="col-lg-6" style="padding: 0px; padding-left: 10px;">
                                        <label for="grado_jugador" class="h5 d-block text-color-claro">Grado: </label>
                                        <select onchange="validar_guardar_resultado('grado_jugador')" class="form-control" id="grado_jugador">
                                            <option value="">Selecciona tu grado</option>
                                            <option value="1">1° Grado</option>
                                            <option value="2">2° Grado</option>
                                            <option value="3">3° Grado</option>
                                            <option value="4">4° Grado</option>
                                            <option value="5">5° Grado</option>
                                            <option value="6">6° Grado</option>
                                            <option value="7">7° Grado</option>
                                            <option value="8">8° Grado</option>
                                            <option value="9">9° Grado</option>
                                            <option value="10">10° Grado</option>
                                            <option value="11">11° Grado</option>
                                        </select>
                                    </div>
                                </div>
                                <br>
                                <h5 class="text-color-claro">Selecciona tu avatar: </h5>
                                <div class="row" id="div_avatar" style="margin: 0px; margin-top: 10px; max-height: 170px; overflow-y: auto;">
                                    ${div_avatar}
                                </div>
                                <br>
                                <div class="container-stats">
                                    <h5 class="text-primary"><i class="fas fa-check-circle text-color-claro"></i> <span class="text-dark">${ExploraApi.escaparHtml(preguntas_correctas)} Puntos</span></h5>
                                    <h5 class="text-primary"><i class="fas fa-clock text-color-claro"></i> <span class="text-dark">${tiempo_en_minutos} Mts ${tiempo_en_segundos} Seg</span></h5>
                                    <h5 class="text-primary"><i class="fa-solid fa-star text-color-claro"></i><i class="fa-solid fa-star text-color-claro"></i> <span class="text-dark">${ExploraApi.escaparHtml(nivel)}</span></h5>
                                </div>
                                <br>
                                <div id="aviso_guardar" role="alert" class="alert alert-warning" style="display: none; margin: 0 0 12px; text-align: center;"></div>
                                <div style="display: flex; justify-content: center; align-items: center;">
                                    <button type="button" onclick="guardar_resultado_base_datos()" style="width: 160px;" class="btn btn-primary" id="btnGuardar">Guardar <i class="fas fa-save"></i></button>
                                    <button type="button" style="width: 160px; margin-left: 20px;" class="btn btn-danger" onclick="cerrar_modal_guardar_resultado()">Cerrar <i class="fas fa-times-circle"></i></button>
                                </div>
                            </div>
                            <div class="col-lg-5" style="padding: 15px 10px 25px 5px;">
                                <h2 class="text-color" style="text-align: center;">RANKING</h2>
                                <h5 class="text-color" style="text-align: center;">${ExploraApi.escaparHtml(categoria)}</h5>
                                <div id="ranking_container" aria-live="polite"></div>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>`;

    // Agrega el modal al body
    $('body').append(modalHtml);

    // Inicializa el modal: estático (no se cierra con clic fuera ni con Esc).
    // Las opciones van por código porque Bootstrap 4.6 ignora los data-bs-*.
    instancia_modal_guardar = new bootstrap.Modal(document.getElementById('modal_guardar_resultado'), {
        backdrop: 'static',
        keyboard: false
    });
    instancia_modal_guardar.show();

    // El ranking se pide con el contenedor ya creado.
    consultar_ranking();

    setTimeout(() => {
        verificar_usuario_logueado();
    }, 500);
}

/**
 * Cierra el modal de guardado. Se elimina del DOM solo al terminar la animación
 * (evento «hidden.bs.modal», jQuery en BS 4 y BS 5); un temporizador de respaldo
 * garantiza que no queden fondo oscuro, modal-open ni estilos en el body.
 * @returns {void}
 */
function cerrar_modal_guardar_resultado() {
    var elemento = document.getElementById('modal_guardar_resultado');
    if (!elemento) {
        limpiar_modal_guardar(null);
        return;
    }
    var temporizador = null;
    var terminar = function () {
        clearTimeout(temporizador);
        limpiar_modal_guardar(elemento);
    };
    $(elemento).one('hidden.bs.modal', terminar);
    temporizador = setTimeout(terminar, TIEMPO_RESPALDO_CIERRE);
    if (instancia_modal_guardar) {
        instancia_modal_guardar.hide();
    }
}

/**
 * Marca como seleccionado el avatar indicado (si existe en el modal).
 * @param {(number|string)} id Número de avatar (1..35).
 * @returns {void}
 */
function seleccionar_avatar(id) {
    var avatar_seleccionado_html = document.getElementById('avatar_' + id);
    if (!avatar_seleccionado_html) {
        return;
    }
    var avatar_anterior = document.querySelector('.avatar_container.active');
    if (avatar_anterior) {
        avatar_anterior.classList.remove('active');
    }
    avatar_seleccionado_html.classList.add('active');
    avatar_seleccionado = Number(id);
}

// Activación de los avatares con teclado (Enter o Espacio).
document.addEventListener('keydown', function (evento) {
    var avatar = evento.target && evento.target.closest ? evento.target.closest('.avatar_container') : null;
    if (avatar && (evento.key === 'Enter' || evento.key === ' ' || evento.key === 'Spacebar')) {
        evento.preventDefault();
        seleccionar_avatar(avatar.id.replace('avatar_', ''));
    }
});

/**
 * Restaura el botón «Guardar» a su estado inicial (habilitado y con su texto).
 * @returns {void}
 */
function restaurar_boton_guardar() {
    $('#btnGuardar').attr('disabled', false)
        .html('Guardar <i class="fas fa-save"></i>');
}

/**
 * Valida el formulario y envía el resultado a POST /api/resultados.
 * Con sesión iniciada solo se envían los datos de la partida; los invitados
 * envían además nombre, grado y avatar.
 * @returns {void}
 */
function guardar_resultado_base_datos() {

    $('#nombre_jugador').removeClass('is-invalid');
    $('#grado_jugador').removeClass('is-invalid');

    var nombre_jugador = document.getElementById('nombre_jugador').value.trim();
    var grado_jugador = document.getElementById('grado_jugador').value;

    $('#aviso_guardar').hide();
    if (nombre_jugador == '') {
        $('#nombre_jugador').addClass('is-invalid');
        avisar_jugador('Escribe tu nombre para guardar el resultado.', 'warning');
        return;
    }

    if (grado_jugador == '') {
        $('#grado_jugador').addClass('is-invalid');
        avisar_jugador('Selecciona tu grado para guardar el resultado.', 'warning');
        return;
    }

    $('#btnGuardar').attr('disabled', true)
        .html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

    // Se espera a la primera sincronización de sesión para saber si hay usuario.
    ExploraApi.sesionLista.done(function () {
        var datos = {
            juego: nombre_juego_query,
            nivel: nivel_query,
            categoria: categoria_query,
            puntaje: Number(preguntas_correctas_query),
            tiempo: Number(tiempo_query)
        };

        var con_sesion = false;
        try {
            con_sesion = !!localStorage.getItem('usuario');
        } catch (e) { /* localStorage inaccesible: se trata como invitado */ }

        if (!con_sesion) {
            var avatar = ExploraApi.avatarValido(avatar_seleccionado);
            if (!avatar) {
                restaurar_boton_guardar();
                avisar_jugador('Selecciona un avatar válido.', 'warning');
                return;
            }
            datos.nombre = nombre_jugador;
            datos.grado = grado_jugador;
            datos.avatar = avatar + '.png';
        }

        ExploraApi.peticion('POST', 'resultados', datos)
            .done(function () {
                $('#btnGuardar').attr('disabled', true);
                $('#btnGuardar').html('<i class="fas fa-check-circle"></i> Guardado');
                $('#btnGuardar').addClass('btn-success');
                $('#btnGuardar').removeClass('btn-primary');

                $('#nombre_jugador').attr('disabled', true);
                $('#grado_jugador').attr('disabled', true);
                bloquear_avatares();

                setTimeout(() => {
                    consultar_ranking();
                }, 1000);
            })
            .fail(function (xhr) {
                restaurar_boton_guardar();
                var campos = xhr && xhr.status === 422 && xhr.responseJSON ? xhr.responseJSON.campos : null;
                if (Array.isArray(campos)) {
                    if (campos.indexOf('nombre') !== -1) { $('#nombre_jugador').addClass('is-invalid'); }
                    if (campos.indexOf('grado') !== -1) { $('#grado_jugador').addClass('is-invalid'); }
                }
                avisar_jugador(ExploraApi.mensajeError(xhr, 'No se pudo guardar el resultado.'), 'error');
            });
    });
}

/**
 * Desactiva la selección de avatar (usuario con sesión o resultado ya guardado).
 * @returns {void}
 */
function bloquear_avatares() {
    $('#div_avatar').css('pointer-events', 'none').css('opacity', '0.5');
    $('#div_avatar .avatar_container').attr({ tabindex: '-1', 'aria-disabled': 'true' });
}

/**
 * Pide el ranking de la combinación juego/nivel/categoría actual y lo pinta.
 * Si falla, muestra un aviso de texto en el contenedor.
 * @returns {void}
 */
function consultar_ranking() {
    ExploraApi.peticion('GET', 'ranking', {
        juego: nombre_juego_query,
        nivel: nivel_query,
        categoria: categoria_query
    })
        .done(function (filas) {
            mapear_ranking(filas);
        })
        .fail(function () {
            var contenedor = document.getElementById('ranking_container');
            if (contenedor) {
                contenedor.textContent = 'No se pudo cargar el ranking.';
            }
        });
}

var colores_ranking = ['#3d006b', '#3d006b', '#3d006b', '#9100ff', '#9100ff', '#9100ff'];

/**
 * Crea un elemento con clases y texto (siempre como texto literal).
 * @param {string} etiqueta Nombre de la etiqueta.
 * @param {string} [clases] Clases CSS separadas por espacios.
 * @param {string} [texto] Contenido de texto.
 * @returns {HTMLElement} Elemento creado.
 */
function crear_elemento(etiqueta, clases, texto) {
    var el = document.createElement(etiqueta);
    if (clases) { el.className = clases; }
    if (texto !== undefined) { el.textContent = texto; }
    return el;
}

/**
 * Pinta las filas del ranking. NINGÚN dato se inserta como HTML: jugador, grado
 * y avatar los escriben usuarios o invitados y se muestran como texto literal.
 * @param {Array<{jugador: string, avatar: string, grado: (string|number), puntaje: (number|string), tiempo: (number|string)}>} filas
 *        Respuesta de GET /api/ranking.
 * @returns {void}
 */
function mapear_ranking(filas) {
    var ranking_container = document.getElementById('ranking_container');
    if (!ranking_container) {
        return; // El modal ya se cerró cuando llegó la respuesta.
    }
    ranking_container.textContent = '';
    if (!Array.isArray(filas)) {
        return;
    }

    for (var i = 0; i < filas.length; i++) {
        var fila = filas[i] || {};
        var tiempo = Number(fila.tiempo) || 0;
        var tiempo_minutos = Math.floor(tiempo / 60);
        var tiempo_segundos = tiempo % 60;
        if (tiempo_segundos < 10) {
            tiempo_segundos = "0" + tiempo_segundos;
        }

        var ranking_item = crear_elemento('div', i < 3 ? 'ranking_item' : 'ranking_item_2');
        if (i < filas.length - 1) {
            ranking_item.style.marginBottom = "33px";
        }

        // Avatar (con coronita en los 3 primeros lugares).
        var contenedor_avatar = crear_elemento('div', 'ranking_item_avatar' + (i < 3 ? '' : ' clase_border_avatar'));
        if (i < 3) {
            var coronita = crear_elemento('img', 'item_coronita');
            coronita.src = '../../images/avatars/lugar' + (i + 1) + '.png';
            coronita.setAttribute('alt', 'Coronita');
            contenedor_avatar.appendChild(coronita);
        }
        var numero_avatar = ExploraApi.avatarValido(fila.avatar) || 1;
        var imagen = crear_elemento('img');
        imagen.src = '../../images/avatars/' + numero_avatar + '.png';
        imagen.setAttribute('alt', 'Avatar ' + numero_avatar);
        contenedor_avatar.appendChild(imagen);

        // Nombre acortado: primer nombre + segundo nombre/apellido.
        var nombre = String(fila.jugador || '').trim().split(/\s+/);
        var nombre_ranking = nombre[0];
        if (nombre.length == 2) {
            nombre_ranking += " " + nombre[1];
        } else if (nombre.length == 3 || nombre.length == 4) {
            nombre_ranking += " " + nombre[2];
        }

        var info = crear_elemento('div', 'info_ranking');

        var bloque_nombre = crear_elemento('div', 'ranking_item_nombre');
        var p_nombre = crear_elemento('p', '', nombre_ranking);
        p_nombre.style.marginBottom = "-3px";
        var p_grado = crear_elemento('p', '', String(fila.grado == null ? '' : fila.grado) + '° Grado');
        p_grado.style.marginBottom = "-3px";
        p_grado.style.fontSize = "0.7rem";
        bloque_nombre.appendChild(p_nombre);
        bloque_nombre.appendChild(p_grado);

        var bloque_puntos = crear_elemento('div', 'ranking_item_puntos');
        bloque_puntos.appendChild(crear_elemento('i', 'fas fa-star'));
        bloque_puntos.appendChild(document.createTextNode(' ' + String(fila.puntaje == null ? '' : fila.puntaje)));

        var bloque_tiempo = crear_elemento('div', 'ranking_item_tiempo');
        bloque_tiempo.appendChild(crear_elemento('i', 'fas fa-clock'));
        bloque_tiempo.appendChild(document.createTextNode(' ' + tiempo_minutos + ':' + tiempo_segundos + ' Mts'));

        info.appendChild(bloque_nombre);
        info.appendChild(bloque_puntos);
        info.appendChild(bloque_tiempo);

        ranking_item.appendChild(contenedor_avatar);
        ranking_item.appendChild(info);
        ranking_container.appendChild(ranking_item);
    }
}

/**
 * Quita la marca de error de un campo al corregirlo.
 * @param {string} id Id del campo (sin #).
 * @returns {void}
 */
function validar_guardar_resultado(id){
    $('#'+id).removeClass('is-invalid');
}

/**
 * Prepara el modal según haya o no sesión iniciada (caché en localStorage,
 * sincronizada con el servidor por ExploraApi). El servidor decide la
 * identidad real al guardar; aquí solo se rellena el formulario.
 * @returns {void}
 */
function verificar_usuario_logueado(){
    var usuario = null;
    try {
        usuario = localStorage.getItem('usuario');
    } catch (e) { /* sin localStorage: se trata como invitado */ }

    if(usuario){
        $('#nombre_jugador').val(localStorage.getItem('nombre')).attr('disabled', true);
        $('#grado_jugador').val(localStorage.getItem('grado')).attr('disabled', true);
        bloquear_avatares();
        seleccionar_avatar(ExploraApi.avatarValido(localStorage.getItem('avatar')) || 1);
    }else{
        seleccionar_avatar(1);
    }
}
