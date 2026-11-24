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
    await page.goto('/perfil');
    await expect(page.locator('#nombre')).toHaveValue('Usuario 1');
});

// Comprueba que las opciones de avatar son operables por teclado y que el cambio se persiste en el servidor.
test('cambiar avatar con teclado hace PATCH 200 y persiste al recargar', async ({ page }) => {
    await iniciarSesionPorApi(page);
    await page.goto('/perfil');
    await expect(page.locator('#nombre')).toHaveValue('Usuario 1');
    await expect(page.getByRole('button', { name: /cambiar avatar/i })).toBeVisible();
    await abrirModalAvatar(page);
    const opcion = page.locator('[data-avatar="7"]');
    await expect(opcion).toHaveAttribute('role', 'button');
    await expect(opcion).toHaveAttribute('tabindex', '0');
    await opcion.focus();
    await page.keyboard.press('Enter');

    const patch = page.waitForResponse((r) => r.url().endsWith('/api/v1/perfil/avatar') && r.request().method() === 'PATCH');
    await page.locator('#btn-guardar').click();
    expect((await patch).status()).toBe(200);

    await page.reload();
    await expect(page.locator('#avatar-preview')).toHaveAttribute('src', /7\.png$/);
});

// Comprueba que sin sesión se invita a iniciar sesión y NO se consulta el historial (sería un 401 inútil).
test('sin sesión muestra aviso con enlace a la portada y no llama a /api/historial', async ({ page }) => {
    /** @type {string[]} */
    const historial = [];
    page.on('request', (r) => { if (r.url().includes('/api/historial')) historial.push(r.url()); });
    await page.goto('/perfil');
    await expect(page.getByText(/iniciar sesión/i).first()).toBeVisible();
    await expect(page.locator('a[href="/"]').filter({ hasText: /./ }).first()).toBeVisible();
    expect(historial).toEqual([]);
});

// Comprueba que si la cookie CSRF desaparece, guardar el avatar no falla en silencio: se avisa de sesión caducada.
test('sin cookie XSRF al guardar avatar se avisa de sesión caducada', async ({ page }) => {
    await iniciarSesionPorApi(page);
    await page.goto('/perfil');
    await expect(page.locator('#nombre')).toHaveValue('Usuario 1');
    // Se espera a que termine la red (p. ej. el historial): cada respuesta de la API vuelve a emitir la cookie
    // XSRF-TOKEN, y una respuesta tardía la restauraría después de borrarla, haciendo que el guardado SÍ funcione.
    await page.waitForLoadState('networkidle');
    await elegirAvatar7YGuardar(page);
    await page.context().clearCookies({ name: 'XSRF-TOKEN' });
    await page.locator('#btn-guardar').click();
    await expect(page.locator('.swal2-popup, [role="alert"]').first()).toContainText(/sesión/i);
});

// Fase 12: el perfil se pinta en el SERVIDOR; el HTML inicial ya trae los datos, sin esperar a ningún JS.
test('el HTML del perfil ya trae los datos del usuario pintados por el servidor', async ({ page }) => {
    await iniciarSesionPorApi(page);
    const html = await (await page.request.get('/perfil')).text();
    expect(html).toContain('value="Usuario 1"');
    expect(html).toContain('value="usuario1"');
    expect(html).toMatch(/<option value="6" selected>/);
    // La lista de avatares la genera Blade con config('explora.avatares_max') (35 por defecto).
    expect((html.match(/data-avatar="\d+"/g) || []).length).toBe(35);
});

// Con sesión se pintan el formulario y el menú; con la sesión cerrada, solo el aviso (nada de datos).
test('con sesión el perfil ofrece menú, formulario e historial y sin sesión solo el aviso', async ({ page }) => {
    await iniciarSesionPorApi(page);
    await page.goto('/perfil');
    await expect(page.locator('#menu-perfil')).toBeVisible();
    await expect(page.locator('#usuario')).toHaveValue('usuario1');
    await expect(page.locator('#grado')).toHaveValue('6');
    await expect(page.locator('#avatars-preview [data-avatar]')).toHaveCount(35);
    await expect(page.locator('#aviso-sesion')).toHaveCount(0);

    await page.context().clearCookies();
    await page.goto('/perfil');
    await expect(page.locator('#aviso-sesion')).toBeVisible();
    await expect(page.locator('#nombre')).toHaveCount(0);
    await expect(page.locator('#historial-body')).toHaveCount(0);
    await expect(page.locator('#modal-avatar')).toHaveCount(0);
});

// Cerrar sesión desde el perfil (SweetAlert + DELETE /api/sesion) lleva a la portada y /perfil vuelve a mostrar el aviso.
test('cerrar sesión desde el perfil limpia la sesión del servidor y la caché local', async ({ page }) => {
    await iniciarSesionPorApi(page);
    await page.goto('/perfil');
    await expect(page.locator('#nombre')).toHaveValue('Usuario 1');
    await page.locator('#link-cerrar-sesion').click();
    await page.getByRole('button', { name: /cerrar sesión/i }).click();
    await page.waitForURL((url) => url.pathname === '/');
    expect(await page.evaluate(() => window.localStorage.getItem('usuario'))).toBeNull();

    await page.goto('/perfil');
    await expect(page.locator('#aviso-sesion')).toBeVisible();
});

// Las URL antiguas siguen funcionando: redirigen (301) a las nuevas.
test('/miPerfil.html redirige a /perfil', async ({ request }) => {
    const r = await request.get('/miPerfil.html', { maxRedirects: 0 });
    expect(r.status()).toBe(301);
    expect(new URL(r.headers()['location'], 'http://x').pathname).toBe('/perfil');
});

// Datos personales: nada de caché (ni al volver con «atrás» tras cerrar sesión).
test('el perfil se envía con Cache-Control private, no-store', async ({ request }) => {
    const r = await request.get('/perfil');
    expect(r.headers()['cache-control']).toMatch(/no-store/);
    expect(r.headers()['cache-control']).toMatch(/private/);
});
