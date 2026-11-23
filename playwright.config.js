// @ts-check
import { defineConfig } from '@playwright/test';

/**
 * Configuración de las pruebas de navegador (Playwright) de Explora.
 *
 * Las pruebas recorren el sitio estático conectado a la API Laravel a través del router de desarrollo
 * (`servidor-desarrollo.php`, docroot `public`), que sirve sitio y API desde el MISMO origen (la sesión es una cookie SameSite=Lax con CSRF).
 *
 * Navegador: el Chromium que descarga Playwright (`npx playwright install chromium`, queda en
 * ~/Library/Caches/ms-playwright, FUERA del repositorio). Antes se usó el Google Chrome del sistema
 * (`channel: 'chrome'`), pero depender de una instalación externa hizo fallar las pruebas en cuanto ese Chrome
 * desapareció del equipo. VERSIÓN FIJADA en 1.60.0: Playwright 1.65 ya no soporta macOS 13 con ningún navegador
 * («does not support chromium on mac13»); al actualizar, comprobar antes que el sistema operativo sea compatible.
 *
 * Ejecución: `npm run test:e2e` (o `npx playwright test`) desde la raíz del repositorio.
 *
 * OPT-IN (Fase 16C): con `EXPLORA_BASE_URL=http://127.0.0.1:<puerto>` las pruebas se lanzan contra un servidor YA levantado (p. ej. la
 * pila Apache + PHP-FPM del ensayo de despliegue, DESPLIEGUE en GUIA_DESPLIEGUE.md) en vez de arrancar `php -S`. Sin la variable
 * el comportamiento es EXACTAMENTE el de siempre. Con ella: (a) se omiten los fixtures (globalSetup/globalTeardown), porque siembran
 * `explora_test` y ese servidor tiene su propia base; por tanto solo valen los specs que no dependen de filas sembradas
 * (bloqueos, recursos, mapas, sesion-ficha-quiz); (b) la URL debe ser de la máquina local (127.0.0.1/localhost): las pruebas escriben
 * datos y nunca deben apuntar a un servidor real; (c) el servidor debe usar bases `*_test`/`*_migtest` y límites de uso altos
 * (API_POR_MINUTO y RESULTADOS_POR_MINUTO), o el throttle por IP dará 429 falsos.
 */
const baseUrlExterna = process.env.EXPLORA_BASE_URL || '';
if (baseUrlExterna && !/^http:\/\/(127\.0\.0\.1|localhost|\[::1\]):\d+\/?$/.test(baseUrlExterna)) {
    throw new Error('EXPLORA_BASE_URL debe ser http://127.0.0.1:<puerto> (o localhost): las pruebas escriben datos y no deben apuntar a un servidor real.');
}

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
    // (Se omiten con EXPLORA_BASE_URL: ver el comentario de arriba.)
    globalSetup: baseUrlExterna ? undefined : './tests/Browser/global-setup.js',
    globalTeardown: baseUrlExterna ? undefined : './tests/Browser/global-teardown.js',

    use: {
        baseURL: baseUrlExterna || 'http://127.0.0.1:8090',
        trace: 'retain-on-failure',
    },

    webServer: baseUrlExterna ? undefined : {
        command: `php -S 127.0.0.1:8090 -t ${process.env.EXPLORA_RAIZ_SITIO || 'public'} servidor-desarrollo.php`,
        // Directorio del proyecto Laravel (raíz del repositorio); desde la Fase 9 el documento raíz del sitio es su carpeta public/.
        cwd: import.meta.dirname,
        url: 'http://127.0.0.1:8090/',

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
