// @ts-check
import { execFileSync } from 'node:child_process';
import path from 'node:path';

/**
 * globalTeardown de Playwright: elimina los datos E2E y restaura el perfil (avatar) del usuario 1,
 * que las pruebas de perfil modifican. Falla si `fixtures.php limpiar` termina con un código distinto de 0.
 */
export default async function globalTeardown() {
    execFileSync('php', ['tests/Browser/fixtures.php', 'limpiar'], {
        cwd: path.resolve(import.meta.dirname, '../..'),
        stdio: 'inherit',
    });
}
