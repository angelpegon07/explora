// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Prueba de HUMO de las 7 páginas de juego migradas a Blade (Fase 13).
 *
 * Por cada juego: la URL canónica (con barra final) carga, no lanza ningún error de JavaScript (`pageerror`), y su fichero
 * de datos principal responde 200 al pedirlo con una ruta RELATIVA a la página (`fetch('<fichero>')` desde la propia página,
 * como hace el juego). Eso demuestra que la barra final y las rutas relativas siguen funcionando. Varios juegos solo piden
 * su JSON al elegir nivel o categoría, por eso se pide desde la página en vez de esperar a que lo haga el juego. Además: la forma sin barra y `/index.html` redirigen (301) a la canónica.
 */
const JUEGOS = [
    { slug: 'laberinto', datos: 'js/preguntas.json' },
    { slug: 'banderas-color', datos: 'json_banderas.json' },
    { slug: 'puzzle', datos: 'categorias.json' },
    { slug: 'sopa_letras', datos: 'datos.json' },
    { slug: 'juego_ahorcado', datos: 'pistas.json' },
    { slug: 'completa', datos: 'completa.json' },
    // arrastra no usa JSON: sus datos son el script data.js (se comprueba que la página lo cargó con 200).
    { slug: 'arrastra', datos: 'data.js' },
];

/**
 * Errores de JS HEREDADOS que ya ocurrían con el HTML original (la migración no los introduce y no se corrigen en esta fase).
 * - arrastra/game.js llama a `panzoom.on(...)`, método que Panzoom 4.x no tiene.
 * - sopa_letras carga tag-it.js, que necesita `$.widget` (jQuery UI), y la página no carga jQuery UI.
 * @type {Record<string, string[]>}
 */
const ERRORES_HEREDADOS = { arrastra: ['panzoom.on is not a function'], sopa_letras: ['$.widget is not a function'] };

for (const { slug, datos } of JUEGOS) {
    test(`juego ${slug}: carga sin errores de JS y su fichero de datos responde 200`, async ({ page }) => {
        /** @type {string[]} */
        const errores = [];
        page.on('pageerror', (e) => errores.push(e.message));

        const r = await page.goto(`/juegos/juegos/${slug}/`);
        expect(r?.status()).toBe(200);
        await page.waitForLoadState('networkidle');

        // Ruta relativa a la URL de la página: solo resuelve si la URL canónica lleva la barra final.
        const estado = await page.evaluate(async (ruta) => (await fetch(ruta)).status, datos);
        expect(estado).toBe(200);
        expect(errores.filter((m) => !(ERRORES_HEREDADOS[slug] ?? []).includes(m))).toEqual([]);
    });

    test(`juego ${slug}: la forma sin barra y /index.html redirigen a la URL canónica`, async ({ request }) => {
        for (const ruta of [`/juegos/juegos/${slug}`, `/juegos/juegos/${slug}/index.html`]) {
            const r = await request.get(`${ruta}?x=1`, { maxRedirects: 0 });
            expect(r.status(), ruta).toBe(301);
            const destino = new URL(r.headers()['location'], 'http://x');
            expect(destino.pathname, ruta).toBe(`/juegos/juegos/${slug}/`);
            expect(destino.search, ruta).toBe('?x=1');
        }
    });
}
