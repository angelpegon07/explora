// @ts-check
import { test, expect } from '@playwright/test';
import { iniciarSesionPorApi } from './ayudas.js';

/**
 * Portal de juegos (`/juegos`, Fase 12): la barra superior se pinta en el SERVIDOR según la sesión real, y
 * mapplic/js/iniciarSesion.js solo la mantiene al día. Incluye las redirecciones de las URL antiguas.
 */

// Sin sesión: «Iniciar sesión» visible; «Mi Perfil» y «Salir» ocultos.
test('el portal anónimo muestra iniciar sesión y oculta perfil y salir', async ({ page }) => {
    await page.goto('/juegos');
    await expect(page.locator('#btn-iniciar')).toBeVisible();
    await expect(page.locator('#btn-perfil')).toBeHidden();
    await expect(page.locator('#btn-cerrar')).toBeHidden();
});

// Con sesión: a la inversa, y el enlace del perfil apunta a /perfil.
test('el portal con sesión muestra perfil y salir y oculta iniciar sesión', async ({ page }) => {
    await iniciarSesionPorApi(page);
    await page.goto('/juegos');
    await expect(page.locator('#btn-perfil')).toBeVisible();
    await expect(page.locator('#btn-cerrar')).toBeVisible();
    await expect(page.locator('#btn-iniciar')).toBeHidden();
    await expect(page.locator('#btn-perfil')).toHaveAttribute('href', /\/perfil$/);
    // «Volver» lleva a la portada del sitio.
    await expect(page.getByRole('link', { name: /volver/i })).toHaveAttribute('href', '/');
});

// Sin parpadeo: el HTML que envía el servidor YA trae el estado correcto (no depende del JS ni de localStorage).
test('el HTML inicial del portal ya trae el estado de la sesión', async ({ page }) => {
    const anonimo = await (await page.request.get('/juegos')).text();
    expect(anonimo).toMatch(/id="btn-iniciar" style="display: block;"/);
    expect(anonimo).toMatch(/id="btn-perfil"[^>]*>/);
    expect(anonimo).toMatch(/style="display: none;" id="btn-perfil"/);

    await iniciarSesionPorApi(page);
    const conSesion = await (await page.request.get('/juegos')).text();
    expect(conSesion).toMatch(/id="btn-iniciar" style="display: none;"/);
    expect(conSesion).toMatch(/style="display: block;" id="btn-perfil"/);
});

// El servidor manda: un localStorage «sucio» (usuario guardado pero sin sesión real) no deja visibles perfil y salir.
test('un localStorage obsoleto no muestra el perfil si el servidor no tiene sesión', async ({ page }) => {
    await page.addInitScript(() => window.localStorage.setItem('usuario', 'fantasma'));
    await page.goto('/juegos');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#btn-iniciar')).toBeVisible();
    await expect(page.locator('#btn-perfil')).toBeHidden();
    // ExploraApi lo limpió al recibir el 401 de /api/sesion.
    expect(await page.evaluate(() => window.localStorage.getItem('usuario'))).toBeNull();
});

// Iniciar sesión desde el propio portal actualiza la barra sin recargar.
test('iniciar sesión desde el portal actualiza la barra', async ({ page }) => {
    await page.goto('/juegos');
    // Se espera a que termine el GET /api/sesion inicial: si su 401 llegara DESPUÉS del inicio de sesión, ExploraApi
    // limpiaría la caché local y la barra volvería a «Iniciar sesión» (carrera irreal para una persona, real para un test).
    await page.waitForLoadState('networkidle');
    await page.locator('#btn-iniciar').click();
    await page.locator('#usuario').fill('usuario1');
    await page.locator('#contrasena').fill('password');
    await page.locator('#btnEnviarLogin').click();
    await expect(page.locator('#btn-perfil')).toBeVisible();
    await expect(page.locator('#btn-cerrar')).toBeVisible();
    await expect(page.locator('#btn-iniciar')).toBeHidden();
});

// Las seis fichas y el modal de «Ubica los puntos» siguen ahí; el pie muestra el año (script estático).
test('el portal conserva fichas, modal y año del pie', async ({ page }) => {
    await page.goto('/juegos');
    await expect(page.locator('.puzzle-piece')).toHaveCount(6);
    await expect(page.locator('#myModal')).toBeAttached();
    await expect(page.locator('#year')).toHaveText(String(new Date().getFullYear()));
});

// Las URL antiguas redirigen (301): /juegos/index.html (también la usan ~20 enlaces `../../index.html` de los juegos).
test('/juegos/index.html redirige a /juegos y /juegos/ responde 200', async ({ request, page }) => {
    const r = await request.get('/juegos/index.html?x=1', { maxRedirects: 0 });
    expect(r.status()).toBe(301);
    const destino = new URL(r.headers()['location'], 'http://x');
    expect(destino.pathname).toBe('/juegos');
    expect(destino.search).toBe('?x=1');

    expect((await request.get('/juegos/')).status()).toBe(200);

    // Un enlace `../../index.html` desde una página de juego termina en el portal.
    await page.goto('/juegos/juegos/puzzle/');
    await page.goto('/juegos/index.html');
    await expect(page).toHaveURL(/\/juegos$/);
    await expect(page.locator('.puzzle-piece')).toHaveCount(6);
});

// Carpetas sin index no se listan (403 en Apache, 404 en el router de desarrollo).
test('las carpetas sin index no se listan', async ({ request }) => {
    for (const ruta of ['/juegos/js/', '/miPerfil/']) {
        const r = await request.get(ruta);
        expect([403, 404]).toContain(r.status());
    }
});
