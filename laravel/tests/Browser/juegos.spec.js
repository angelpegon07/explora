// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Guardado de resultados y ranking de los juegos, conectados a /api/resultados y /api/ranking.
 * Las filas creadas llevan el prefijo «E2E-» y las borra fixtures.php (setup y teardown).
 */

const COMPLETA = '/juegos/juegos/completa/index.html';
const BANDERAS = '/juegos/juegos/banderas-color/index.html';

// Comprueba el flujo de invitado: guardar resultado -> POST 201 -> el ranking refleja al jugador.
test('invitado guarda un resultado (201) y aparece en el ranking', async ({ page }) => {
    await page.goto(COMPLETA);
    await page.evaluate(() => {
        // @ts-ignore función global del juego
        guardar_resultado('Completa la oración', 30, 95, 'Principiante', 'E2E-Cat-Completa');
    });
    await expect(page.locator('#modal_guardar_resultado')).toBeVisible();
    await page.locator('#nombre_jugador').fill('E2E-Jugador Uno');
    await page.locator('#grado_jugador').selectOption('5');
    await page.locator('#avatar_1').click();

    const post = page.waitForResponse((r) => r.url().endsWith('/api/resultados') && r.request().method() === 'POST');
    await page.locator('#btnGuardar').click();
    expect((await post).status()).toBe(201);
    await expect(page.locator('#ranking_container')).toContainText('E2E-Jugador');
});

// Comprueba que ante un error del servidor el botón no queda bloqueado en «Guardando…» y el jugador puede reintentar.
test('si /api/resultados falla (500) el botón se vuelve a habilitar', async ({ page }) => {
    await page.route('**/api/resultados', (route) =>
        route.fulfill({ status: 500, contentType: 'application/json', body: '{"message":"Error"}' }));
    await page.goto(COMPLETA);
    await page.evaluate(() => {
        // @ts-ignore función global del juego
        guardar_resultado('Completa la oración', 30, 95, 'Principiante', 'E2E-Cat-Completa');
    });
    await expect(page.locator('#modal_guardar_resultado')).toBeVisible();
    await page.locator('#nombre_jugador').fill('E2E-Jugador Dos');
    await page.locator('#grado_jugador').selectOption('5');
    await page.locator('#avatar_1').click();

    const post = page.waitForResponse((r) => r.url().endsWith('/api/resultados'));
    await page.locator('#btnGuardar').click();
    await post;
    await expect(page.locator('#btnGuardar')).toBeEnabled();
    await expect(page.locator('#btnGuardar')).not.toContainText('Guardando');
    // El aviso debe verse DENTRO del modal (un Swal quedaba detrás de él y no se veía).
    await expect(page.locator('#aviso_guardar')).toBeVisible();
});

/** Casos de cierre del modal: uno por versión de Bootstrap. */
const CIERRES = [
    { nombre: 'completa (Bootstrap 5)', url: COMPLETA, args: ['Completa la oración', 30, 95, 'Principiante', 'E2E-Cat-Completa'] },
    { nombre: 'banderas-color (Bootstrap 4)', url: BANDERAS, args: ['Pinta la bandera', 3, 40, 'Normal', 'E2E-Cat-Banderas'] },
];

for (const caso of CIERRES) {
    // Comprueba que cerrar el modal no deja la pantalla bloqueada (fondo oscuro ni body con scroll deshabilitado).
    test(`cerrar el modal limpia backdrop y modal-open: ${caso.nombre}`, async ({ page }) => {
        await page.goto(caso.url);
        await page.evaluate((a) => {
            // @ts-ignore función global del juego
            guardar_resultado(...a);
        }, caso.args);
        const modal = page.locator('#modal_guardar_resultado');
        await expect(modal).toBeVisible();
        await modal.getByRole('button', { name: /cerrar/i }).click();
        await expect(modal).toHaveCount(0);
        await expect(page.locator('.modal-backdrop')).toHaveCount(0);
        await expect(page.locator('body')).not.toHaveClass(/modal-open/);
    });
}
