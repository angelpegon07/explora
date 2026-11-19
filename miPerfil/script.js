/**
 * miPerfil/script.js
 * =============================================================================
 * Página «Mi perfil»: datos del usuario, cambio de avatar e historial de partidas.
 * Depende de jQuery, SweetAlert2 y js/explora-api.js (ExploraApi), cargados antes.
 *
 * La sesión se confirma contra el servidor con ExploraApi.sesionLista; si no hay
 * sesión se muestra un aviso con enlace a index.html y no se consulta el historial.
 * Los datos del historial son escritos por usuarios: se pintan SIEMPRE con
 * textContent (nunca como HTML).
 * =============================================================================
 */

/** Avatar elegido en el modal (undefined = aún no se ha elegido ninguno). */
var avatar_seleccionado;

/**
 * Muestra la sección indicada («datos» o «historial») y marca su enlace del menú.
 * @param {string} id Identificador de la sección a mostrar.
 * @param {HTMLElement} link Enlace del menú pulsado.
 * @returns {void}
 */
function mostrarSeccion(id, link) {
    document.getElementById('datos').style.display = (id === 'datos') ? 'block' : 'none';
    document.getElementById('historial').style.display = (id === 'historial') ? 'block' : 'none';

    var links = document.querySelectorAll('#menu-perfil .nav-link');
    links.forEach(function (el) {
        el.classList.remove('active');
    });
    link.classList.add('active');
}

/**
 * Pinta los datos del usuario en el formulario.
 * @param {{nombre_usuario?: string, login_usuario?: string, grado_usuario?: (string|number),
 *          avatar_explora?: (string|number)}} usuario Objeto `usuario_objeto` de la API.
 * @returns {void}
 */
function verUsuario(usuario) {
    document.getElementById('usuario').value = usuario.login_usuario || '';
    document.getElementById('nombre').value = usuario.nombre_usuario || '';
    document.getElementById('grado').value = String(usuario.grado_usuario == null ? '' : usuario.grado_usuario);
    // Se valida el avatar (1..35); si no es válido se usa el 1.
    var avatar = ExploraApi.avatarValido(usuario.avatar_explora) || 1;
    document.getElementById('avatar-preview').src = 'juegos/images/avatars/' + avatar + '.png';
}

/**
 * Muestra el aviso «Debe iniciar sesión» (role="alert") con enlace a index.html y
 * oculta el formulario, el historial y el menú. Es idempotente.
 * @returns {void}
 */
function mostrarAvisoSinSesion() {
    // Se vacía la tabla: «Aún no hay partidas» junto al aviso de sesión caducada hacía creer que no había historial.
    var tabla = document.getElementById('historial-body');
    if (tabla) { tabla.textContent = ''; }
    if (document.getElementById('aviso-sesion')) {
        return;
    }
    var aviso = document.createElement('div');
    aviso.id = 'aviso-sesion';
    aviso.className = 'alert alert-warning text-center';
    aviso.setAttribute('role', 'alert');
    aviso.appendChild(document.createTextNode('Debe iniciar sesión para ver su perfil. '));
    var enlace = document.createElement('a');
    enlace.href = 'index.html';
    enlace.textContent = 'Ir al inicio';
    aviso.appendChild(enlace);

    var contenido = document.querySelector('.col-md-9');
    contenido.insertBefore(aviso, contenido.firstChild);

    ['datos', 'historial', 'menu-perfil'].forEach(function (id) {
        var elemento = document.getElementById(id);
        if (elemento) {
            elemento.style.display = 'none';
        }
    });
}

$(document).ready(function () {
    ExploraApi.sesionLista.done(function (usuario) {
        if (usuario) {
            verUsuario(usuario);
            consultarHistorial();
        } else {
            mostrarAvisoSinSesion();
        }
    });
});

/**
 * Abre el modal de avatares y genera las 35 opciones. Cada opción es un elemento
 * con data-avatar, role="button" y tabindex="0", activable con clic, Enter o Espacio.
 * @returns {void}
 */
function mostrarModalAvatar() {
    $('#modal-avatar').modal('show');

    var $contenedor = $('#avatars-preview').empty();
    for (var i = 1; i <= 35; i++) {
        var $opcion = $('<div class="avatar_container" role="button" tabindex="0"></div>')
            .attr({ id: 'avatar_' + i, 'data-avatar': i, 'aria-label': 'Avatar ' + i })
            .append($('<img class="img-fluid">').attr({ src: 'juegos/images/avatars/' + i + '.png', alt: '' }));
        $('<div class="col-lg-1" style="padding: 10px;"></div>').append($opcion).appendTo($contenedor);
    }

    // Un único manejador delegado (se reasigna sin acumular duplicados).
    $contenedor.off('click.avatar keydown.avatar')
        .on('click.avatar', '[data-avatar]', function () {
            seleccionar_avatar(Number($(this).attr('data-avatar')));
        })
        .on('keydown.avatar', '[data-avatar]', function (evento) {
            if (evento.key === 'Enter' || evento.key === ' ' || evento.key === 'Spacebar') {
                evento.preventDefault();
                seleccionar_avatar(Number($(this).attr('data-avatar')));
            }
        });
}

/**
 * Registra el avatar elegido, actualiza la vista previa y cierra el modal.
 * (Aún no se guarda en el servidor: eso lo hace guardarCambios.)
 * @param {number} id Número de avatar (1..35).
 * @returns {void}
 */
function seleccionar_avatar(id) {
    $('#avatar-preview').attr('src', 'juegos/images/avatars/' + id + '.png');
    cerrarModalAvatar();
    avatar_seleccionado = id;
}

/**
 * Cierra el modal de avatares de forma fiable.
 *
 * Bootstrap 4 IGNORA `hide()` mientras el modal todavía se está abriendo (transición de ~300 ms): si la persona elige
 * un avatar enseguida (por ejemplo con el teclado), el modal quedaba abierto encima del botón «Guardar». Aquí se pide
 * el cierre y, además, se deja un oyente de una sola vez para repetirlo cuando termine de abrirse. Ambos oyentes
 * llevan un espacio de nombres y se retiran al cerrarse, para que NO se cierre solo la próxima vez que se abra.
 * @returns {void}
 */
function cerrarModalAvatar() {
    var $modal = $('#modal-avatar');
    $modal.off('.cerrarAvatar')
        .one('shown.bs.modal.cerrarAvatar', function () { $modal.modal('hide'); })
        .one('hidden.bs.modal.cerrarAvatar', function () { $modal.off('.cerrarAvatar'); });
    $modal.modal('hide');
}

/**
 * Crea una celda <td> con texto literal (sin interpretar HTML).
 * @param {(string|number)} texto Contenido de la celda.
 * @param {string} [clase] Clase de un icono Font Awesome opcional que antecede al texto.
 * @returns {HTMLTableCellElement} Celda creada.
 */
function crearCelda(texto, clase) {
    var td = document.createElement('td');
    if (clase) {
        var icono = document.createElement('i');
        icono.className = clase;
        td.appendChild(icono);
        td.appendChild(document.createTextNode(' '));
    }
    td.appendChild(document.createTextNode(String(texto)));
    return td;
}

/**
 * Pinta el historial en la tabla #historial-body usando solo textContent/nodos de
 * texto (nombre_juego, categoria y nivel son datos escritos por usuarios).
 * @param {Array<{nombre_juego: string, categoria: string, nivel: string, puntaje: number,
 *          tiempo: number, fecha: string}>} historial Partidas devueltas por la API.
 * @returns {void}
 */
function cargarHistorial(historial) {
    var cuerpo = document.getElementById('historial-body');
    cuerpo.textContent = '';

    if (!historial || historial.length === 0) {
        var vacia = document.createElement('tr');
        var celda = crearCelda('Aún no hay partidas registradas');
        celda.colSpan = 7;
        celda.className = 'text-center';
        vacia.appendChild(celda);
        cuerpo.appendChild(vacia);
        return;
    }

    historial.forEach(function (item) {
        var partes = String(item.fecha || '').split(' ');
        var fila = document.createElement('tr');
        fila.appendChild(crearCelda(item.nombre_juego));
        fila.appendChild(crearCelda(item.categoria));
        fila.appendChild(crearCelda(item.nivel));
        fila.appendChild(crearCelda(item.puntaje + ' Pts', 'fas fa-star'));
        fila.appendChild(crearCelda(segundos_a_tiempo(item.tiempo), 'fas fa-clock'));
        fila.appendChild(crearCelda(partes[0] || ''));
        fila.appendChild(crearCelda(formatearHoraDoce(partes[1])));
        cuerpo.appendChild(fila);
    });
}

/**
 * Convierte segundos a «minutos:segundos Mts».
 * @param {number} segundos Duración en segundos.
 * @returns {string} Texto de duración.
 */
function segundos_a_tiempo(segundos) {
    segundos = Number(segundos) || 0;
    var minutos = Math.floor(segundos / 60);
    var segundos_restantes = segundos % 60;
    return minutos + ':' + segundos_restantes + ' Mts';
}

/**
 * Convierte una hora «HH:MM:SS» (24 h) a formato de 12 horas con AM/PM.
 * @param {string} [hora] Hora en 24 h; si falta o es inválida devuelve cadena vacía.
 * @returns {string} Hora en formato 12 h.
 */
function formatearHoraDoce(hora) {
    if (!hora) {
        return '';
    }
    var hora_array = hora.split(':');
    var hora_doce = hora_array[0] > 12 ? hora_array[0] - 12 : hora_array[0];
    var am_pm = hora_array[0] >= 12 ? 'PM' : 'AM';
    return hora_doce + ':' + hora_array[1] + ' ' + am_pm;
}

/**
 * Consulta GET /api/historial (la API identifica al usuario por su sesión; no se
 * envía ningún id). Si la sesión caducó muestra el aviso de iniciar sesión.
 * @returns {void}
 */
function consultarHistorial() {
    ExploraApi.peticion('GET', 'historial', null, { cache: false })
        .done(function (respuesta) {
            cargarHistorial(respuesta);
        })
        .fail(function (xhr) {
            if (ExploraApi.esSesionCaducada(xhr)) {
                mostrarAvisoSinSesion();
                return;
            }
            var cuerpo = document.getElementById('historial-body');
            cuerpo.textContent = '';
            var fila = document.createElement('tr');
            var celda = crearCelda(ExploraApi.mensajeError(xhr, 'No se pudo cargar el historial.'));
            celda.colSpan = 7;
            celda.className = 'text-center';
            fila.appendChild(celda);
            cuerpo.appendChild(fila);
        });
}

/**
 * Guarda el avatar elegido con PATCH /api/perfil/avatar. El botón se deshabilita
 * durante el envío. Si la sesión caducó (401/419) se avisa de ello.
 * @returns {void}
 */
function guardarCambios() {
    if (!avatar_seleccionado) {
        Swal.fire({
            icon: 'info',
            title: 'Elija un avatar',
            text: 'Seleccione un avatar con «Cambiar Avatar» antes de guardar.'
        });
        return;
    }

    var $boton = $('#btn-guardar').prop('disabled', true);
    ExploraApi.peticion('PATCH', 'perfil/avatar', { avatar: Number(avatar_seleccionado) })
        .done(function (respuesta) {
            // La caché local se actualiza de inmediato, sin esperar al aviso.
            var avatar = ExploraApi.avatarValido(respuesta && respuesta.avatar) || avatar_seleccionado;
            try {
                localStorage.setItem('avatar', String(avatar));
            } catch (e) { /* almacenamiento no disponible: la sesión real está en el servidor */ }
            $('#avatar-preview').attr('src', 'juegos/images/avatars/' + avatar + '.png');
            Swal.fire({
                title: 'Éxito',
                icon: 'success',
                text: (respuesta && respuesta.message) || 'Avatar actualizado.',
                showConfirmButton: false,
                timer: 1500
            });
        })
        .fail(function (xhr) {
            Swal.fire({
                title: 'Error',
                icon: 'error',
                text: ExploraApi.esSesionCaducada(xhr)
                    ? 'Su sesión caducó. Vuelva a iniciar sesión.'
                    : ExploraApi.mensajeError(xhr, 'No se pudo guardar el avatar.')
            });
        })
        .always(function () {
            $boton.prop('disabled', false);
        });
}
