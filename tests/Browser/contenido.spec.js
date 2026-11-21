// @ts-check
import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';

/**
 * Fase 16A: `/contenido/` (imágenes de las fichas que genera `php artisan explora:externalizar-imagenes`).
 *
 * No depende del sandbox ni de datos de la base: la prueba crea su PROPIO archivo dentro de una subcarpeta aleatoria de
 * `public/contenido/` (ignorada por git), comprueba lo que sirve el servidor y lo borra al terminar.
 *   - Las carpetas bajo `/contenido/` no se listan (404; Apache daría 404 por la regla de .htaccess o 403 por -Indexes).
 *   - Un PNG se sirve con 200 y `image/png`, con los mismos bytes que se escribieron.
 *   - Un archivo que NO es imagen (html, svg, txt) no se sirve aunque exista: 404.
 */
const RAIZ = path.resolve(import.meta.dirname, '../../public/contenido');
// PNG de 1x1 píxel (bytes reales: la cabecera PNG + datos mínimos).
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');

test('contenido: sirve una imagen creada por la prueba, no lista carpetas y no sirve lo que no es imagen', async ({ request }) => {
    const sub = `prueba-e2e-${crypto.randomBytes(4).toString('hex')}`;
    const dir = path.join(RAIZ, sub, 'ab');
    const creoRaiz = !fs.existsSync(RAIZ);
    try {
        fs.mkdirSync(dir, { recursive: true });
        fs.writeFileSync(path.join(dir, 'imagen.png'), PNG);
        fs.writeFileSync(path.join(dir, 'pagina.html'), '<script>alert(1)</script>');
        fs.writeFileSync(path.join(dir, 'dibujo.svg'), '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        fs.writeFileSync(path.join(dir, 'nota.txt'), 'texto');

        const img = await request.get(`/contenido/${sub}/ab/imagen.png`);
        expect(img.status()).toBe(200);
        expect(img.headers()['content-type']).toBe('image/png');
        expect(Buffer.from(await img.body()).equals(PNG)).toBe(true);

        for (const nombre of ['pagina.html', 'dibujo.svg', 'nota.txt']) {
            expect((await request.get(`/contenido/${sub}/ab/${nombre}`)).status(), nombre).toBe(404);
        }
        for (const carpeta of ['/contenido/', '/contenido', '/contenido/fichas/', `/contenido/${sub}/`, `/contenido/${sub}/ab/`, `/contenido/${sub}/ab`]) {
            const r = await request.get(carpeta);
            expect(r.status(), carpeta).toBe(404);
            expect(await r.text(), carpeta).not.toContain('imagen.png');
        }
    } finally {
        fs.rmSync(path.join(RAIZ, sub), { recursive: true, force: true });
        if (creoRaiz) fs.rmSync(RAIZ, { recursive: true, force: true });
    }
    expect(fs.existsSync(path.join(RAIZ, sub))).toBe(false);
});
