// @ts-check
/**
 * Build ligero del sitio estático (Fase 7B).
 *
 * Copia el sitio de la raíz del repositorio a `../dist/` y minifica los JS y CSS propios con esbuild (el motor de
 * Vite, vía `transformWithEsbuild`). NO empaqueta ni convierte nada a módulos: los scripts son clásicos con jQuery
 * global, así que se conservan nombres de archivo, rutas y orden de carga. Por eso las páginas HTML no cambian.
 *
 * Qué NO entra en `dist/`: PHP legado (chat, corrector y conexiones: se quedan en el servidor), `laravel/`,
 * documentación, dumps SQL, `node_modules`, `.git` y archivos `.md`. Despliegue: superponer `dist/` sobre la
 * raíz del sitio (por ejemplo `rsync -a dist/ /var/www/explora/`), sin reemplazar `laravel/` ni los PHP.
 *
 * Uso: `npm run build:sitio` (desde `laravel/`). Falla si algún JS/CSS no se puede minificar: nunca publica a medias.
 */
import { transformWithEsbuild } from 'vite';
import { cpSync, existsSync, mkdirSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, extname, join, relative, resolve } from 'node:path';

const RAIZ = resolve(import.meta.dirname, '..');
const SALIDA = join(RAIZ, 'dist');

/** Carpetas que nunca se copian (relativas a la raíz o por nombre en cualquier nivel). */
const CARPETAS_EXCLUIDAS = new Set(['.git', 'node_modules', 'laravel', 'dist', 'DOCUMENTACION_MEJORAMIENTO', 'php', 'test-results']);
/** Extensiones que nunca se copian. */
const EXTENSIONES_EXCLUIDAS = new Set(['.php', '.sql', '.gz', '.zip', '.md', '.sh', '.lock', '.log', '.bak']);

let leidos = 0;
let escritos = 0;
let minificados = 0;

/**
 * Recorre un directorio y procesa cada archivo en `dist/`.
 * @param {string} dir Directorio absoluto a recorrer.
 * @returns {Promise<void>}
 */
async function recorrer(dir) {
    for (const nombre of readdirSync(dir)) {
        const origen = join(dir, nombre);
        const rel = relative(RAIZ, origen);
        const info = statSync(origen);
        if (info.isDirectory()) {
            if (CARPETAS_EXCLUIDAS.has(nombre)) continue;
            await recorrer(origen);
            continue;
        }
        if (nombre === '.DS_Store' || nombre === '.env' || nombre.startsWith('conexion')) continue;
        if (EXTENSIONES_EXCLUIDAS.has(extname(nombre).toLowerCase())) continue;
        // `.htaccess` de carpetas excluidas no llega aquí; el de la raíz sí (necesario para Apache).
        const destino = join(SALIDA, rel);
        mkdirSync(dirname(destino), { recursive: true });
        leidos += info.size;
        const ext = extname(nombre).toLowerCase();
        const yaMin = /\.min\.(js|css)$/i.test(nombre);
        if ((ext === '.js' || ext === '.css') && !yaMin) {
            const fuente = readFileSync(origen, 'utf8');
            const { code } = await transformWithEsbuild(fuente, origen, {
                minify: true,
                loader: ext === '.js' ? 'js' : 'css',
                // Los scripts clásicos no deben cambiar de semántica: sin target, sin renombrar globales.
                legalComments: 'inline',
            });
            writeFileSync(destino, code);
            minificados++;
        } else {
            cpSync(origen, destino);
        }
        escritos += statSync(destino).size;
    }
}

if (existsSync(SALIDA)) rmSync(SALIDA, { recursive: true, force: true });
await recorrer(RAIZ);
console.log(`dist/ listo: ${(leidos / 1e6).toFixed(1)} MB -> ${(escritos / 1e6).toFixed(1)} MB (${minificados} JS/CSS minificados)`);
