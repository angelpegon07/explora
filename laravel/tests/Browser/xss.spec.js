// @ts-check
import { test, expect } from '@playwright/test';
import { iniciarSesionPorApi, vigilarDialogosNativos } from './ayudas.js';

/**
 * XSS almacenado: filas con HTML hostil sembradas directamente en la base (fixtures.php) deben mostrarse como
 * TEXTO literal, sin ejecutar `onerror` (que fijaría window.__xss).
 */

// Comprueba que el nombre del jugador del ranking se escapa: se ve el texto «<img» y no se ejecuta nada.
test('ranking muestra el HTML hostil como texto literal', async ({ page }) => {
    const dialogos = vigilarDialogosNativos(page);
    await page.goto('/juegos/juegos/completa/index.html');
    await page.evaluate(() => {
        // @ts-ignore función global del juego
        guardar_resultado('Ahorcado', 1, 10, 'Principiante', 'E2E-XSS');
    });
    await expect(page.locator('#ranking_container')).toContainText('<img');
    expect(await page.evaluate(() => /** @type {any} */ (window).__xss)).toBeUndefined();
    expect(dialogos).toEqual([]);
});

// Comprueba lo mismo para el historial del perfil, cuyo nombre de juego sembrado contiene HTML.
test('historial muestra el HTML hostil como texto literal', async ({ page }) => {
    const dialogos = vigilarDialogosNativos(page);
    await iniciarSesionPorApi(page);
    await page.goto('/miPerfil.html');
    await page.locator('#menu-perfil .nav-link', { hasText: /historial/i }).click();
    await expect(page.locator('#historial')).toContainText('E2E-<img');
    expect(await page.evaluate(() => /** @type {any} */ (window).__xss)).toBeUndefined();
    expect(dialogos).toEqual([]);
});
