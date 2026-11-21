// @ts-check
/**
 * Ayudas compartidas por las pruebas de navegador de Explora.
 * (No es una prueba: Playwright solo ejecuta los archivos *.spec.js.)
 */

/** Credenciales del usuario de prueba (datos anonimizados de pedigital_test; id 1, Estudiante, grado 6). */
export const USUARIO = 'usuario1';
export const CLAVE = 'password';

/**
 * Inicia sesión por API usando el contexto de la página, de modo que la cookie de sesión queda compartida
 * con el navegador. Reproduce lo que hace js/explora-api.js: GET /api/sesion (entrega la cookie XSRF-TOKEN) y
 * POST /api/sesion con la cabecera X-XSRF-TOKEN.
 *
 * @param {import('@playwright/test').Page} page Página cuyo contexto recibirá la sesión.
 * @returns {Promise<void>} Resuelve cuando el login respondió 200 (falla en caso contrario).
 */
export async function iniciarSesionPorApi(page) {
    await page.request.get('/api/sesion', { headers: { Accept: 'application/json' } });
    const cookies = await page.context().cookies();
    const xsrf = cookies.find((c) => c.name === 'XSRF-TOKEN');
    if (!xsrf) throw new Error('GET /api/sesion no entregó la cookie XSRF-TOKEN');

    const r = await page.request.post('/api/sesion', {
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) },
        data: { usuario: USUARIO, contrasena: CLAVE },
    });
    if (r.status() !== 200) throw new Error(`Login por API falló: HTTP ${r.status()}`);
}

/**
 * Registra los diálogos nativos (alert/confirm/prompt) que aparezcan y los cierra. Sirve para detectar XSS:
 * un `onerror="alert(...)"` ejecutado dejaría una entrada en la lista devuelta.
 *
 * @param {import('@playwright/test').Page} page Página a vigilar.
 * @returns {string[]} Lista viva con el mensaje de cada diálogo nativo detectado.
 */
export function vigilarDialogosNativos(page) {
    /** @type {string[]} */
    const vistos = [];
    page.on('dialog', async (d) => {
        vistos.push(d.message());
        await d.dismiss();
    });
    return vistos;
}
