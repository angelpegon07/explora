/*
 * Lógica de la página «sopa_letras» (Fase 13).
 * Antes era el <script> inline de ~235 líneas al final de sopa_letras/index.html; se extrajo SIN cambios (incluido el
 * código muerto `readText`) y se carga en la MISMA posición, tras terminar_juego.js. Sus rutas relativas
 * (`datos.json`, `../assets/…`) resuelven contra la URL de la página, /juegos/juegos/sopa_letras/. El nombre del juego
 * enviado a /api/resultados («Sopa de letras») sigue escrito aquí.
 */
var words = [];
var gamePuzzle;
$(document).ready(function () {

  pantalla_completa('#422612', '#8a5025', 'none').then(function (result) {
    iniciar_animacion();
  });


  function iniciar_animacion() {
    setTimeout(() => {
      $('#principal').fadeToggle(1000);
      $('#fondo_blanco').fadeToggle(3000);
      setTimeout(() => {
        const divAnimado = document.querySelector('.overlay');
        divAnimado.style.animationName = 'moverDerecha';
        divAnimado.style.animationDirection = 'normal';
        divAnimado.style.display = 'block';
        setTimeout(() => {
          const divAnimado2 = document.querySelector('.nube');
          divAnimado2.style.animationName = 'moverArriba';
          divAnimado2.style.animationDirection = 'normal';
          divAnimado2.style.display = 'block';
          setTimeout(() => {
            divAnimado.style.backgroundImage = "url(../assets/images/normal2.webp)"
            maquina2("bienvenida", 'Hola, soy Genio. <br> Selecciona una de las siguientes categorias para jugar, y luego busca las palabras en la sopa de letras. <br> ¡Tú Puedes!', 50, 1);
          }, 3000)
        }, 2000)
      })
    }, 200)
  }
});

function maquina2(contenedor, texto, intervalo, n) {
  var i = 0,
    // Creamos el timer
    timer = setInterval(function () {
      if (i < texto.length) {
        // Si NO hemos llegado al final del texto..
        // Vamos añadiendo letra por letra y la _ al final.
        $("#" + contenedor).html(texto.substr(0, i++) + "_");
      } else {
        // En caso contrario..
        // Salimos del Timer y quitamos la barra baja (_)
        clearInterval(timer);
        $("#" + contenedor).html(texto);
        if (!cerrardo) {
          document.querySelector('#btnomitir').style.display = "none";
          setTimeout(() => {
            cerrar_anuncio();
          }, 3000)
        }
        // Auto invocamos la rutina n veces (0 para infinito)
        if (--n != 0) {
          setTimeout(function () {
            maquina2(contenedor, texto, intervalo, n);
          }, 3600);
        }
      }
    }, intervalo);
}

let cerrardo = false;
function cerrar_anuncio() {
  if (!cerrardo) {
    cerrardo = true;
    const divAnimado2 = document.querySelector('.nube');
    divAnimado2.style.animationName = 'moverabajo';
    const divAnimado = document.querySelector('.overlay');
    divAnimado.style.backgroundImage = "url(../assets/images/normal1.webp)";
    $('#fondo_blanco').fadeToggle(3000);
    setTimeout(function () {
      divAnimado.style.animationName = 'moverIzquierda';
      divAnimado.style.animationDirection = 'normal';
      setTimeout(() => {
        $('#principal').fadeToggle(1000);
        elegir();
      }, 2000)
    }, 2000);
  }
}

var categorias = [];

function cargar_categorias() {
  return new Promise((resolve, reject) => {
    $.getJSON("datos.json", function (data) {
      resolve(data);
    }).fail(function (jqXHR, textStatus, error) {
      reject(error);
    });
  });
}

async function elegir() {
  categorias = await cargar_categorias();

  var categorias_html = "";

  Object.keys(categorias).forEach(key => {
    categorias_html += '<div onclick="seleccionar(\'' + key + '\')"  class="col-4"><div class="contenedor_categoria"><div class="icono_vocal"><img src="' + categorias[key].mapa + '" alt=""></div> <p class="nombre_categoria">' + categorias[key].nombre + '</p> </div></div>';
  });

  Swal.fire({
    title: 'Seleccione una Categoria',
    html: '<div style="padding-top: 20px; height: 60vh; margin: 0px"  class="row">' + categorias_html + '</div>',
    showCloseButton: false,
    showCancelButton: false,
    showConfirmButton: false,
    allowOutsideClick: false,
    focusConfirm: false,
  });
}

var tipo_de_juego = null;
function seleccionar(tipo) {
  Swal.close();
  tipo_de_juego = tipo;
  $('#nivelModal').modal('show');
}

var nivel_seleccionado = 0;
function selectNivel(tiempo) {
  $('#nivelModal').modal('hide');
  clearInterval(intervalo);
  iniciar_juego();
  iniciarContador(tiempo);
  if (tiempo == 10) {
    nivel_seleccionado = "Principiante";
  } else {
    nivel_seleccionado = "Experto";
  }
}


var intervalo;
var tiempo;
function iniciarContador(minutos) {
  tiempo = minutos * 60;

  intervalo = setInterval(() => {
    const min = String(Math.floor(tiempo / 60)).padStart(2, '0');
    const seg = String(tiempo % 60).padStart(2, '0');
    var tiempo_actual = `${min}:${seg}`;
    document.getElementById('contador').innerHTML = tiempo_actual;
    tiempo--;

    if (tiempo < 0) {
      let audio2 = new Audio("../sounds/game_over.mp3");
      audio2.volume = 0.29;
      audio2.play();
      clearInterval(intervalo);

      Swal.fire({
        html: '<img src="../assets/images/derrota.webp" style="width: 400px;"> <br> <h2 style="font-size: 2.2rem; text-align: center; line-height: 45px;">¡Opps, se ha acabado el tiempo! <br> ¿Quieres volver a intentarlo?</h2>',
        confirmButtonText: 'Si, jugar',
        confirmButtonColor: '#34230d',
        cancelButtonText: 'No, salir',
        showCancelButton: true,
        cancelButtonColor: '#5a391f',
        allowOutsideClick: false,
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.reload(1);
        } else {
          window.location.href = '/juegos';
        }
      });
    }
  }, 1000);
}

let audio = document.createElement('audio');
var categoria_query = '';
function iniciar_juego() {
  var categoria = categorias[tipo_de_juego];
  categoria_query = categoria.nombre;
  var palabras = randomValueGenerator(categoria.palabras);

  audio.pause();
  audio.src = "../sounds/fondo_sopa_letras.mp3";
  audio.play();
  audio.loop = true;
  audio.volume = 0.2;

  setTimeout(() => {
    swal.close();
    words = [];
    for (i = 0; i < 10; i++) {
      words.push(palabras[i].toLowerCase());
    }

    $('#Juegos').show();
    gamePuzzle = wordfindgame.create(words, '#juego', '#Palabras');
    iniciar_contador_juego();
  }, 1000)
}

function readText(ruta_local) {
  var texto = null;
  var xmlhttp = new XMLHttpRequest();
  xmlhttp.open("GET", ruta_local, false);
  xmlhttp.send();
  if (xmlhttp.status == 200) {
    texto = xmlhttp.responseText;
  }
  return texto;
}


function randomValueGenerator(vector) {
  return vector.sort(function () { return Math.random() - 0.5 });
};

$('#solve').click(function () { wordfindgame.solve(gamePuzzle, words); });


var contador_juego = 0;
var intervalo_contador_juego = null;
function iniciar_contador_juego() {
  intervalo_contador_juego = setInterval(() => {
    contador_juego++;
  }, 1000);
}

function detener_contador_juego() {
  clearInterval(intervalo_contador_juego);
}

function terminar_juego() {
  clearInterval(intervalo_contador_juego);
  clearInterval(intervalo);
  guardar_resultado("Sopa de letras", 10, contador_juego, nivel_seleccionado, categoria_query);
}
