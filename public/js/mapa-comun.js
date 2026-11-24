/**
 * JS COMÚN de la portada (`inicio`) y de los mapas temáticos (climatico, coordilleras, etnias, parques_naturales,
 * vertientes). Fase 11: antes cada página llevaba su copia casi idéntica en un <script> inline (en la Fase 10 las de los
 * mapas pasaron a mapa-tematico.js; ahora lo común vive aquí y cada página conserva solo lo suyo).
 *
 * Contiene: acordeón del panel «CONTENIDO», modal de pantalla completa, pestañas de la ficha, visores de video e
 * imágenes y menú de accesibilidad.
 *
 * Es un script CLÁSICO (sin módulos) que necesita jQuery y Bootstrap ya cargados y que se incluye DESPUÉS del objeto de
 * configuración `window.EXPLORA_MAPA`, que imprime la vista Blade con @json:
 *
 *   {
 *     slug:            clave de la página (p. ej. 'climatico' o 'inicio'),
 *     json:            URL absoluta del archivo de datos de Mapplic,
 *     carpetaImagenes: URL base de las imágenes del carrusel; «{carpeta}» se sustituye por el atributo `carpeta`
 *                      del lugar (etnias y parques naturales),
 *     variante:        valor de la opción `variante` de Mapplic, o null (solo vertientes: 'vertientes'),
 *     datoInteres:     true si la página tiene el panel #dato_interes (solo climatico),
 *     ocultarNiveles:  true para ocultar el selector de niveles a los 800 ms (vertientes y la portada)
 *   }
 *
 * Las funciones son GLOBALES a propósito: el HTML las invoca con atributos `onclick="..."`.
 * Los datos de la configuración nunca se concatenan en HTML como texto: solo se usan como valores de atributos
 * (`.attr()`), que el navegador no interpreta como marcado.
 */

/** Configuración de la página (la imprime la vista). Objeto vacío si falta, para fallar con un error claro más abajo. */
var EXPLORA_MAPA_CFG = window.EXPLORA_MAPA || {};

// ---------------------------------------------------------------------------------------------------------------
// Mapplic crea el selector de niveles de forma asíncrona; a los 800 ms se oculta (comportamiento original de
// vertientes y de la portada). Si el selector no existe, no hace nada.
// ---------------------------------------------------------------------------------------------------------------
if (EXPLORA_MAPA_CFG.ocultarNiveles) {
    setTimeout(() => {
        const niveles = document.querySelector('.mapplic-levels');
        if (niveles) niveles.style.display = 'none';
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

// ---------------------------------------------------------------------------------------------------------------
// Controles comunes que se enlazan tras iniciar Mapplic
// ---------------------------------------------------------------------------------------------------------------

/**
 * Enlaza los controles comunes del mapa: la ventana de ejemplo `.editor-window` (resto del demo de Mapplic) y las
 * pestañas del menú de la ficha de departamento (`.bhoechie-tab-*`).
 *
 * Cada página la llama desde su `$(document).ready` justo DESPUÉS de `$('#mapplic').mapplic(...)`, el mismo orden que
 * tenían los scripts inline originales.
 */
function enlazarControlesMapa() {
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
}

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

/**
 * Abre o cierra el menú de accesibilidad y mantiene `aria-expanded` del botón #btn_menu_accesibilidad (Fase 17F-C).
 *
 * @param {boolean} [forzarAbierto] true abre y false cierra; sin argumento alterna (comportamiento de siempre).
 */
function toggleMenuAccesibilidad(forzarAbierto) {
    const menu = document.getElementById('menuAccesibilidad');
    const abierto = typeof forzarAbierto === 'boolean'
        ? menu.classList.toggle('activo', forzarAbierto)
        : menu.classList.toggle('activo');
    const boton = document.getElementById('btn_menu_accesibilidad');
    if (boton) {
        boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    }
}

// Escape cierra el menú de accesibilidad si está abierto y devuelve el foco a su botón (Fase 17F-C).
document.addEventListener('keydown', function (evento) {
    if (evento.key !== 'Escape') {
        return;
    }
    const menu = document.getElementById('menuAccesibilidad');
    if (menu && menu.classList.contains('activo')) {
        toggleMenuAccesibilidad(false);
        const boton = document.getElementById('btn_menu_accesibilidad');
        if (boton) {
            boton.focus();
        }
    }
});

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
