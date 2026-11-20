/**
 * iniciarSesion.js
 * =============================================================================
 * Inicio y cierre de sesión del sitio Explora, común a dos tipos de página:
 *   - Páginas raíz del mapa (index.html, climatico.html...): tienen el menú de
 *     accesibilidad `.opciones-accesibilidad`, donde se pintan los botones de sesión.
 *   - Barra de juegos (juegos/index.html): tiene los botones `#btn-perfil`,
 *     `#btn-cerrar` y `#btn-iniciar`, que se muestran u ocultan según la sesión.
 * El tipo de página se deduce del DOM (ya no se usa localStorage.tipo_modal): los
 * botones de la barra de juegos se manejan si existen y el menú de accesibilidad
 * solo en páginas que lo tienen.
 *
 * La sesión real vive en una cookie HttpOnly del servidor (API Laravel); la clave
 * localStorage.usuario es solo una caché de compatibilidad que mantiene
 * ExploraApi (js/explora-api.js, que debe cargarse ANTES que este archivo).
 * La contraseña nunca se guarda ni se muestra.
 * =============================================================================
 */

/** Avatar elegido en el selector de avatares (lo usa seleccionar_avatar). */
var avatar_seleccionado = 1;

/**
 * Indica si la página es una página raíz con menú de accesibilidad.
 * @returns {boolean} true si existe un elemento `.opciones-accesibilidad`.
 */
function esPaginaRaiz() {
    return $('.opciones-accesibilidad').length > 0;
}

/**
 * Refresca la interfaz de sesión según el tipo de página: pinta los botones del
 * menú de accesibilidad (raíz) y/o muestra los de la barra de juegos.
 * @returns {void}
 */
function refrescarInterfazSesion() {
    cargar_opciones_accesibilidad();
    verificarSesion();
}

$(document).ready(function () {
    refrescarInterfazSesion();
    // Cuando termina la primera sincronización con el servidor, se vuelve a pintar:
    // así un cierre de sesión en otra pestaña o una sesión caducada se reflejan.
    if (window.ExploraApi && window.ExploraApi.sesionLista) {
        window.ExploraApi.sesionLista.done(refrescarInterfazSesion);
    }
});

/**
 * Pinta en el menú de accesibilidad los botones de sesión: «Iniciar sesión» si no
 * hay sesión; «Mi Perfil» y «Cerrar sesión» si la hay. No hace nada si la página
 * no tiene `.opciones-accesibilidad`.
 * @returns {void}
 */
function cargar_opciones_accesibilidad() {
    var $contenedor = $('.opciones-accesibilidad');
    if ($contenedor.length === 0) {
        return;
    }
    var usuario = localStorage.getItem('usuario');
    $('#opciones_iniciar_sesion').remove();

    var div = '';
    if (!usuario) {
        div = "<div class='row' id='opciones_iniciar_sesion' style='margin: 0px;'>" +
            "<div class='col-12' style='padding-left: 10px; padding-right: 10px;'>" +
            "<button style='width: 100%; text-align: center; display: block;' onclick='mostrarModalIniciarSesion(1)' id='btn_iniciar_sesion' class='btn-opcion'>" +
            "<i class='fa fa-user'></i> Iniciar sesión" +
            "</button>" +
            "</div>" +
        "</div>";
    } else {
        div = "<div class='row' id='opciones_iniciar_sesion' style='margin: 0px;'>" +
            "<div class='col-6' style='padding-left: 10px; padding-right: 10px;'>" +
            "<button onclick='mostrarPerfil()' id='btn_perfil' class='btn-opcion'>" +
            "<i class='fa fa-user'></i>Mi Perfil" +
            "</button>" +
            "</div>" +
            "<div class='col-6' style='padding-left: 10px; padding-right: 10px;'>" +
            "<button onclick='cerrarSesion()' id='btn_cerrar_sesion' class='btn-opcion'>" +
            "<i class='fa fa-sign-out-alt'></i>Cerrar sesión" +
            "</button>" +
            "</div>" +
        "</div>";
    }

    $contenedor.first().append(div);
}

/**
 * Abre el modal de inicio de sesión (accesible: role="dialog", aria-modal y
 * título enlazado; Enter en los campos envía el formulario).
 * @param {number} [tipo] Se conserva solo por compatibilidad con los onclick
 *   existentes; el tipo de página se deduce del DOM y NO se guarda en localStorage.
 * @returns {void}
 */
function mostrarModalIniciarSesion(tipo) {
    if (esPaginaRaiz()) {
        // El menú de accesibilidad se cierra mientras el modal está abierto.
        toggleMenuAccesibilidadSeguro();
    }

    $('#modalLogin').remove();

    // Crear modal Bootstrap dinámicamente (el logo se resuelve respecto a la raíz del sitio).
    var logo = window.ExploraApi.escaparHtml(window.ExploraApi.raiz('img/logo.png'));
    var modalHTML = '<div class="modal fade" id="modalLogin" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="tituloModalLogin">' +
        '<div class="modal-dialog modal-dialog-centered">' +
            '<div class="modal-content">' +
            '<div class="modal-body">' +
                '<div style="text-align: center;">' +
                    '<img src="' + logo + '" alt="logo" style="width: 200px; height: auto;">' +
                    '<h3 id="tituloModalLogin" style="font-weight: bold; margin-top: 10px;">Iniciar Sesión</h3>' +
                '</div>' +
                '<br>' +
                '<form id="formLogin">' +
                    '<div class="mb-3">' +
                        '<label style="font-weight: bold;" for="usuario" class="form-label">Usuario</label>' +
                        '<input type="text" class="form-control" id="usuario" required placeholder="Usuario" autocomplete="username">' +
                    '</div>' +
                    '<div class="mb-3">' +
                        '<label style="font-weight: bold;" for="contrasena" class="form-label">Contraseña</label>' +
                        '<input type="password" class="form-control" id="contrasena" required placeholder="Contraseña" autocomplete="current-password">' +
                    '</div>' +
                    '<div style="display: flex; justify-content: center; gap: 10px;">' +
                        '<button style="width: 45%;" type="submit" id="btnEnviarLogin" class="btn btn-primary">Iniciar sesión <i class="fa fa-sign-in-alt" aria-hidden="true"></i></button>' +
                        '<button style="width: 45%;" type="button" onclick="cerrarModalIniciarSesion()" class="btn btn-danger">Cerrar <i class="fa fa-times" aria-hidden="true"></i></button>' +
                    '</div>' +
                '</form>' +
            '</div>' +
            '</div>' +
        '</div>' +
    '</div>';

    $('body').append(modalHTML);

    // Enter en cualquier campo dispara el envío nativo del formulario.
    $('#formLogin').on('submit', function (evento) {
        evento.preventDefault();
        iniciarSesion();
    });

    // Mostrar modal con Bootstrap
    var modal = new bootstrap.Modal(document.getElementById('modalLogin'));
    modal.show();
}

/**
 * Alterna el menú de accesibilidad si la página define toggleMenuAccesibilidad.
 * @returns {void}
 */
function toggleMenuAccesibilidadSeguro() {
    if (typeof toggleMenuAccesibilidad === 'function') {
        toggleMenuAccesibilidad();
    }
}

/**
 * Marca como activo el avatar elegido en el selector de avatares.
 * @param {number} id Número del avatar.
 * @returns {void}
 */
function seleccionar_avatar(id) {
    var avatar_seleccionado_container = document.querySelector('.avatar_container.active');
    if (avatar_seleccionado_container) {
        avatar_seleccionado_container.classList.remove('active');
    }

    var avatar_seleccionado_html = document.getElementById('avatar_' + id);
    if (avatar_seleccionado_html) {
        avatar_seleccionado_html.classList.add('active');
    }

    avatar_seleccionado = id;
}

/**
 * Cierra y elimina el modal de inicio de sesión; en páginas raíz vuelve a
 * alternar el menú de accesibilidad que se cerró al abrirlo.
 * @returns {void}
 */
function cerrarModalIniciarSesion() {
    var elemento = document.getElementById('modalLogin');
    if (elemento && window.bootstrap && bootstrap.Modal && bootstrap.Modal.getInstance) {
        var instancia = bootstrap.Modal.getInstance(elemento);
        if (instancia) {
            instancia.hide();
        }
    }
    $('#modalLogin').remove();
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css({ overflow: '', 'padding-right': '' });
    if (esPaginaRaiz()) {
        toggleMenuAccesibilidadSeguro();
    }
}

/**
 * Valida el formulario y envía POST /api/sesion. Durante el envío el botón queda
 * deshabilitado. Si va bien guarda la caché local, avisa y refresca la interfaz.
 * La contraseña solo viaja en la petición; no se guarda ni se muestra.
 * @returns {void}
 */
function iniciarSesion() {
    var usuario = $('#usuario').val();
    var contrasena = $('#contrasena').val();

    if (usuario === '' || contrasena === '') {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Por favor, ingrese su usuario y contraseña.',
            timer: 1500,
            showConfirmButton: false
        });
        return;
    }

    var $boton = $('#btnEnviarLogin');
    $boton.prop('disabled', true);

    ExploraApi.peticion('POST', 'sesion', { usuario: usuario, contrasena: contrasena })
        .done(function (respuesta) {
            ExploraApi.guardarSesionLocal(respuesta.usuario_objeto);

            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                text: 'Inicio de sesión exitoso.',
                timer: 1500,
                showConfirmButton: false
            });

            refrescarInterfazSesion();

            setTimeout(function () {
                cerrarModalIniciarSesion();
            }, 1500);
        })
        .fail(function (xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: ExploraApi.mensajeError(xhr, 'Error al iniciar sesión, por favor intente nuevamente.'),
                timer: 1500,
                showConfirmButton: false
            });
            $boton.prop('disabled', false);
        });
}

/**
 * Muestra u oculta los botones de la barra de juegos (#btn-perfil, #btn-cerrar,
 * #btn-iniciar) según la sesión. Tolera que alguno no exista (páginas raíz).
 * @returns {void}
 */
function verificarSesion() {
    var hayUsuario = !!localStorage.getItem('usuario');
    var visibilidad = {
        'btn-perfil': hayUsuario,
        'btn-cerrar': hayUsuario,
        'btn-iniciar': !hayUsuario
    };
    Object.keys(visibilidad).forEach(function (id) {
        var elemento = document.getElementById(id);
        if (elemento) {
            elemento.style.display = visibilidad[id] ? 'block' : 'none';
        }
    });
}

/**
 * Pide confirmación y cierra la sesión: DELETE /api/sesion y, pase lo que pase
 * (incluso si la petición falla), limpia la caché local y refresca la interfaz.
 * @returns {void}
 */
function cerrarSesion() {
    Swal.fire({
        icon: 'warning',
        title: '¿Estás seguro que quieres cerrar sesión?',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Si, salir',
        cancelButtonText: 'No, cancelar',
        allowOutsideClick: false,
    }).then(function (result) {
        if (result.isConfirmed) {
            ExploraApi.peticion('DELETE', 'sesion').always(function () {
                ExploraApi.limpiarSesionLocal();
                refrescarInterfazSesion();
            });
        }
    });
}

/**
 * Lleva a la página «Mi perfil» (ruta calculada desde la raíz del sitio).
 * @returns {void}
 */
function mostrarPerfil() {
    window.location.href = ExploraApi.raiz('miPerfil.html');
}
