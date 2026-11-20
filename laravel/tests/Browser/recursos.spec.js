// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Ningún recurso estático (CSS, JS, imágenes, fuentes) de las páginas principales y de cada juego debe dar 4xx/5xx.
 * Protege las rutas compartidas de librerías y los WebP que sustituyeron a los GIF.
 */
const PAGINAS = [
    '/', '/climatico', '/coordilleras', '/etnias', '/parques_naturales', '/vertientes', '/perfil', '/juegos',
    '/juegos/juegos/arrastra/', '/juegos/juegos/banderas-color/',
    '/juegos/juegos/completa/', '/juegos/juegos/juego_ahorcado/',
    '/juegos/juegos/laberinto/', '/juegos/juegos/puzzle/', '/juegos/juegos/sopa_letras/',
];

for (const ruta of PAGINAS) {
    // Comprueba que todas las peticiones del mismo origen (salvo la API de sesión sin login) responden bien.
    test(`recursos de ${ruta} sin errores`, async ({ page }) => {
        /** @type {string[]} */
        const rotos = [];
        page.on('response', (r) => {
            const u = new URL(r.url());
            if (u.pathname.startsWith('/api/')) return;
            // Defecto previo conocido: laberinto referencia un PNG que nunca existió en el repositorio (el JS lo reemplaza).
            if (u.pathname.endsWith('/laberinto/css/perdio_gano.png')) return;
            if (r.status() >= 400) rotos.push(`${r.status()} ${u.pathname}`);
        });
        await page.goto(ruta);
        await page.waitForLoadState('networkidle');
        expect(rotos).toEqual([]);
    });
}
