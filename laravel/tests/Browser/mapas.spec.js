// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Páginas de mapas temáticos (Mapplic). Caracterización previa a fusionar las variantes de `mapplic*.js`:
 * cada página debe cargar sin errores de JS y abrir la ficha de un lugar al elegirlo en la lista.
 */
const PAGINAS = ['index', 'climatico', 'coordilleras', 'etnias', 'parques_naturales', 'vertientes'];

/**
 * URL de cada página: la portada sigue siendo un HTML estático (Fase 11 la migrará); los cinco mapas temáticos son
 * rutas de Laravel sin extensión (Fase 10).
 *
 * @param {string} nombre Nombre de la página.
 * @returns {string} Ruta a visitar.
 */
const ruta = (nombre) => (nombre === 'index' ? '/index.html' : `/${nombre}`);

for (const nombre of PAGINAS) {
    // Comprueba que la página carga, pinta la lista de lugares y que elegir uno muestra su contenido en el tooltip.
    test(`mapa ${nombre}: carga sin errores y abre un lugar`, async ({ page }) => {
        /** @type {string[]} */
        const errores = [];
        page.on('pageerror', (e) => errores.push(e.message));
        await page.goto(ruta(nombre));
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

/**
 * Navegación del modal «Seleccione el mapa»: seleccionarTipoMapa() (general.js, compartida con index.html) sigue
 * apuntando a `*.html`; la redirección 301 debe llevar a la ruta nueva sin extensión.
 */
test('el modal de tipo de mapa navega: climatico -> etnias termina en /etnias', async ({ page }) => {
    await page.goto('/climatico');
    await page.evaluate(() => /** @type {any} */ (window).seleccionarTipoMapa('etnias'));
    await page.waitForURL('**/etnias');
    expect(new URL(page.url()).pathname).toBe('/etnias');
    await expect(page).toHaveTitle('Etnias de Colombia');
});

/**
 * Las URL antiguas `*.html` redirigen (301) a la ruta nueva y conservan el hash del deeplinking de Mapplic.
 */
test('la URL antigua /vertientes.html redirige a /vertientes', async ({ page }) => {
    await page.goto('/vertientes.html#x');
    expect(new URL(page.url()).pathname).toBe('/vertientes');
    expect(new URL(page.url()).hash).toBe('#x');
    await expect(page).toHaveTitle('Hidrografía de Colombia');
});

/**
 * Solo climatico tiene el panel «Dato de interés»: el modal de pantalla completa lo abre al pulsar «Seguir en modo
 * normal» y la X lo cierra (right: 20% abierto, -800px cerrado).
 */
test('climatico: el panel de dato de interés abre y cierra', async ({ page }) => {
    await page.goto('/climatico');
    const panel = page.locator('#dato_interes');
    await expect(panel).toBeAttached();
    // Cerrado al cargar (CSS: right -600px, sin estilo en línea).
    expect(await panel.evaluate((el) => el.style.right)).toBe('');

    await page.evaluate(() => /** @type {any} */ (window).mostrarCerrarDatoInteres());
    expect(await panel.evaluate((el) => el.style.right)).toBe('20%');

    await page.locator('.btn_cerrar_dato_interes').dispatchEvent('click');
    expect(await panel.evaluate((el) => el.style.right)).toBe('-800px');

    // Los otros mapas no lo llevan.
    await page.goto('/coordilleras');
    await expect(page.locator('#dato_interes')).toHaveCount(0);
});

/**
 * Las imágenes del carrusel salen de la carpeta configurada: `multimedia/imagenes/` en climatico y la subcarpeta del
 * lugar (`carpeta`) bajo `maps_parques/imagenes_parques/` en etnias.
 */
test('verImagenes usa la carpeta de imágenes de cada mapa', async ({ page }) => {
    await page.goto('/climatico');
    const climatico = await page.evaluate(() => {
        const w = /** @type {any} */ (window);
        const b = document.createElement('button');
        b.setAttribute('link-imagenes', 'a.jpg,b.jpg');
        w.verImagenes(b);
        return Array.from(document.querySelectorAll('#carouselExampleIndicators img')).map((i) => i.getAttribute('src'));
    });
    expect(climatico.map((u) => new URL(/** @type {string} */ (u)).pathname)).toEqual(['/multimedia/imagenes/a.jpg', '/multimedia/imagenes/b.jpg']);

    await page.goto('/etnias');
    const etnias = await page.evaluate(() => {
        const w = /** @type {any} */ (window);
        const b = document.createElement('button');
        b.setAttribute('link-imagenes', 'a.jpg');
        b.setAttribute('carpeta', 'wayuu');
        w.verImagenes(b);
        return Array.from(document.querySelectorAll('#carouselExampleIndicators img')).map((i) => i.getAttribute('src'));
    });
    expect(etnias.map((u) => new URL(/** @type {string} */ (u)).pathname)).toEqual(['/maps_parques/imagenes_parques/wayuu/a.jpg']);
});
