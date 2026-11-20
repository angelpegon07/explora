// @ts-check
import { defineConfig } from '@playwright/test';

/**
 * Configuración de las pruebas de navegador (Playwright) de Explora.
 *
 * Las pruebas recorren el sitio estático conectado a la API Laravel a través del router de desarrollo
 * (`servidor-desarrollo.php`, docroot `laravel/public`), que sirve sitio y API desde el MISMO origen (la sesión es una cookie SameSite=Lax con CSRF).
 *
 * Navegador: el Chromium que descarga Playwright (`npx playwright install chromium`, queda en
 * ~/Library/Caches/ms-playwright, FUERA del repositorio). Antes se usó el Google Chrome del sistema
 * (`channel: 'chrome'`), pero depender de una instalación externa hizo fallar las pruebas en cuanto ese Chrome
 * desapareció del equipo. VERSIÓN FIJADA en 1.60.0: Playwright 1.65 ya no soporta macOS 13 con ningún navegador
 * («does not support chromium on mac13»); al actualizar, comprobar antes que el sistema operativo sea compatible.
 *
 * Ejecución: `npm run test:e2e` (o `npx playwright test`) desde `laravel/`.
 */
export default defineConfig({
    testDir: 'tests/Browser',

    // Un solo trabajador y sin paralelismo: las pruebas comparten la misma base de datos de prueba y la misma
    // cuenta (usuario1), de modo que ejecutarlas a la vez provocaría interferencias.
    workers: 1,
    fullyParallel: false,

    // Sin reintentos: un fallo intermitente debe verse, no ocultarse.
    retries: 0,
    reporter: 'list',

    // Prepara (limpia y siembra) y deshace las filas de prueba en explora_test. Ver tests/Browser/fixtures.php.
    globalSetup: './tests/Browser/global-setup.js',
    globalTeardown: './tests/Browser/global-teardown.js',

    use: {
        baseURL: 'http://127.0.0.1:8090',
        trace: 'retain-on-failure',
    },

    webServer: {
        command: `php -S 127.0.0.1:8090 -t ${process.env.EXPLORA_RAIZ_SITIO || 'public'} servidor-desarrollo.php`,
        // Directorio del proyecto Laravel (laravel/); desde la Fase 9 el documento raíz del sitio es su carpeta public/.
        cwd: import.meta.dirname,
        url: 'http://127.0.0.1:8090/index.html',

        // NUNCA reutilizar un servidor ya levantado: podría apuntar a otra base de datos (p. ej. la de desarrollo)
        // y las pruebas escribirían en datos reales. Siempre se arranca uno propio con las bases de prueba.
        reuseExistingServer: false,
        timeout: 60000,
        env: {
            DB_DATABASE: 'explora_test',
            PEDIGITAL_DB_DATABASE: 'pedigital_test',
            // El router se niega a atender /api si las bases no acaban en «_test».
            EXPLORA_SOLO_BD_PRUEBA: '1',
            SESSION_DRIVER: 'file',

            // CACHE_DRIVER=array: cada petición de `php -S` es un proceso PHP nuevo, así que la caché en memoria
            // empieza vacía en cada una. De ese modo los límites de intentos (throttle) NO se acumulan entre
            // pruebas y no provocan 429 espurios. Los límites ya se verifican en las pruebas de PHPUnit.
            CACHE_DRIVER: 'array',
            APP_DEBUG: 'false',

            // El servidor integrado atiende una petición a la vez; con varios workers una ficha pesada (~10 MB)
            // no bloquea al resto de la página.
            PHP_CLI_SERVER_WORKERS: '4',
        },
    },
});
