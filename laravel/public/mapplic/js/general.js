/**
 * Navega al mapa elegido en el modal «SELECCIONE EL MAPA QUE DESEA EXPLORAR» (parcial `modal-tipo-mapa`).
 *
 * Desde la Fase 11 usa las URL de Laravel, absolutas y sin `.html` (`/`, `/climatico`, `/vertientes`…): así funcionan
 * desde cualquier ruta. Las URL `*.html` antiguas siguen redirigiendo (301) solo para marcadores viejos.
 *
 * @param {string} tipo Clave del mapa: general, hidrico, coordilleras, climatico, parques_naturales, etnias o volcanes.
 */
function seleccionarTipoMapa(tipo) {
    console.log(tipo);
    if (tipo == 'general') {
        window.location.href = '/';
    } else if (tipo == 'hidrico') {
        window.location.href = '/vertientes';
    } else if (tipo == 'coordilleras') {
        window.location.href = '/coordilleras';
    } else if (tipo == 'climatico') {
        window.location.href = '/climatico';
    } else if (tipo == 'parques_naturales') {
        window.location.href = '/parques_naturales';
    } else if (tipo == 'volcanes') {
        // El mapa de volcanes no existe todavía (antes redirigía a una página inexistente: FUN-10).
        if (window.Swal) Swal.fire({ icon: 'info', title: 'Próximamente', text: 'El mapa de volcanes estará disponible pronto.' });
    } else if (tipo == 'etnias') {
        window.location.href = '/etnias';
    }
}
