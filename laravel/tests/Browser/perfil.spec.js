// @ts-check
import { test, expect } from '@playwright/test';
import { iniciarSesionPorApi } from './ayudas.js';

/**
 * Página «Mi perfil» conectada a la API.
 * CONTRATO (lote D): las opciones de avatar del modal son `[data-avatar]` con role="button" y tabindex="0".
 * El avatar de usuario1 se restaura al final (globalTeardown -> fixtures.php limpiar).
 */

/**
 * Abre el modal de avatares y ESPERA a que Bootstrap termine de mostrarlo (evento `shown.bs.modal`).
 *
 * Por qué: al terminar de abrirse, Bootstrap 4 mueve el foco al propio modal. Si la prueba enfoca una opción y pulsa
 * Enter justo antes de ese momento, la tecla llega al modal y no a la opción (un teclista real ya llega con el foco
 * dentro del modal, así que no le afecta). Esperar el evento hace la prueba determinista sin esperas fijas.
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<void>}
 */
async function abrirModalAvatar(page) {
    await page.evaluate(() => {
        // @ts-ignore jQuery y el modal existen en la página
        window.__avatarMostrado = new Promise((resolver) => window.jQuery('#modal-avatar').one('shown.bs.modal', resolver));
    });
    await page.getByRole('button', { name: /cambiar avatar/i }).click();
    await page.evaluate(() => /** @type {any} */ (window).__avatarMostrado);
}

/**
 * Abre el modal de avatar, elige el 7 con el teclado (foco + Enter) y pulsa guardar.
 * @param {import('@playwright/test').Page} page
 */
async function elegirAvatar7YGuardar(page) {
    await abrirModalAvatar(page);
    const opcion = page.locator('[data-avatar="7"]');
    await expect(opcion).toBeVisible();
    await opcion.focus();
    await page.keyboard.press('Enter');
    // Elegir un avatar debe CERRAR el modal (aunque la elección ocurra mientras aún se abre: Bootstrap 4 ignora
    // hide() durante esa transición y miPerfil/script.js lo reintenta). Si no se cierra, el botón «Guardar» queda tapado.
    await expect(page.locator('#modal-avatar')).toBeHidden();
}

// Comprueba que con sesión la página muestra los datos reales del usuario desde la API.
test('con sesión muestra el nombre del usuario', async ({ page }) => {
    await iniciarSesionPorApi(page);
    await page.goto('/miPerfil.html');
    await expect(page.locator('#nombre')).toHaveValue('Usuario 1');
});

// Comprueba que las opciones de avatar son operables por teclado y que el cambio se persiste en el servidor.
test('cambiar avatar con teclado hace PATCH 200 y persiste al recargar', async ({ page }) => {
    await iniciarSesionPorApi(page);
    await page.goto('/miPerfil.html');
    await expect(page.locator('#nombre')).toHaveValue('Usuario 1');
    await expect(page.getByRole('button', { name: /cambiar avatar/i })).toBeVisible();
    await abrirModalAvatar(page);
    const opcion = page.locator('[data-avatar="7"]');
    await expect(opcion).toHaveAttribute('role', 'button');
    await expect(opcion).toHaveAttribute('tabindex', '0');
    await opcion.focus();
    await page.keyboard.press('Enter');

    const patch = page.waitForResponse((r) => r.url().endsWith('/api/perfil/avatar') && r.request().method() === 'PATCH');
    await page.locator('#btn-guardar').click();
    expect((await patch).status()).toBe(200);

    await page.reload();
    await expect(page.locator('#avatar-preview')).toHaveAttribute('src', /7\.png$/);
});

// Comprueba que sin sesión se invita a iniciar sesión y NO se consulta el historial (sería un 401 inútil).
test('sin sesión muestra aviso con enlace a index.html y no llama a /api/historial', async ({ page }) => {
    /** @type {string[]} */
    const historial = [];
    page.on('request', (r) => { if (r.url().includes('/api/historial')) historial.push(r.url()); });
    await page.goto('/miPerfil.html');
    await expect(page.getByText(/iniciar sesión/i).first()).toBeVisible();
    await expect(page.locator('a[href$="index.html"]').filter({ hasText: /./ }).first()).toBeVisible();
    expect(historial).toEqual([]);
});

// Comprueba que si la cookie CSRF desaparece, guardar el avatar no falla en silencio: se avisa de sesión caducada.
test('sin cookie XSRF al guardar avatar se avisa de sesión caducada', async ({ page }) => {
    await iniciarSesionPorApi(page);
    await page.goto('/miPerfil.html');
    await expect(page.locator('#nombre')).toHaveValue('Usuario 1');
    // Se espera a que termine la red (p. ej. el historial): cada respuesta de la API vuelve a emitir la cookie
    // XSRF-TOKEN, y una respuesta tardía la restauraría después de borrarla, haciendo que el guardado SÍ funcione.
    await page.waitForLoadState('networkidle');
    await elegirAvatar7YGuardar(page);
    await page.context().clearCookies({ name: 'XSRF-TOKEN' });
    await page.locator('#btn-guardar').click();
    await expect(page.locator('.swal2-popup, [role="alert"]').first()).toContainText(/sesión/i);
});
