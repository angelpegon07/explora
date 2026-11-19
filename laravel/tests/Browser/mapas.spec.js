// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Páginas de mapas temáticos (Mapplic). Caracterización previa a fusionar las variantes de `mapplic*.js`:
 * cada página debe cargar sin errores de JS y abrir la ficha de un lugar al elegirlo en la lista.
 */
const PAGINAS = ['index', 'climatico', 'coordilleras', 'etnias', 'parques_naturales', 'vertientes'];

for (const nombre of PAGINAS) {
    // Comprueba que la página carga, pinta la lista de lugares y que elegir uno muestra su contenido en el tooltip.
    test(`mapa ${nombre}: carga sin errores y abre un lugar`, async ({ page }) => {
        /** @type {string[]} */
        const errores = [];
        page.on('pageerror', (e) => errores.push(e.message));
        await page.goto(`/${nombre}.html`);
        const lugares = page.locator('.mapplic-list-location a');
        await expect(lugares.first()).toBeAttached({ timeout: 15000 });
        await page.evaluate(() => /** @type {any} */ (window).jQuery('.mapplic-list-location a').first().trigger('click'));
        const tooltip = page.locator('.mapplic-tooltip-content');
        await expect(tooltip.first()).toBeAttached();
        const info = await page.evaluate(() => ({
            contenidoTexto: !!document.getElementById('contenido_texto'),
            titulosMayus: (() => { const h = document.querySelector('.mapplic-list-location a h4'); return h ? getComputedStyle(h).textTransform : null; })(),
            imagenesAttr: Array.from(document.querySelectorAll('[link-imagenes]')).length,
        }));
        console.log(nombre, JSON.stringify(info));
        expect(errores).toEqual([]);
    });
}
