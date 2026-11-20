// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Bloqueos del router de desarrollo: los PHP legados sustituidos por la API responden 410, lo privado 404 y
 * nada filtra rutas del sistema. Estas pruebas NO dependen del frontend y deben pasar desde el principio.
 */

const SUSTITUIDOS = [
    '/mapplic/php/iniciarSesion.php',
    '/mapplic/php/cambiarAvatar.php',
    '/mapplic/php/consultarInfo.php',
    '/mapplic/php/buscarPreguntas.php',
    '/juegos/php/guardar_resultado.php',
    '/juegos/php/consultar_ranking.php',
    '/juegos/php/consultar_historial.php',
];

const PRIVADOS = [
    '/laravel/.env',
    '/laravel/composer.json',
    '/.git/HEAD',
    '/DOCUMENTACION_MEJORAMIENTO/REGISTRO_CAMBIOS.md',
    '/mapplic/php/conexion.php',
    '/juegos/php/conexion.php',
    // PATH_INFO: `php -S` ejecutaría el script legado si el router devolviera false.
    '/mapplic/php/consultarInfo.php/x.css',
    // Mayúsculas: el sistema de archivos de macOS no distingue y podría llegar al script.
    '/MAPPLIC/PHP/CONSULTARINFO.PHP/x.css',
];

for (const ruta of SUSTITUIDOS) {
    // Comprueba que el PHP legado (inyectable y sin sesión) ya no se ejecuta: 410 con JSON de error, con GET y POST.
    test(`410 JSON en ${ruta}`, async ({ request }) => {
        for (const metodo of ['get', 'post'] /** @type {const} */) {
            const r = await request[metodo](ruta);
            expect(r.status(), `${metodo.toUpperCase()} ${ruta}`).toBe(410);
            const cuerpo = await r.text();
            expect(JSON.parse(cuerpo).status).toBe('error');
            expect(cuerpo).not.toContain('/Users/');
        }
    });
}

for (const ruta of PRIVADOS) {
    // Comprueba que archivos privados o scripts de conexión nunca se sirven ni se ejecutan: 404 sin rutas del sistema.
    test(`404 en ${ruta}`, async ({ request }) => {
        const r = await request.get(ruta);
        expect(r.status()).toBe(404);
        expect(await r.text()).not.toContain('/Users/');
    });
}

for (const ruta of ['/', '/juegos/index.html']) {
    // Comprueba que el router sigue sirviendo la portada (Laravel) y las páginas estáticas legítimas.
    test(`200 en ${ruta}`, async ({ request }) => {
        const r = await request.get(ruta);
        expect(r.status()).toBe(200);
        expect(await r.text()).not.toContain('/Users/');
    });
}

// Comprueba que la API está viva en el mismo origen y que, sin sesión, GET /api/sesion es 401 con JSON.
test('401 JSON en /api/sesion sin sesión', async ({ request }) => {
    const r = await request.get('/api/sesion', { headers: { Accept: 'application/json' } });
    expect(r.status()).toBe(401);
    const cuerpo = await r.text();
    expect(() => JSON.parse(cuerpo)).not.toThrow();
    expect(cuerpo).not.toContain('/Users/');
});
