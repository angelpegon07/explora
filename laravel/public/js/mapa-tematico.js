/**
 * JS compartido por las páginas de mapas temáticos (climatico, coordilleras, etnias, parques_naturales, vertientes).
 *
 * Antes cada HTML llevaba copias casi idénticas de este código en un <script> inline (Fase 10 las unifica).
 *
 * Es un script CLÁSICO (sin módulos) que necesita jQuery y Bootstrap ya cargados y que se incluye DESPUÉS del
 * objeto de configuración `window.EXPLORA_MAPA`, que imprime la vista Blade `mapas/tematico.blade.php` con @json:
 *
 *   {
 *     slug:            clave del mapa (p. ej. 'climatico'),
 *     json:            URL absoluta del archivo de datos de Mapplic,
 *     carpetaImagenes: URL base de las imágenes del carrusel; «{carpeta}» se sustituye por el atributo `carpeta`
 *                      del lugar (etnias y parques naturales),
 *     variante:        valor de la opción `variante` de Mapplic, o null (solo vertientes: 'vertientes'),
 *     datoInteres:     true si la página tiene el panel #dato_interes (solo climatico),
 *     ocultarNiveles:  true para ocultar el selector de niveles a los 800 ms (solo vertientes)
 *   }
 *
 * Las funciones son GLOBALES a propósito: el HTML las invoca con atributos `onclick="..."`.
 * Los datos de la configuración nunca se concatenan en HTML como texto: solo se usan como valores de atributos
 * (`.attr()`), que el navegador no interpreta como marcado.
 */

/** Configuración de la página (la imprime la vista). Objeto vacío si falta, para fallar con un error claro más abajo. */
var EXPLORA_MAPA_CFG = window.EXPLORA_MAPA || {};

// ---------------------------------------------------------------------------------------------------------------
// Vertientes: Mapplic crea el selector de niveles de forma asíncrona; a los 800 ms se oculta (comportamiento original).
// ---------------------------------------------------------------------------------------------------------------
if (EXPLORA_MAPA_CFG.ocultarNiveles) {
    setTimeout(() => {
        document.querySelector('.mapplic-levels').style.display = 'none';
    }, 800);
}

// ---------------------------------------------------------------------------------------------------------------
// Acordeón del panel «CONTENIDO»: cada botón `.accordion` despliega/pliega el `.panel` que le sigue.
// ---------------------------------------------------------------------------------------------------------------
var acc = document.getElementsByClassName("accordion");
var i;

for (i = 0; i < acc.length; i++) {
    acc[i].addEventListener("click", function () {
        this.classList.toggle("active_acordion");
        var panel = this.nextElementSibling;
        if (panel.style.maxHeight) {
            panel.style.maxHeight = null;
        } else {
            panel.style.maxHeight = (panel.scrollHeight + 20) + "px";
        }
    });
}

// ---------------------------------------------------------------------------------------------------------------
// Modal de pantalla completa
// ---------------------------------------------------------------------------------------------------------------

/** Muestra el modal que sugiere activar la pantalla completa (#myModal). */
function mostrarModal(params) {
    $('#myModal').modal("show")
}

// Se abre al cargar la página, como antes.
mostrarModal();

/** Pide la pantalla completa al navegador (con los prefijos antiguos) y cierra el modal. */
function pantallaCompleta() {
    if (document.documentElement.requestFullscreen) {
        document.documentElement.requestFullscreen();
    } else if (document.documentElement.mozRequestFullScreen) {
        document.documentElement.mozRequestFullScreen();
    } else if (document.documentElement.webkitRequestFullscreen) {
        document.documentElement.webkitRequestFullscreen();
    } else if (document.documentElement.msRequestFullscreen) {
        document.documentElement.msRequestFullscreen();
    }

    $('#myModal').modal("hide")
}

/**
 * Panel «Dato de interés» (solo climatico): lo desliza dentro de la pantalla (right: 20%) o lo esconde
 * (right: -800px). Si la página no lo tiene (#dato_interes ausente) no hace nada.
 */
function mostrarCerrarDatoInteres() {
    var datoInteres = document.getElementById("dato_interes");
    if (!datoInteres) return;
    if (datoInteres.style.right == "20%") {
        datoInteres.style.right = "-800px";
    } else {
        datoInteres.style.right = "20%";
    }
}

// ---------------------------------------------------------------------------------------------------------------
// Mapplic. Las opciones son EXACTAMENTE las de los HTML originales (también startScale/minZoom/maxZoom, que pueden
// no tener efecto en esta versión de Mapplic: no se «arreglan» aquí). Solo cambian `source` y `variante`.
// ---------------------------------------------------------------------------------------------------------------
$(document).ready(function () {
    var opcionesMapplic = {};
    // `variante` va primero, como en el HTML original de vertientes.
    if (EXPLORA_MAPA_CFG.variante) {
        opcionesMapplic.variante = EXPLORA_MAPA_CFG.variante;
    }
    opcionesMapplic.source = EXPLORA_MAPA_CFG.json;
    Object.assign(opcionesMapplic, {
        mapfill: true,
        height: 560,
        animate: true,
        sidebar: true,
        search: true,
        minimap: false,
        locations: true,
        deeplinking: true,
        fullscreen: true,
        hovertip: true,
        developer: true,
        maxscale: 20,
        minscale: 1.4,
        zoom: true,
        searchdescription: true,
        searcheverywhere: true,
        thumbholder: true,
        hovertipdesc: true,
        alphabetical: true,
        startScale: 1.4,
        minZoom: 1.4,
        maxZoom: 20,
    });
    $('#mapplic').mapplic(opcionesMapplic);

    $('.usage').click(function (e) {
        e.preventDefault();
        $('.editor-window').slideToggle(200);
    });
    $('.editor-window .window-mockup').click(function () {
        $('.editor-window').slideUp(200);
    });

    $("div.bhoechie-tab-menu>div.list-group>a").click(function (e) {
        e.preventDefault();
        $(this).siblings('a.active').removeClass("active");
        $(this).addClass("active");
        var index = $(this).index();
        $("div.bhoechie-tab>div.bhoechie-tab-content").removeClass("active");
        $("div.bhoechie-tab>div.bhoechie-tab-content").eq(index).addClass("active");
    });
});

// ---------------------------------------------------------------------------------------------------------------
// Visores de video e imágenes (los abre verMas.js al pulsar los botones de un lugar)
// ---------------------------------------------------------------------------------------------------------------

/** Abre el visor de video con el enlace del atributo `link-video` del botón pulsado. */
function verVideo(elemento) {
    var link = elemento.getAttribute("link-video");
    document.getElementById("video_punto").setAttribute("src", link);

    $('#contenedorVideo').css('display', 'block');
    setTimeout(() => {
        $('#contenedorVideo').css('opacity', '1');
    }, 50)
}

/**
 * Carpeta base (con «/» final) de las imágenes del carrusel de un lugar.
 * En etnias y parques la plantilla lleva «{carpeta}», que se sustituye por el atributo `carpeta` del botón.
 */
function carpetaDeImagenes(elemento) {
    var base = String(EXPLORA_MAPA_CFG.carpetaImagenes || '');
    // asset() quita la barra final y puede codificar las llaves: se normaliza antes de sustituir.
    base = base.replace(/%7Bcarpeta%7D/i, '{carpeta}');
    if (base.indexOf('{carpeta}') !== -1) {
        base = base.replace('{carpeta}', elemento.getAttribute("carpeta"));
    }
    return base.replace(/\/*$/, '/');
}

/** Abre el carrusel con las imágenes (lista separada por comas en el atributo `link-imagenes`). */
function verImagenes(elemento) {
    var link = elemento.getAttribute("link-imagenes");
    var linksArray = link.split(',');
    var carpeta = carpetaDeImagenes(elemento);

    $('#carouselExampleIndicators').empty();

    for (var i = 0; i < linksArray.length; i++) {
        var carouselItem = $('<div class="carousel-item"></div>');
        if (i === 0) {
            carouselItem.addClass('active');
        }
        var img = $('<img class="d-block imagen-carrusel">');
        img.attr('src', carpeta + linksArray[i]);
        carouselItem.append(img);
        $('#carouselExampleIndicators').append(carouselItem);
    }

    $('#contenedorImagenes').css('display', 'block');
    setTimeout(() => {
        $('#contenedorImagenes').css('opacity', '1');
    }, 50)
}

/** Cierra el carrusel con un fundido de 0,5 s. */
function cerrarImagenes() {
    $('#contenedorImagenes').css('opacity', '0');
    setTimeout(() => {
        $('#contenedorImagenes').css('display', 'none');
    }, 500)
}

/** Cierra el visor de video con un fundido de 0,5 s. */
function cerrarVideo() {
    $('#contenedorVideo').css('opacity', '0');
    setTimeout(() => {
        $('#contenedorVideo').css('display', 'none');
    }, 500)
}

// ---------------------------------------------------------------------------------------------------------------
// Menú de accesibilidad
// ---------------------------------------------------------------------------------------------------------------

/** Abre o cierra el menú de accesibilidad. */
function toggleMenuAccesibilidad() {
    const menu = document.getElementById('menuAccesibilidad');
    menu.classList.toggle('activo');
}

let tamanoTextoActual = 16; // Tamaño base del texto

/** Aumenta 3 px el tamaño del texto de la página. */
function aumentarFuente() {
    tamanoTextoActual += 3;
    const elementos = document.querySelectorAll('body h1, body h2, body h3, body h4, body h5, body h6, body p, body label, body input, body span, body strong, body li');
    elementos.forEach(elemento => {
        elemento.style.fontSize = `${tamanoTextoActual}px`;
    });
}

/** Disminuye 3 px el tamaño del texto de la página. */
function disminuirFuente() {
    tamanoTextoActual -= 3;
    const elementos = document.querySelectorAll('body h1, body h2, body h3, body h4, body h5, body h6, body p, body label, body input, body span, body strong, body li');
    elementos.forEach(elemento => {
        elemento.style.fontSize = `${tamanoTextoActual}px`;
    });
}

/** Activa o desactiva el modo dislexia (tipografía especial, definida en css/general.css). */
function toggleDislexia() {
    document.body.classList.toggle('modo-dislexia');
}
