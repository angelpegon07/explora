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
    // Fase 15: `conexion*.php` ya no existen (404 por inexistentes).
    '/mapplic/php/conexion.php',
    '/mapplic/php/conexion_pedigital.php',
    '/juegos/php/conexion.php',
    // El chat con IA queda fuera de la Fase 15 (el archivo sigue en el repositorio): el router de desarrollo NUNCA lo ejecuta
    // (usa credenciales de MySQL escritas en el código) y NO es un servicio «retirado»: 404, no 410.
    '/mapplic/php/chat.php',
    '/mapplic/php/chat%20copy.php',
    // PATH_INFO: `php -S` ejecutaría el script legado si el router devolviera false.
    '/mapplic/php/consultarInfo.php/x.css',
    // Mayúsculas: el sistema de archivos de macOS no distingue y podría llegar al script.
    '/MAPPLIC/PHP/CONSULTARINFO.PHP/x.css',
];

// Fase 15: sus archivos ya no existen; el router de desarrollo sigue dando el 410 (en Apache lo da la ruta de Laravel).
for (const ruta of SUSTITUIDOS) {
    test(`410 JSON en ${ruta.toUpperCase()} (mayúsculas)`, async ({ request }) => {
        const r = await request.get(ruta.toUpperCase());
        expect(r.status()).toBe(410);
    });
}

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

for (const ruta of ['/', '/perfil', '/juegos', '/juegos/', '/lineaTiempo/', '/ViajaPED/']) {
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
