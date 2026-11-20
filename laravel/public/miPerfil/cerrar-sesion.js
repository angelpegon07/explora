/**
 * miPerfil/cerrar-sesion.js
 * =============================================================================
 * Cierre de sesión de la página «Mi perfil» (enlace #link-cerrar-sesion).
 * Antes era un <script> inline al final de miPerfil.html; se movió a un archivo
 * estático al migrar la página a Blade (Fase 12).
 * Depende de SweetAlert2 y de js/explora-api.js (ExploraApi), cargados antes.
 * =============================================================================
 */

/**
 * Pide confirmación y cierra la sesión: DELETE /api/sesion y, pase lo que pase,
 * limpia la caché local (localStorage) y vuelve a la portada (`/`).
 * @returns {void}
 */
function cerrarSesion() {
    Swal.fire({
        title: '¿Estás seguro de cerrar sesión?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Si, cerrar sesión',
        cancelButtonText: 'No, cancelar'
    }).then(function (result) {
        if (result.isConfirmed) {
            // Se avisa al servidor y, pase lo que pase, se limpia la caché local.
            ExploraApi.peticion('DELETE', 'sesion').always(function () {
                ExploraApi.limpiarSesionLocal();
                window.location.href = '/';
            });
        }
    });
}
