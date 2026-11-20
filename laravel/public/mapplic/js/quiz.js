/**
 * quiz.js
 * =============================================================================
 * Test (quiz) por departamento del mapa de Explora.
 *
 * Depende de jQuery, SweetAlert2 y de js/explora-api.js (ExploraApi), que debe
 * cargarse ANTES que este archivo.
 *
 * ACCESIBILIDAD
 *   - Las opciones son <button type="button" class="opcion-quiz"> (operables con
 *     teclado) dentro de un contenedor role="group" etiquetado por el enunciado.
 *   - El resultado de cada respuesta se anuncia como texto en #quiz_anuncio
 *     (región aria-live), no solo con color.
 *
 * SEGURIDAD
 *   - La corrección sigue haciéndose en el navegador (la API incluye
 *     `correcta`/`respuesta` mientras config explora.quiz_incluir_respuestas
 *     sea true), pero esos valores NUNCA se escriben en el DOM: viven en el
 *     cierre del manejador de cada botón.
 *   - El enunciado y el texto de las opciones son HTML EDITORIAL administrado
 *     por el equipo de contenidos (pueden llevar etiquetas de formato); por eso
 *     se insertan como HTML. Ningún dato de usuario pasa por esta vía.
 *
 * Los nombres globales (verTest, cerrarTest, buenas, numeropregunta,
 * arrayPreguntas, ...) se conservan porque los usan los onclick de las páginas.
 * =============================================================================
 */

var intervaloContador;
var numeropregunta = 0;
var arrayPreguntas = [];
var buenas = 0;
var departamento = {};
var pregunta = null;
var id_departamento = null;

/** Número máximo de preguntas por test. */
var MAX_PREGUNTAS = 10;

/**
 * Total de preguntas del test en curso: min(10, preguntas recibidas).
 * @returns {number} Total de preguntas a contestar.
 */
function totalPreguntas() {
    return Math.min(MAX_PREGUNTAS, arrayPreguntas.length);
}

/**
 * Abre el test del departamento elegido en el mapa.
 * - Sin #departamento_seleccionado en la página (solo existe en la portada, `/`):
 *   informa y enlaza al mapa; no pide nada a la API.
 * - Con el campo vacío: pide seleccionar un departamento.
 * - Si no: muestra el contenedor y pide las preguntas UNA sola vez.
 * @returns {void}
 */
function verTest() {
    var $campo = $("#departamento_seleccionado");

    if ($campo.length === 0) {
        // El HTML de Swal es texto estático escrito aquí: no contiene datos externos.
        Swal.fire({
            icon: 'info',
            title: 'Test por departamento',
            html: 'El test por departamento está disponible en el mapa de departamentos.<br><br>' +
                  '<a class="btn btn-primary" href="/">Ir al mapa de departamentos</a>',
            confirmButtonColor: '#3085d6'
        });
        return;
    }

    id_departamento = $campo.val();
    if (id_departamento === "" || id_departamento == null) {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Debe seleccionar un departamento o un sitio de interés para poder realizar el test',
            confirmButtonColor: '#3085d6'
        });
        return;
    }

    // El id sale de un campo oculto: se valida antes de usarlo en la URL.
    if (!/^[1-9][0-9]*$/.test(String(id_departamento))) {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'El departamento seleccionado no es válido.',
            confirmButtonColor: '#3085d6'
        });
        return;
    }

    $('#loadbar').show();
    $('#boton_comenzar').hide();
    $('#quiz').hide();
    $('#quiz_anuncio').text('');
    $('#contenedorTest').fadeIn(1000);
    buscarPreguntas(function () {
        $('#loadbar').hide();
        $('#boton_comenzar').show().removeClass("animacionLogo");
    });
}

/**
 * Pide a la API las preguntas del departamento seleccionado y las deja en
 * `arrayPreguntas` (barajadas y limitadas a 10).
 * Si falla o llegan 0 preguntas: oculta la barra de carga, cierra el test y avisa.
 * @param {function(): void} [callback] Se invoca solo si hay al menos una pregunta.
 * @returns {void}
 */
function buscarPreguntas(callback) {
    ExploraApi.peticion('GET', 'departamentos/' + encodeURIComponent(id_departamento) + '/preguntas')
        .done(function (response) {
            departamento = response.departamento || {};
            $("#departamento_nombre").text(departamento.nombre || '');
            arrayPreguntas = shuffleArray(response.preguntas || []);

            if (arrayPreguntas.length === 0) {
                abortarTest('Este departamento todavía no tiene preguntas disponibles.');
                return;
            }
            $("#contador_preguntas").text('Pregunta 0 de ' + totalPreguntas());
            if (typeof callback === "function") {
                callback();
            }
        })
        .fail(function (xhr) {
            abortarTest('No se pudieron cargar las preguntas del test. ' + ExploraApi.mensajeError(xhr));
        });
}

/**
 * Cancela la carga del test: oculta la barra de carga, cierra el contenedor y
 * muestra un aviso de error.
 * @param {string} mensaje Texto (sin HTML) para el usuario.
 * @returns {void}
 */
function abortarTest(mensaje) {
    $('#loadbar').hide();
    cerrarTest();
    Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: mensaje,
        confirmButtonColor: '#3085d6'
    });
}

/**
 * Cierra el contenedor del test y reinicia contador, caritas y puntaje.
 * @returns {void}
 */
function cerrarTest() {
    clearInterval(intervaloContador);
    $('#contenedorTest').fadeOut(1000, function () {
        $(".facaritas").removeClass("fa-frown").addClass("fa-smile").css("color", "#6c757d");
        $("#contador").text("00:00");
        $("#contador_preguntas").text("Pregunta 0 de " + MAX_PREGUNTAS);
        $('#quiz_anuncio').text('');
        numeropregunta = 0;
        buenas = 0;
    });
}

/**
 * Oculta el botón de inicio y arranca el contador y la primera pregunta.
 * @returns {void}
 */
function comenzarTest() {
    $('#boton_comenzar').addClass("animacionLogo").delay(1500).fadeOut(function () {
        iniciarContador();
        mapearPregunta();
    });
}

/**
 * Inicia el cronómetro mm:ss del test.
 * @returns {void}
 */
function iniciarContador() {
    $(".facaritas").removeClass("fa-frown").addClass("fa-smile");
    let segundos = 0;
    intervaloContador = setInterval(function () {
        segundos++;
        let minutos = Math.floor(segundos / 60);
        let segundosRestantes = segundos % 60;
        $("#contador").text(`${minutos.toString().padStart(2, '0')}:${segundosRestantes.toString().padStart(2, '0')}`);
    }, 1000);
}

/**
 * Baraja un arreglo (modifica el original) y devuelve como máximo 10 elementos.
 * @param {Array} array Elementos a barajar.
 * @returns {Array} Hasta 10 elementos en orden aleatorio.
 */
function shuffleArray(array) {
    return array.sort(() => Math.random() - 0.5).slice(0, MAX_PREGUNTAS);
}

/**
 * Pinta la pregunta actual con sus opciones como botones accesibles.
 * Enunciado y opciones son HTML editorial (se insertan como HTML); la
 * corrección vive en el cierre de cada manejador, no en atributos del DOM.
 * Al terminar mueve el foco a la primera opción.
 * @returns {void}
 */
function mapearPregunta() {
    pregunta = arrayPreguntas[numeropregunta];
    $("#contador_preguntas").text(`Pregunta ${numeropregunta + 1} de ${totalPreguntas()}`);

    var $grupo = $('<div role="group" aria-labelledby="enunciado_pregunta"></div>');
    // Contenido editorial administrado: ver nota de seguridad de la cabecera.
    var $enunciado = $("<h4 id='enunciado_pregunta' class='element-animation0'></h4>")
        .html("<span class='label label-warning'>" + pregunta.pregunta + "</span>");
    var opciones = [];

    if (pregunta.tipo_pregunta === "multiple") {
        shuffleArray(pregunta.opciones).forEach(function (element) {
            opciones.push({ texto: element.opcion, esHtml: true, correcta: Number(element.correcta) === 1 });
        });
    } else {
        // Verdadero/Falso: la API entrega en opciones[0].respuesta (0/1) el valor correcto.
        var valor = pregunta.opciones && pregunta.opciones[0] ? Number(pregunta.opciones[0].respuesta) : NaN;
        opciones.push({ texto: 'Verdadero', esHtml: false, correcta: valor === 1 });
        opciones.push({ texto: 'Falso', esHtml: false, correcta: valor === 0 });
    }

    opciones.forEach(function (op, index) {
        var $boton = $("<button type='button'></button>")
            .addClass('opcion-quiz element-animation' + (index + 1) + ' btn btn-lg btn-primary btn-block');
        var $contenido = $("<span class='btn-label'><i class='fa fa-chevron-right' aria-hidden='true'></i></span>");
        $boton.append($contenido);
        if (op.esHtml) {
            // Texto editorial de la opción (HTML administrado).
            $boton.append($("<span class='texto-opcion'></span>").html(op.texto));
        } else {
            $boton.append(document.createTextNode(op.texto));
        }
        $boton.on('click', function (evento) {
            // Evita que el plugin de botones de Bootstrap (data-toggle="buttons") alterne la opción.
            evento.stopPropagation();
            verificarRespuesta($boton, op.correcta);
        });
        $grupo.append($boton);
    });

    $("#quiz").hide().empty().append($enunciado, $grupo).fadeIn(500, function () {
        $grupo.find('button.opcion-quiz').first().trigger('focus');
    });
}

/**
 * Califica la opción pulsada (múltiple o verdadero/falso).
 * Solo cuenta el PRIMER clic: deshabilita todas las opciones, lo que evita el
 * doble conteo de `buenas` con dobles clics o Enter repetido. Marca el color,
 * anuncia «Correcto»/«Incorrecto» en #quiz_anuncio y avanza a la siguiente pregunta.
 * @param {jQuery} $opcion Botón pulsado.
 * @param {boolean} esCorrecta Si la opción pulsada es la correcta.
 * @returns {void}
 */
function verificarRespuesta($opcion, esCorrecta) {
    $('button.opcion-quiz').prop('disabled', true);
    let $carita = $(`#carita${numeropregunta}`);

    if (esCorrecta) {
        $opcion.addClass("btn-success").removeClass("btn-primary");
        $carita.css("color", "#2c7b36");
        buenas++;
    } else {
        $opcion.addClass("btn-danger").removeClass("btn-primary");
        $carita.css("color", "#bd4141").removeClass("fa-smile").addClass("fa-frown");
    }
    $('#quiz_anuncio').text(esCorrecta ? 'Correcto' : 'Incorrecto');

    setTimeout(()=>{
        $('#quiz').fadeOut(800, function () {
            actualizarNumeroPregunta();
            if(verificarFin()){
                mostrarResultado()
            }else{
                mapearPregunta();
            }
        });
    }, 700);
}

/**
 * Indica si ya se contestaron todas las preguntas del test.
 * @returns {boolean} true al llegar a min(10, preguntas recibidas).
 */
function verificarFin() {
    return numeropregunta >= totalPreguntas();
}

/**
 * Avanza el índice de la pregunta actual.
 * @returns {void}
 */
function actualizarNumeroPregunta() {
    numeropregunta++;
}

/**
 * Cierra el test y muestra la pantalla final con el puntaje y su sonido.
 * @returns {void}
 */
function mostrarResultado() {
    // totalPreguntas() se lee antes de cerrar, porque cerrarTest reinicia el estado.
    var total = totalPreguntas();
    var aciertos = buenas;
    cerrarTest();
    $('#final').fadeToggle(1000);
    $("#imagen_final").attr("src", aciertos < 6 ? "images/derrota.webp" : "images/victoria.webp");
    $("#texto_final").text(`Has contestado correctamente ${aciertos} preguntas de ${total}.`);
    new Audio(aciertos >= 6 ? 'sounds/victory.mp3' : 'sounds/game_over.mp3').play();
    buenas = 0;
}
