/**
 * miPerfil/script.js
 * =============================================================================
 * Página «Mi perfil» (vista Blade `/perfil`, Fase 12): cambio de avatar e historial de partidas.
 * Depende de jQuery, Bootstrap, SweetAlert2 y js/explora-api.js (ExploraApi), cargados antes.
 *
 * El SERVIDOR es la fuente de verdad: los datos del usuario (nombre, login, grado, avatar) y la lista de
 * avatares ya vienen pintados en el HTML, y este script NO se carga si no hay sesión (la vista solo emite
 * el aviso «Debe iniciar sesión…»). Aquí solo queda lo interactivo. Si la sesión caduca mientras la página
 * está abierta (la API responde 401/419) se muestra ese mismo aviso con enlace a la portada (`/`).
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
 * Muestra el aviso «Debe iniciar sesión» (role="alert") con enlace a la portada (`/`) y
 * oculta el formulario, el historial y el menú. Es idempotente. Equivale al aviso que la
 * vista pinta en el servidor sin sesión (mismo texto y mismas clases): se usa cuando la sesión
 * caduca con la página ya abierta.
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
    enlace.href = '/';
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
    // Los datos ya están en el HTML (pintados por el servidor): solo se pide el historial y se activan las
    // opciones de avatar. Este script solo se carga con sesión, pero se comprueba por si se reutiliza.
    if (document.getElementById('historial-body')) {
        consultarHistorial();
    }
    activarOpcionesAvatar();
});

/**
 * Activa las opciones de avatar que la vista dejó dentro de #avatars-preview. Cada una es un elemento
 * con data-avatar, role="button" y tabindex="0", activable con clic, Enter o Espacio.
 * Un único manejador delegado (se reasigna sin acumular duplicados).
 * @returns {void}
 */
function activarOpcionesAvatar() {
    $('#avatars-preview').off('click.avatar keydown.avatar')
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
 * Abre el modal con las opciones de avatar (las pintó el servidor con `config('explora.avatares_max')`).
 * @returns {void}
 */
function mostrarModalAvatar() {
    $('#modal-avatar').modal('show');
}

/**
 * URL de la imagen de un avatar, tomada de la propia opción pintada por el servidor (así este script no
 * conoce rutas ni el número de avatares).
 * @param {number} id Número de avatar.
 * @returns {(string|undefined)} Valor del atributo src, o undefined si esa opción no existe.
 */
function urlAvatar(id) {
    return $('#avatar_' + id + ' img').attr('src');
}

/**
 * Registra el avatar elegido, actualiza la vista previa y cierra el modal.
 * (Aún no se guarda en el servidor: eso lo hace guardarCambios.)
 * @param {number} id Número de avatar (1..`avatares_max`).
 * @returns {void}
 */
function seleccionar_avatar(id) {
    var url = urlAvatar(id);
    if (url) {
        $('#avatar-preview').attr('src', url);
    }
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
            var url = urlAvatar(avatar);
            if (url) {
                $('#avatar-preview').attr('src', url);
            }
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
