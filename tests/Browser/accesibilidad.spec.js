// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Accesibilidad por teclado (Fase 17F): lo que las pruebas de PHP no pueden comprobar porque necesita un navegador real.
 * Cubre el enlace «Saltar al contenido», los role="button" operables con Enter/Espacio (teclado.js),
 * el menú de accesibilidad (aria-expanded y cierre con Escape) y el anillo de foco visible.
 */

test('el enlace «Saltar al contenido» es el primer foco y lleva al contenido principal', async ({ page }) => {
    await page.goto('/juegos');
    await page.keyboard.press('Tab');
    const enlace = page.locator('.saltar-contenido');
    await expect(enlace).toBeFocused();
    await expect(enlace).toBeVisible();

    await page.keyboard.press('Enter');
    // El destino recibe el foco (id propio o, si no existe, el primer role="main").
    const enfocado = await page.evaluate(() => {
        const a = document.activeElement;
        return !!a && (a.id === 'contenido-principal' || a.closest('[role="main"], main') !== null || a.getAttribute('role') === 'main');
    });
    expect(enfocado).toBe(true);
});

test('un role="button" visible (indicador de scroll del portal) se activa con Enter y con Espacio', async ({ page }) => {
    // Las tarjetas `.card-juego` están dentro de un modal oculto: se prueba con el indicador, que es visible y hace scroll.
    for (const tecla of ['Enter', ' ']) {
        await page.goto('/juegos');
        const indicador = page.locator('.scroll-indicator[role="button"]');
        await indicador.focus();
        await expect(indicador).toBeFocused();
        const antes = await page.evaluate(() => window.scrollY);
        await page.keyboard.press(tecla);
        await expect.poll(() => page.evaluate(() => window.scrollY), { message: `scroll tras ${JSON.stringify(tecla)}` }).toBeGreaterThan(antes);
    }
});

test('el menú de accesibilidad actualiza aria-expanded y se cierra con Escape devolviendo el foco', async ({ page }) => {
    await page.goto('/');
    const boton = page.locator('#btn_menu_accesibilidad');
    await expect(boton).toHaveAttribute('aria-label', /accesibilidad/i);
    await expect(boton).toHaveAttribute('aria-expanded', 'false');

    await boton.click();
    await expect(boton).toHaveAttribute('aria-expanded', 'true');

    await page.keyboard.press('Escape');
    await expect(boton).toHaveAttribute('aria-expanded', 'false');
    await expect(boton).toBeFocused();
});

test('el foco por teclado en el botón del menú de accesibilidad es visible (no outline:none)', async ({ page }) => {
    await page.goto('/');
    const boton = page.locator('#btn_menu_accesibilidad');
    // Una pulsación de teclado previa hace que el navegador trate el foco siguiente como «por teclado» (:focus-visible).
    await page.keyboard.press('Tab');
    await boton.focus();
    await expect(boton).toBeFocused();
    const contorno = await boton.evaluate((el) => {
        const e = getComputedStyle(el);
        return { estilo: e.outlineStyle, ancho: parseFloat(e.outlineWidth) };
    });
    expect(contorno.estilo).not.toBe('none');
    expect(contorno.ancho).toBeGreaterThan(0);
});

test('la portada permite hacer zoom (el viewport no lo bloquea)', async ({ page }) => {
    await page.goto('/');
    const viewport = await page.locator('meta[name="viewport"]').getAttribute('content');
    expect(viewport ?? '').not.toMatch(/user-scalable\s*=\s*(no|0)|maximum-scale\s*=\s*1(\.0)?\b/i);
});

test('el manifest es accesible y sus iconos se descargan', async ({ page, request }) => {
    await page.goto('/');
    const href = await page.locator('link[rel="manifest"]').getAttribute('href');
    expect(href).toBeTruthy();
    const respuesta = await request.get(/** @type {string} */ (href));
    expect(respuesta.ok()).toBe(true);
    const manifest = JSON.parse(await respuesta.text());
    for (const icono of manifest.icons) {
        const r = await request.get(new URL(icono.src, /** @type {string} */ (href)).toString());
        expect(r.ok(), icono.src).toBe(true);
    }
});
