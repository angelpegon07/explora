// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Prueba de HUMO de `/lineaTiempo/` y `/ViajaPED/`, migradas a Blade en la Fase 15.
 *
 * Por cada página: la URL canónica (con barra final) carga sin `pageerror`, su JSON de datos (`eventos.json` / `data.json`)
 * responde 200 al pedirlo con una ruta RELATIVA (como hace su script.js: solo resuelve con la barra final) y la página
 * pinta su contenido dinámico. Además: la forma sin barra y `/index.html` redirigen (301) a la canónica, y el menú circular
 * de la portada y de los mapas enlaza a ellas.
 */
const PAGINAS = [
    { slug: 'lineaTiempo', datos: 'eventos.json', titulo: 'Línea de Tiempo Histórica' },
    { slug: 'ViajaPED', datos: 'data.json', titulo: 'Épocas Históricas' },
];

for (const { slug, datos, titulo } of PAGINAS) {
    test(`${slug}: carga sin errores de JS y su JSON responde 200`, async ({ page }) => {
        /** @type {string[]} */
        const errores = [];
        page.on('pageerror', (e) => errores.push(e.message));

        const r = await page.goto(`/${slug}/`);
        expect(r?.status()).toBe(200);
        await expect(page).toHaveTitle(titulo);
        await page.waitForLoadState('networkidle');

        const estado = await page.evaluate(async (ruta) => (await fetch(ruta)).status, datos);
        expect(estado).toBe(200);
        expect(errores).toEqual([]);
    });

    test(`${slug}: la forma sin barra y /index.html redirigen a la URL canónica`, async ({ request }) => {
        for (const ruta of [`/${slug}`, `/${slug}/index.html`]) {
            const r = await request.get(`${ruta}?x=1`, { maxRedirects: 0 });
            expect(r.status(), ruta).toBe(301);
            const destino = new URL(r.headers()['location'], 'http://x');
            expect(destino.pathname, ruta).toBe(`/${slug}/`);
            expect(destino.search, ruta).toBe('?x=1');
        }
    });
}

// lineaTiempo construye la línea con eventos.json: debe haber al menos un periodo pintado y el mensaje de carga desaparece.
test('lineaTiempo: pinta los periodos desde eventos.json', async ({ page }) => {
    await page.goto('/lineaTiempo/');
    await expect(page.locator('#timeline .loading-message')).toHaveCount(0);
    expect(await page.locator('#timeline > *').count()).toBeGreaterThan(0);
});

// ViajaPED: las cinco épocas están en la portada y al elegir una, script.js carga data.json y muestra sus detalles.
test('ViajaPED: las 5 épocas se pintan y al elegir una aparecen sus detalles', async ({ page }) => {
    // La máquina del tiempo vuela hasta el centro con una animación de varios segundos antes de mostrar los detalles.
    test.setTimeout(90000);
    await page.goto('/ViajaPED/');
    await expect(page.locator('.era-item')).toHaveCount(5);
    // Las épocas giran en círculo (animación CSS continua): Playwright nunca las ve «estables» y un clic por coordenadas falla,
    // así que se despacha el evento `click` directamente sobre el elemento (el mismo comportamiento que el original).
    await page.locator('.era-item[data-era="prehistoria"]').dispatchEvent('click');
    // `display: block` (no `toBeVisible`): el contenedor lo rellena y dimensiona el JS después, y aquí solo importa que se abra.
    await expect(page.locator('#era-details-section')).toHaveCSS('display', 'block', { timeout: 45000 });
});

// Comprueba que el menú circular enlaza a las URL canónicas (con barra, en otra pestaña) y que esas URL responden 200.
for (const origen of ['/', '/climatico']) {
    test(`menú circular de ${origen} enlaza a lineaTiempo y ViajaPED`, async ({ page, request }) => {
        await page.goto(origen);
        for (const [id, slug] of [['btn_menu_linea_tiempo', 'lineaTiempo'], ['btn_menu_viaja_ped', 'ViajaPED']]) {
            const enlace = page.locator(`#${id}`);
            await expect(enlace).toHaveAttribute('target', '_blank');
            const href = (await enlace.getAttribute('href')) ?? '';
            expect(new URL(href, page.url()).pathname).toBe(`/${slug}/`);
            expect((await request.get(new URL(href, page.url()).href)).status()).toBe(200);
        }
    });
}
