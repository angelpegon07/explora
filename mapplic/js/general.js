function seleccionarTipoMapa(tipo) {
    console.log(tipo);
    if (tipo == 'general') {
        window.location.href = 'index.html';
    } else if (tipo == 'hidrico') {
        window.location.href = 'vertientes.html';
    } else if (tipo == 'coordilleras') {
        window.location.href = 'coordilleras.html';
    } else if (tipo == 'climatico') {
        window.location.href = 'climatico.html';
    } else if (tipo == 'parques_naturales') {
        window.location.href = 'parques_naturales.html';
    } else if (tipo == 'volcanes') {
        // El mapa de volcanes no existe todavía (antes redirigía a una página inexistente: FUN-10).
        if (window.Swal) Swal.fire({ icon: 'info', title: 'Próximamente', text: 'El mapa de volcanes estará disponible pronto.' });
    } else if (tipo == 'etnias') {
        window.location.href = 'etnias.html';
    }
}
