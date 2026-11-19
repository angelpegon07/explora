/**
 * verMas.js
 * =============================================================================
 * Ficha («Ver más») de un departamento: pide la ficha a la API Laravel y la pinta
 * en el modal #modal_sitios.
 *
 * Depende de jQuery, SweetAlert2 y js/explora-api.js (ExploraApi), cargado antes.
 *
 * SEGURIDAD: el contenido de las fichas (descripciones, historia, fauna, flora,
 * festividades, sitios, personajes, etc.) es HTML EDITORIAL administrado por el
 * equipo de contenidos y puede incluir etiquetas de formato, por eso se inserta
 * con .html(). En cambio, los `id` y `href` que se construyen con ids del
 * servidor pasan por idSeguro(), que solo admite enteros positivos, para que un
 * valor inesperado no pueda inyectar atributos.
 * =============================================================================
 */

/**
 * Convierte un id del servidor en un entero positivo seguro para usar en
 * atributos HTML (id/href).
 * @param {*} valor Id recibido de la API.
 * @returns {number} Entero positivo, o 0 si el valor no es válido.
 */
function idSeguro(valor) {
    var n = parseInt(valor, 10);
    return n > 0 ? n : 0;
}

/**
 * Abre la ficha del departamento indicado en el atributo `id-departamento`
 * del elemento. Muestra un diálogo de carga que se cierra SIEMPRE (éxito o fallo).
 * @param {HTMLElement} element Elemento con el atributo `id-departamento`.
 * @returns {void}
 */
function verMas(element){
    var id_departamento = $(element).attr("id-departamento");

    // Solo se aceptan enteros positivos: el id viaja en la ruta de la API.
    if (!/^[1-9][0-9]*$/.test(String(id_departamento))) {
        Swal.fire({
            icon: 'warning',
            title: 'Departamento no válido',
            text: 'No se pudo identificar el departamento seleccionado.'
        });
        return;
    }

    Swal.fire({
        position: "center",
        title: "Consultando información",
        text: "Por favor espere mientras se carga la información del departamento",
        allowOutsideClick: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });

    ExploraApi.peticion('GET', 'departamentos/' + encodeURIComponent(id_departamento) + '/ficha')
        .done(function (data) {
            Swal.close();
            mostrarDescripcion(data);
            $('#modal_sitios').modal('show');
        })
        .fail(function (xhr) {
            // Sin esto el diálogo de carga (allowOutsideClick:false) bloquearía la pantalla.
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'No se pudo cargar la información del departamento. ' + ExploraApi.mensajeError(xhr)
            });
        });
}

/**
 * Rellena el modal de la ficha con los datos de la API (HTML editorial: ver
 * nota de seguridad de la cabecera) y deja activa la primera pestaña.
 * @param {Object} data Ficha completa del departamento.
 * @returns {void}
 */
function mostrarDescripcion(data){
    $('#descCapital').html(data.nombreDepartamento.capital);
    $('#contenidoDepartameto').html(data.descripcion.contenido);
    $('#descPoblacion').html(data.descripcion.poblacion);
    $('#descClima').html(data.descripcion.clima);
    $('#descTemperatura').html(data.descripcion.temperatura);
    $('#descExtension').html(data.descripcion.extension);

    $('#bandera').html(data.descripcion.bandera);
    $('#escudo').html(data.descripcion.escudo);

    $('#nombreDepartamento').text('Departamento de ' + data.nombreDepartamento.nombre);

    // Cargar historia
    $('#desHistoria').html(data.historia.historia);

    //carga geografia
    if (data.geografia.ubicacion) {
        $('#desUbicacion').html(data.geografia.ubicacion);
        $('#contUbi').show();
    } else {
        $('#contUbi').hide();
    }

    if (data.geografia.coordenadas) {
        $('#desCoordenadas').html(data.geografia.coordenadas);
        $('#contCoord').show();
    } else {
        $('#contCoord').hide();
    }

    if (data.geografia.limites) {
        $('#desLimites').html(data.geografia.limites);
        $('#contLimi').show();
    } else {
        $('#contLimi').hide();
    }

    if (data.geografia.relieve) {
        $('#desRelieve').html(data.geografia.relieve);
        $('#contReli').show();
    } else {
        $('#contReli').hide();
    }

    //carga hidrografica
    $('#desHidrografia').html(data.hidrografia.hidrografia);

    //carga clima
    $('#desClima').html(data.clima.clima);

    //carga demografia
    if (data.demografia.poblacion) {
        $('#desPoblacion').html(data.demografia.poblacion);
    }

    //cargar etnografia
    $('#desEtnografia').html(data.etnografia.contenido);

    let navEtnias = '';
    let active = 'active show';

    $.each(data.etnografia.lista, function(index, etnia) {
        navEtnias += `<li class="nav-item">
                        <a style="border: 2px solid #62aeff; font-size: 11pt;  height: 100%; display: flex; align-items: center; justify-content: center;" href="#etnia${idSeguro(etnia.id)}" data-toggle="pill" aria-selected="false"
                            class="nav-link rounded-0 ${active}">
                            <span class="d-none d-md-block">${etnia.nombre}</span>
                        </a>
                    </li>`;
        active = '';
    });

    active = 'active show';
    let contNavEtnias = '';

    $.each(data.etnografia.lista, function(index, etnia) {
        contNavEtnias += `<div class="tab-pane fade ${active}" id="etnia${idSeguro(etnia.id)}">
                            ${etnia.contenido}
                        </div>`;
        active = '';
    });

    $('#navEtnias').html(navEtnias);
    $('#contNavEtnias').html(contNavEtnias);

    //cargar fauna
    $('#desFauna').html(data.faunaFlora.fauna);
    
    let navFauna = '';
    let active_fauna = 'active show';
    
    $.each(data.faunaFlora.lista_fauna, function(index, fauna) {
        navFauna += `<li class="nav-item">
                        <a style="border: 2px solid #62aeff; font-size: 11pt;  height: 100%; display: flex; align-items: center; justify-content: center;" href="#fauna${idSeguro(fauna.id)}" data-toggle="tab" aria-expanded="false"
                            class="nav-link ${active_fauna}">
                            <span class="d-none d-md-block">${fauna.nombre}</span>
                        </a>
                    </li>`;
        active_fauna = '';
    });
    
    active_fauna = 'active show';
    let contNavFauna = '';
    
    $.each(data.faunaFlora.lista_fauna, function(index, fauna) {
        contNavFauna += `<div class="tab-pane fade ${active_fauna}" id="fauna${idSeguro(fauna.id)}">
                            ${fauna.contenido}
                        </div>`;
        active_fauna = '';
    });
    
    $('#navFauna').html(navFauna);
    $('#contNavFauna').html(contNavFauna);
    
    //cargar flora
    $('#desFlora').html(data.faunaFlora.flora);
    
    let navFlora = '';
    let active_flora = 'active show';
    
    $.each(data.faunaFlora.lista_flora, function(index, flora) {
        navFlora += `<li class="nav-item">
                        <a style="border: 2px solid #62aeff; font-size: 11pt; height: 100%; display: flex; align-items: center; justify-content: center;" href="#flora${idSeguro(flora.id)}" data-toggle="tab" aria-expanded="false"
                            class="nav-link ${active_flora}">
                            <span class="d-none d-md-block">${flora.nombre}</span>
                        </a>
                    </li>`;
        active_flora = '';
    });
    
    active_flora = 'active show';
    let contNavFlora = '';
    
    $.each(data.faunaFlora.lista_flora, function(index, flora) {
        contNavFlora += `<div class="tab-pane fade ${active_flora}" id="flora${idSeguro(flora.id)}">
                            ${flora.contenido}
                        </div>`;
        active_flora = '';
    });
    
    $('#navFlora').html(navFlora);
    $('#contNavFlora').html(contNavFlora);

    //cargar economia
    $('#desEconomia').html(data.economia.economia);

    //cargar cultura
    if (data.cultura.descripcion) {
        $('#desCultura').html(data.cultura.descripcion);
        $('#contCultura').show();
    } else {
        $('#contCultura').hide();
    }

    if (data.cultura.arte) {
        $('#desArte').html(data.cultura.arte);
        $('#contArte').show();
    } else {
        $('#contArte').hide();
    }

    if (data.cultura.danza) {
        $('#desDanza').html(data.cultura.danza);
        $('#contDanza').show();
    } else {
        $('#contDanza').hide();
    }

    if (data.cultura.musica) {
        $('#desMusica').html(data.cultura.musica);
        $('#contMusica').show();
    } else {
        $('#contMusica').hide();
    }

    //cargar gastronomia
    $('#desGastronomia').html(data.gastronomia.gastronomia);

    // Generación dinámica de pestañas y festividades
    let navFestividades = '';
    let active_festividades = 'active show';
    
    $.each(data.festividades, function(index, festividad) {
        navFestividades += `<li class="nav-item">
                        <a style="border: 2px solid #62aeff; font-size: 11pt; height: 100%; display: flex; align-items: center; justify-content: center;" href="#festividad${idSeguro(festividad.id)}" data-toggle="tab" aria-expanded="false"
                            class="nav-link ${active_festividades}">
                            <span class="d-none d-md-block">${festividad.nombre}</span>
                        </a>
                    </li>`;
        active_festividades = '';
    });
    
    active_festividades = 'active show';
    let contNavFestividades = '';
    
    $.each(data.festividades, function(index, festividad) {
        contNavFestividades += `<div class="tab-pane fade ${active_festividades}" id="festividad${idSeguro(festividad.id)}">
                            ${festividad.contenido}
                        </div>`;
        active_festividades = '';
    });
    
    $('#navFestividades').html(navFestividades);
    $('#contNavFestividades').html(contNavFestividades);

    // Generación dinámica de pestañas sitios de interes
    let navSitios = '';
    let active_sitios = 'active show';
    
    $.each(data.sitios, function(index, sitio) {
        navSitios += `<li class="nav-item">
                        <a style="border: 2px solid #62aeff; font-size: 11pt;  height: 100%; display: flex; align-items: center; justify-content: center;" href="#sitio${idSeguro(sitio.id)}" data-toggle="tab" aria-expanded="false"
                            class="nav-link ${active_sitios}">
                            <span class="d-none d-md-block">${sitio.nombre}</span>
                        </a>
                    </li>`;
        active_sitios = '';
    });
    
    active_sitios = 'active show';
    let contNavSitios = '';
    
    $.each(data.sitios, function(index, sitio) {
        contNavSitios += `<div class="tab-pane fade ${active_sitios}" id="sitio${idSeguro(sitio.id)}">
                            ${sitio.contenido}
                        </div>`;
        active_sitios = '';
    });
    
    $('#navSitios').html(navSitios);
    $('#contNavSitios').html(contNavSitios);

    // Generación dinámica de pestañas personajes
    let navPersonajes = '';
    let active_personajes = 'active show';
    
    $.each(data.personajes, function(index, personaje) {
        navPersonajes += `<li class="nav-item">
                        <a style="border: 2px solid #62aeff; font-size: 11pt;  height: 100%; display: flex; align-items: center; justify-content: center;" href="#persona${idSeguro(personaje.id)}" data-toggle="tab" aria-expanded="false"
                            class="nav-link ${active_personajes}">
                            <span class="d-none d-md-block">${personaje.nombre}</span>
                        </a>
                    </li>`;
        active_personajes = '';
    });
    
    active_personajes = 'active show';
    let contNavPersonajes = '';
    
    $.each(data.personajes, function(index, personaje) {
        contNavPersonajes += `<div class="tab-pane fade ${active_personajes}" id="persona${idSeguro(personaje.id)}">
                            ${personaje.contenido}
                        </div>`;
        active_personajes = '';
    });
    
    $('#navPersonajes').html(navPersonajes);
    $('#contNavPersonajes').html(contNavPersonajes);

    //cargar capital
    $('#desCapital').html(data.capital.capital);

    quitarActive();
    
    // Mostrar el contenido una vez que todo esté mapeado
    $('#modal-content-info').fadeIn();
}

/**
 * Quita la clase `active` de todos los elementos de las listas de pestañas de la ficha (`.list-group-item`),
 * para que al cambiar de sección solo quede marcada la elegida.
 * @returns {void}
 */
function quitarActive(){
    const listItems = document.querySelectorAll('.list-group-item');

    listItems.forEach(item => {
        item.classList.remove('active');
    });

    if (listItems.length > 0) {
        listItems[0].classList.add('active');
    }


    const listItems2 = document.querySelectorAll('.bhoechie-tab-content');

    listItems2.forEach(item => {
        item.classList.remove('active');
    });

    if (listItems2.length > 0) {
        listItems2[0].classList.add('active');
    }
}