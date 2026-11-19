// @ts-check
import { execFileSync } from 'node:child_process';
import path from 'node:path';

/**
 * Ejecuta una acción de `tests/Browser/fixtures.php` desde la carpeta `laravel/`.
 * Lanza una excepción (y por tanto aborta toda la suite) si el proceso termina con un código distinto de 0.
 *
 * @param {'sembrar'|'limpiar'} accion Acción de los fixtures.
 */
function fixtures(accion) {
    execFileSync('php', ['tests/Browser/fixtures.php', accion], {
        cwd: path.resolve(import.meta.dirname, '../..'),
        stdio: 'inherit',
    });
}

/**
 * globalSetup de Playwright: limpia restos de una ejecución anterior interrumpida y siembra los datos E2E.
 * Es idempotente: se puede repetir sin efectos acumulados.
 */
export default async function globalSetup() {
    fixtures('limpiar');
    fixtures('sembrar');
}
