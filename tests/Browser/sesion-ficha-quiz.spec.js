// @ts-check
import { test, expect } from '@playwright/test';
import { USUARIO, CLAVE, iniciarSesionPorApi } from './ayudas.js';

/**
 * Sesión, ficha de departamento y quiz en las páginas del mapa, conectadas a la API.
 * CONTRATO (lote C): opciones del quiz = `button.opcion-quiz` dentro de `#quiz [role="group"]`; el anuncio
 * accesible va en `#quiz_anuncio` (aria-live).
 */

// Comprueba el login completo desde la interfaz: menú de accesibilidad -> modal -> POST /api/sesion -> caché local.
test('login desde el menú de accesibilidad guarda la sesión sin exponer el hash', async ({ page }) => {
    await page.goto('/');
    await page.locator('#btn_menu_accesibilidad').click();
    await page.locator('#btn_iniciar_sesion').click();
    const modal = page.locator('#modalLogin');
    await expect(modal).toBeVisible();
    await modal.locator('#usuario').fill(USUARIO);
    await modal.locator('#contrasena').fill(CLAVE);

    const respuesta = page.waitForResponse((r) => r.url().endsWith('/api/sesion') && r.request().method() === 'POST');
    await modal.getByRole('button', { name: /iniciar sesión/i }).click();
    expect((await respuesta).status()).toBe(200);

    await expect.poll(() => page.evaluate(() => localStorage.getItem('usuario'))).toBe(USUARIO);
    expect(await page.content()).not.toContain('$2y$');
});

// Comprueba que si la API de la ficha falla, el usuario no queda con un spinner eterno sino con un aviso de error.
test('ficha: un 500 cierra el spinner y muestra un error', async ({ page }) => {
    await page.route('**/api/departamentos/*/ficha', (route) =>
        route.fulfill({ status: 500, contentType: 'application/json', body: '{"message":"Error"}' }));
    await page.goto('/');
    await page.evaluate(() => {
        const a = document.createElement('a');
        a.setAttribute('id-departamento', '2');
        document.body.appendChild(a);
        // @ts-ignore función global de la página
        verMas(a);
    });
    await expect(page.locator('.swal2-popup')).toContainText('No se pudo');
    await expect(page.locator('.swal2-loading')).toBeHidden();
});

// Comprueba el caso feliz: la ficha real (~10 MB) del departamento 2 abre el modal con su nombre.
test('ficha: el departamento 2 abre #modal_sitios con Antioquia', async ({ page }) => {
    test.setTimeout(150000);
    await page.goto('/');
    await page.evaluate(() => {
        const a = document.createElement('a');
        a.setAttribute('id-departamento', '2');
        document.body.appendChild(a);
        // @ts-ignore función global de la página
        verMas(a);
    });
    await expect(page.locator('#modal_sitios')).toBeVisible({ timeout: 120000 });
    await expect(page.locator('#nombreDepartamento')).toContainText('Antioquia');
});

/**
 * Abre el quiz del departamento 2 y lo inicia; espera a que las opciones de la primera pregunta estén visibles.
 * @param {import('@playwright/test').Page} page
 */
async function iniciarQuiz(page) {
    await page.goto('/');
    await page.evaluate(() => {
        // @ts-ignore elemento de la página
        document.querySelector('#departamento_seleccionado').value = '2';
        // @ts-ignore función global de la página
        verTest();
    });
    await expect(page.locator('#boton_comenzar')).toBeVisible();
    await page.locator('#boton_comenzar').click();
    await expect(page.locator('#quiz [role="group"] button.opcion-quiz').first()).toBeVisible();
}

// Comprueba la accesibilidad por teclado: Enter sobre una opción la responde, bloquea todas y lo anuncia.
test('quiz: Enter responde, deshabilita las opciones y anuncia el resultado', async ({ page }) => {
    await iniciarQuiz(page);
    const opciones = page.locator('#quiz [role="group"] button.opcion-quiz');
    await opciones.first().focus();
    await page.keyboard.press('Enter');

    const n = await opciones.count();
    expect(n).toBeGreaterThan(1);
    for (let i = 0; i < n; i++) await expect(opciones.nth(i)).toBeDisabled();
    await expect(page.locator('#quiz_anuncio')).toHaveText(/Correcto|Incorrecto/);
});

/**
 * Cuestionario CONTROLADO para las pruebas del quiz: 10 preguntas de opción múltiple en las que la opción «Opción
 * correcta» es siempre la correcta. Con datos reales no se podría saber cuál es el botón correcto (el quiz baraja las
 * opciones), y una prueba que pulse una opción cualquiera puede pasar sin demostrar nada.
 *
 * @param {import('@playwright/test').Page} page Página en la que se sustituye la respuesta de la API.
 * @returns {Promise<void>}
 */
async function usarCuestionarioControlado(page) {
    const cuestionario = {
        departamento: { id: 2, nombre: 'Antioquia', capital: 'Medellín' },
        preguntas: Array.from({ length: 10 }, (_, i) => ({
            id: i + 1,
            tipo_pregunta: 'multiple',
            pregunta: `Pregunta ${i + 1}`,
            origen_pregunta: 'prueba',
            opciones: [
                { id: 1, opcion: 'Opción correcta', correcta: '1' },
                { id: 2, opcion: 'Opción falsa A', correcta: '0' },
                { id: 3, opcion: 'Opción falsa B', correcta: '0' },
            ],
        })),
    };
    await page.route('**/api/departamentos/2/preguntas', (ruta) => ruta.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(cuestionario),
    }));
}

/**
 * Lee el contador global de aciertos de quiz.js (`buenas`).
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<number>}
 */
async function aciertos(page) {
    return page.evaluate(() => Number(/** @type {any} */ (window).buenas) || 0);
}

// Comprueba que un doble clic rápido sobre la opción CORRECTA cuenta exactamente UN acierto (antes contaba dos).
test('quiz: un doble clic en la opción correcta suma exactamente un acierto', async ({ page }) => {
    await usarCuestionarioControlado(page);
    await iniciarQuiz(page);
    const antes = await aciertos(page);
    await page.getByRole('button', { name: 'Opción correcta', exact: true }).dblclick();
    expect(await aciertos(page) - antes).toBe(1);
    await expect(page.locator('#quiz_anuncio')).toHaveText(/Correcto/);
});

// Comprueba que una opción INCORRECTA no suma aciertos y se anuncia como incorrecta (no solo por color).
test('quiz: una opción incorrecta no suma y se anuncia como «Incorrecto»', async ({ page }) => {
    await usarCuestionarioControlado(page);
    await iniciarQuiz(page);
    const antes = await aciertos(page);
    await page.getByRole('button', { name: 'Opción falsa A', exact: true }).dblclick();
    expect(await aciertos(page) - antes).toBe(0);
    await expect(page.locator('#quiz_anuncio')).toHaveText(/Incorrecto/);
});

// Comprueba que sin departamento elegido el quiz no consulta preguntas (/api/departamentos//preguntas) y avisa.
test('climatico: verTest sin departamento avisa del mapa y no llama a la API', async ({ page }) => {
    /** @type {string[]} */
    const peticiones = [];
    page.on('request', (r) => { if (r.url().includes('/preguntas')) peticiones.push(r.url()); });
    await page.goto('/climatico.html');
    await page.evaluate(() => {
        // @ts-ignore función global de la página
        verTest();
    });
    await expect(page.locator('.swal2-popup')).toContainText('mapa de departamentos');
    expect(peticiones.filter((u) => u.includes('/api/departamentos//preguntas'))).toEqual([]);
});

// Comprueba que cerrar sesión avisa al servidor (DELETE) y borra la caché local.
test('cerrar sesión dispara DELETE /api/sesion y limpia localStorage', async ({ page }) => {
    await iniciarSesionPorApi(page);
    await page.goto('/');
    // Con el frontend conectado la página ya sincroniza esto sola; se fija por si aún no lo hace.
    await page.evaluate(() => { localStorage.setItem('usuario', 'usuario1'); localStorage.setItem('id_usuario', '1'); });
    await page.reload();

    await page.locator('#btn_menu_accesibilidad').click();
    await page.locator('#btn_cerrar_sesion').click();
    const borrado = page.waitForResponse((r) => r.url().endsWith('/api/sesion') && r.request().method() === 'DELETE');
    await page.locator('.swal2-confirm').click();
    await borrado;

    const r = await page.request.get('/api/sesion', { headers: { Accept: 'application/json' } });
    expect(r.status()).toBe(401);
    expect(await page.evaluate(() => [localStorage.getItem('usuario'), localStorage.getItem('id_usuario')])).toEqual([null, null]);
});
