/**
 * explora-api.js
 * =============================================================================
 * Cliente mínimo de la API Laravel (/api) para el sitio estático de Explora.
 *
 * PROPÓSITO
 *   Centraliza lo que todas las páginas necesitan para hablar con la API:
 *   resolución de URL (sin depender de la profundidad de la página), peticiones
 *   AJAX con cabeceras JSON y token CSRF, mensajes de error en español,
 *   sincronización de la sesión con localStorage y utilidades de seguridad
 *   (escape de HTML, validación de avatar).
 *
 *   La sesión real vive en una cookie HttpOnly del servidor; localStorage es solo
 *   una CACHÉ DE COMPATIBILIDAD para el frontend antiguo y nunca la fuente de verdad.
 *
 * CÓMO INCLUIRLO (siempre DESPUÉS de jQuery; el sitio y la API comparten origen)
 *   Raíz (index.html, ...):          <script src="js/explora-api.js"></script>
 *   juegos/index.html:               <script src="../js/explora-api.js"></script>
 *   juegos/juegos/<juego>/index.html:<script src="../../../js/explora-api.js"></script>
 *   Debe cargarse con una etiqueta <script src> normal (no async/módulo
 *   dinámico), porque la base del sitio se calcula con document.currentScript.
 *
 * CONFIGURACIÓN OPCIONAL
 *   window.EXPLORA_API = '/api';   // base alternativa de la API (antes de este script)
 *
 * EJEMPLO
 *   ExploraApi.peticion('POST', 'sesion', { usuario: u, contrasena: c })
 *     .done(function (r) { ExploraApi.guardarSesionLocal(r.usuario_objeto); })
 *     .fail(function (xhr) { alert(ExploraApi.mensajeError(xhr, 'No se pudo iniciar sesión.')); });
 *
 *   ExploraApi.sesionLista.done(function (usuario) { ... usuario o null ... });
 *   $(document).on('explora:sesion', function (e, usuario) { ... });
 *
 * NOTA jQuery 1.11: los jqXHR de esa versión no son promesas estándar; use
 * done/fail/always en lugar de then/catch.
 * =============================================================================
 */
(function (window, document) {
  'use strict';

  /**
   * Número máximo de avatares disponibles (1..35).
   * DEBE coincidir con AVATARES_MAX del servidor (config/explora.php).
   * @type {number}
   */
  var AVATARES_MAX = 35;

  /**
   * Claves de localStorage que el frontend antiguo todavía lee.
   * @type {string[]}
   */
  var CLAVES_SESION = ['usuario', 'nombre', 'grado', 'avatar', 'id_usuario'];

  /** Clave obsoleta que provocaba desfases entre páginas; se elimina siempre. */
  var CLAVE_OBSOLETA = 'tipo_modal';

  /** Nombre de la cookie CSRF que entrega Laravel. */
  var COOKIE_CSRF = 'XSRF-TOKEN';

  /** Escapes HTML aplicables a texto y a valores de atributo. */
  var ESCAPES_HTML = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
    '`': '&#96;'
  };

  /* --------------------------------------------------------------------------
   * Base del sitio. IMPORTANTE: document.currentScript solo existe durante la
   * ejecución de nivel superior del script; por eso se captura aquí, una vez.
   * ------------------------------------------------------------------------ */
  var scriptActual = document.currentScript;
  var baseSitio = calcularBase(scriptActual);

  /**
   * Calcula la URL base absoluta del sitio (carpeta que contiene /js/).
   * @param {HTMLScriptElement|null} script Elemento <script> de este archivo.
   * @returns {URL} Base del sitio, siempre terminada en «/».
   */
  function calcularBase(script) {
    if (script && script.src) {
      // Este archivo vive en /js/, así que '../' es la raíz del sitio.
      return new URL('../', script.src);
    }
    // Respaldo (p. ej. script inyectado dinámicamente): deducir desde la página.
    if (window.console && console.warn) {
      console.warn('ExploraApi: document.currentScript no está disponible; la raíz del sitio se deduce de la ubicación de la página.');
    }
    var ruta = window.location.pathname;
    var i = ruta.indexOf('/juegos/');
    // Las páginas de juegos viven bajo /juegos/...; el resto, en la raíz o su carpeta.
    var carpeta = i >= 0 ? ruta.slice(0, i + 1) : ruta.slice(0, ruta.lastIndexOf('/') + 1);
    return new URL(carpeta || '/', window.location.href);
  }

  /**
   * Devuelve la URL absoluta de un recurso del sitio.
   * @param {string} [ruta] Ruta relativa a la raíz, p. ej. 'img/logo.png'.
   * @returns {string} URL absoluta, p. ej. 'http://host/img/logo.png'.
   */
  function raiz(ruta) {
    return new URL(String(ruta == null ? '' : ruta).replace(/^\/+/, ''), baseSitio).href;
  }

  /**
   * Devuelve la URL completa de un endpoint de la API.
   * Usa window.EXPLORA_API (string) como base si existe; si no, raiz('api/').
   * @param {string} [ruta] Ruta del endpoint, p. ej. 'sesion' o '/sesion'.
   * @returns {string} URL lista para $.ajax.
   */
  function url(ruta) {
    var base = typeof window.EXPLORA_API === 'string' && window.EXPLORA_API !== ''
      ? window.EXPLORA_API
      : raiz('api/');
    // Una sola barra entre base y ruta, sin importar cómo vengan escritas.
    return base.replace(/\/+$/, '') + '/' + String(ruta == null ? '' : ruta).replace(/^\/+/, '');
  }

  /**
   * Lee una cookie por nombre. Soporta valores que contienen «=» (el token
   * XSRF de Laravel suele terminar en «%3D»; otros valores pueden llevar «=»).
   * @param {string} nombre Nombre exacto de la cookie.
   * @returns {string|null} Valor SIN decodificar, o null si no existe.
   */
  function leerCookie(nombre) {
    var partes = String(document.cookie || '').split(';');
    for (var i = 0; i < partes.length; i++) {
      var par = partes[i].replace(/^\s+/, '');
      // Se corta solo en el PRIMER «=»: el resto pertenece al valor.
      var corte = par.indexOf('=');
      if (corte > 0 && par.slice(0, corte) === nombre) {
        return par.slice(corte + 1);
      }
    }
    return null;
  }

  /**
   * Crea una promesa rechazada con la API de promesas nativa, marcada como
   * gestionada para no ensuciar la consola con «unhandled rejection».
   * @param {string} motivo Texto del error.
   * @returns {Promise<never>} Promesa rechazada.
   */
  function promesaRechazada(motivo) {
    var p = Promise.reject(new Error(motivo));
    p.catch(function () {});
    return p;
  }

  /**
   * Indica si jQuery está disponible (se comprueba en cada llamada).
   * @returns {boolean} true si existe window.jQuery.
   */
  function hayJQuery() {
    return typeof window.jQuery === 'function';
  }

  if (!hayJQuery() && window.console && console.error) {
    console.error('ExploraApi: jQuery no está cargado. Incluya jQuery ANTES de explora-api.js; peticion() y sincronizarSesion() no funcionarán.');
  }

  /**
   * Envoltorio de $.ajax para la API (no usa $.ajaxSetup, para no alterar
   * chat.js ni librerías de terceros).
   * @param {string} metodo Método HTTP (GET, POST, PATCH, PUT, DELETE).
   * @param {string} ruta Ruta del endpoint, relativa a la base de la API.
   * @param {Object|null} [datos] GET: parámetros de consulta. Resto: cuerpo JSON.
   * @param {{cache?: boolean}} [opciones] cache (true por defecto); false evita caché del navegador.
   * @returns {jqXHR|Promise} jqXHR (usar done/fail/always); promesa rechazada si falta jQuery.
   */
  function peticion(metodo, ruta, datos, opciones) {
    if (!hayJQuery()) {
      return promesaRechazada('jQuery no está disponible.');
    }
    var $ = window.jQuery;
    var verbo = String(metodo || 'GET').toUpperCase();
    var ajustes = {
      url: url(ruta),
      type: verbo,
      dataType: 'json',
      headers: { Accept: 'application/json' },
      // Sin tiempo máximo una respuesta colgada dejaba la ficha cargando para siempre; 120 s admite la ficha más pesada (~46 MB).
      timeout: (opciones && opciones.timeout) || 120000,
      cache: !(opciones && opciones.cache === false),
      // El token se lee AQUÍ, en cada petición: cambia al iniciar y cerrar
      // sesión, y un valor guardado al cargar la página provocaría errores 419.
      beforeSend: function (xhr) {
        var token = leerCookie(COOKIE_CSRF);
        if (token) {
          try {
            xhr.setRequestHeader('X-XSRF-TOKEN', decodeURIComponent(token));
          } catch (e) { /* cookie mal codificada: se envía sin token y el servidor responderá 419 */ }
        }
      }
    };
    if (datos != null) {
      if (verbo === 'GET') {
        ajustes.data = datos;
      } else {
        ajustes.data = JSON.stringify(datos);
        ajustes.contentType = 'application/json';
      }
    }
    return $.ajax(ajustes);
  }

  /**
   * Extrae el mensaje de texto del cuerpo JSON de un error, si lo hay.
   * @param {Object} xhr Objeto jqXHR.
   * @returns {string} Mensaje o cadena vacía. Nunca HTML ni cuerpo crudo.
   */
  function mensajeDelCuerpo(xhr) {
    var cuerpo = xhr && xhr.responseJSON;
    if (cuerpo && typeof cuerpo === 'object') {
      if (typeof cuerpo.mensaje === 'string' && cuerpo.mensaje) { return cuerpo.mensaje; }
      if (typeof cuerpo.message === 'string' && cuerpo.message) { return cuerpo.message; }
    }
    return '';
  }

  /**
   * Convierte un error de petición en un texto en español apto para el usuario.
   * @param {Object} xhr jqXHR del fallo (puede ser nulo).
   * @param {string} [porDefecto] Texto a usar si no hay mensaje específico.
   * @returns {string} Mensaje en español; nunca HTML ni el cuerpo crudo.
   */
  function mensajeError(xhr, porDefecto) {
    var estado = xhr && Number(xhr.status) || 0;
    var respaldo = porDefecto || 'No se pudo completar la solicitud.';
    if (estado === 0 && xhr && xhr.statusText === 'timeout') { return 'El servidor tardó demasiado en responder. Inténtelo de nuevo.'; }
    if (estado === 0) { return 'No hay conexión con el servidor.'; }
    if (estado === 419) { return 'Su sesión caducó. Vuelva a iniciar sesión.'; }
    if (estado === 429) {
      return mensajeDelCuerpo(xhr) || 'Demasiadas solicitudes. Espere un momento e inténtelo de nuevo.';
    }
    if (estado >= 500) { return 'Ocurrió un error en el servidor. Inténtelo más tarde.'; }
    if (estado === 401 || estado === 403 || estado === 404 || estado === 422) {
      return mensajeDelCuerpo(xhr) || respaldo;
    }
    return respaldo;
  }

  /**
   * Indica si el fallo significa «sesión caducada o inexistente».
   * @param {Object} xhr jqXHR del fallo.
   * @returns {boolean} true si el estado es 401 o 419.
   */
  function esSesionCaducada(xhr) {
    var estado = xhr && Number(xhr.status);
    return estado === 401 || estado === 419;
  }

  /**
   * Ejecuta una acción sobre localStorage tolerando que no esté disponible
   * (modo privado, cookies bloqueadas, etc.).
   * @param {function(Storage): void} accion Operación a realizar.
   * @returns {void}
   */
  function conAlmacenamiento(accion) {
    try {
      accion(window.localStorage);
    } catch (e) { /* localStorage inaccesible: se ignora; la sesión real está en el servidor */ }
  }

  /**
   * Guarda los datos del usuario en localStorage como caché de compatibilidad.
   * Los campos ausentes (null/undefined) NO se escriben y se borra su clave
   * anterior, para no dejar datos de otro usuario ni textos «undefined».
   * @param {{id?: number, nombre_usuario?: string, login_usuario?: string,
   *          grado_usuario?: (string|number), avatar_explora?: (string|number)}|null} usuario
   *        Objeto `usuario_objeto` de la API (o nulo).
   * @returns {void}
   */
  function guardarSesionLocal(usuario) {
    var origen = usuario && typeof usuario === 'object' ? usuario : {};
    var valores = {
      usuario: origen.login_usuario,
      nombre: origen.nombre_usuario,
      grado: origen.grado_usuario,
      avatar: origen.avatar_explora,
      id_usuario: origen.id
    };
    conAlmacenamiento(function (almacen) {
      CLAVES_SESION.forEach(function (clave) {
        var valor = valores[clave];
        if (valor === undefined || valor === null) {
          almacen.removeItem(clave);
        } else {
          almacen.setItem(clave, String(valor));
        }
      });
      almacen.removeItem(CLAVE_OBSOLETA);
    });
  }

  /**
   * Elimina de localStorage los datos de sesión (y la clave obsoleta).
   * @returns {void}
   */
  function limpiarSesionLocal() {
    conAlmacenamiento(function (almacen) {
      CLAVES_SESION.concat(CLAVE_OBSOLETA).forEach(function (clave) {
        almacen.removeItem(clave);
      });
    });
  }

  /**
   * Consulta GET /api/sesion y sincroniza localStorage con el servidor.
   *  - 200: guarda el usuario y resuelve con él.
   *  - 401: limpia localStorage y resuelve con null (no hay sesión).
   *  - Otro error (0, 5xx, 419): NO toca localStorage; resuelve con null.
   * Siempre dispara $(document).trigger('explora:sesion', [usuario|null]).
   * La llamada también deja la cookie XSRF-TOKEN lista (útil para invitados).
   * @returns {Object} Promesa jQuery que siempre se resuelve (usuario o null);
   *                   se rechaza solo si jQuery no existe.
   */
  function sincronizarSesion() {
    if (!hayJQuery()) {
      return promesaRechazada('jQuery no está disponible.');
    }
    var $ = window.jQuery;
    var diferido = $.Deferred();

    /** Notifica a la página y resuelve la promesa. */
    function terminar(usuario) {
      $(document).trigger('explora:sesion', [usuario]);
      diferido.resolve(usuario);
    }

    peticion('GET', 'sesion', null, { cache: false })
      .done(function (respuesta) {
        var usuario = respuesta && respuesta.usuario_objeto;
        if (usuario && typeof usuario === 'object') {
          guardarSesionLocal(usuario);
          terminar(usuario);
        } else {
          // 200 sin usuario: respuesta inesperada, no se altera la caché.
          terminar(null);
        }
      })
      .fail(function (xhr) {
        // Solo el 401 prueba que no hay sesión; un fallo transitorio no debe
        // cerrar la sesión local del usuario.
        if (xhr && xhr.status === 401) { limpiarSesionLocal(); }
        terminar(null);
      });

    return diferido.promise();
  }

  /**
   * Escapa texto para insertarlo de forma segura en HTML (contenido o atributo).
   * Escapa & < > " ' y `. null/undefined se convierten en cadena vacía.
   * @param {*} texto Valor a escapar (se convierte a String).
   * @returns {string} Texto escapado.
   */
  function escaparHtml(texto) {
    if (texto === null || texto === undefined) { return ''; }
    return String(texto).replace(/[&<>"'`]/g, function (c) { return ESCAPES_HTML[c]; });
  }

  /**
   * Valida un identificador de avatar. Acepta 7, '7' o '7.png'.
   * @param {number|string} valor Valor a validar.
   * @returns {number|null} Entero entre 1 y AVATARES_MAX, o null si no es válido.
   */
  function avatarValido(valor) {
    var n;
    if (typeof valor === 'number') {
      n = valor;
    } else if (typeof valor === 'string') {
      var m = /^(\d{1,3})(?:\.png)?$/.exec(valor);
      n = m ? parseInt(m[1], 10) : NaN;
    } else {
      return null;
    }
    return Number.isInteger(n) && n >= 1 && n <= AVATARES_MAX ? n : null;
  }

  // La API pública es inmutable para que ningún otro script la altere.
  var api = Object.freeze({
    raiz: raiz,
    url: url,
    peticion: peticion,
    mensajeError: mensajeError,
    esSesionCaducada: esSesionCaducada,
    guardarSesionLocal: guardarSesionLocal,
    limpiarSesionLocal: limpiarSesionLocal,
    sincronizarSesion: sincronizarSesion,
    // Primera sincronización, lanzada automáticamente al cargar el script.
    sesionLista: sincronizarSesion(),
    escaparHtml: escaparHtml,
    avatarValido: avatarValido
  });

  window.ExploraApi = api;
})(window, document);
