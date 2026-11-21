<?php

/**
 * Fixtures de las pruebas de navegador (Playwright) de Explora.
 *
 * Uso (desde `laravel/`):
 *
 *     php tests/Browser/fixtures.php sembrar   # respalda el perfil del usuario 1 e inserta filas E2E
 *     php tests/Browser/fixtures.php limpiar   # borra las filas E2E y restaura el perfil del usuario 1
 *     php tests/Browser/fixtures.php contar    # imprime cuántas filas E2E quedan (solo un número)
 *
 * Seguridad: el proceso FUERZA `explora_test` y `pedigital_test` en su entorno antes de arrancar Laravel y ABORTA
 * (código 1) si las conexiones `mysql` o `pedigital` no apuntan a bases acabadas en `_test` o no son locales.
 * Nunca imprime credenciales ni datos personales.
 *
 * Por qué se siembra directamente en la base: la API rechaza HTML en los campos de texto, así que la única forma de
 * probar que el frontend ESCAPA contenido hostil que ya esté guardado (XSS almacenado) es insertarlo por fuera.
 *
 * Ambas acciones son idempotentes.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// 1. Forzar las bases de prueba en TODAS las vías por las que Laravel lee el entorno (antes de arrancarlo).
foreach (['DB_DATABASE' => 'explora_test', 'PEDIGITAL_DB_DATABASE' => 'pedigital_test'] as $clave => $valor) {
    putenv("$clave=$valor");
    $_ENV[$clave] = $valor;
    $_SERVER[$clave] = $valor;
}

$raiz = dirname(__DIR__, 2);
require $raiz.'/vendor/autoload.php';
$app = require $raiz.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** Ruta del respaldo del perfil del usuario 1 (fuera del repositorio). */
const RESPALDO = 'explora_e2e_perfiles_usuario1.json';

/**
 * Termina el proceso con un mensaje (sin datos sensibles) y un código de salida.
 *
 * @param  string  $mensaje  Texto para la salida de error.
 * @param  int  $codigo  Código de salida.
 */
function terminar(string $mensaje, int $codigo = 1): never
{
    fwrite(STDERR, $mensaje.PHP_EOL);
    exit($codigo);
}

/**
 * Aborta si alguna de las conexiones no es una base de prueba local.
 *
 * @param  array<string,string>  $conexiones  Mapa nombre de conexión => cadena que debe aparecer.
 */
function verificarBasesDePrueba(array $conexiones): void
{
    foreach ($conexiones as $nombre) {
        $cfg = config("database.connections.$nombre");
        $bd = (string) ($cfg['database'] ?? '');
        $host = (string) ($cfg['host'] ?? '');
        if (! str_ends_with($bd, '_test')) {
            terminar("ABORTADO: la conexión '$nombre' no apunta a una base *_test.");
        }
        if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            terminar("ABORTADO: la conexión '$nombre' no es local.");
        }
    }
}

verificarBasesDePrueba(['mysql', 'pedigital']);

$ruta = sys_get_temp_dir().DIRECTORY_SEPARATOR.RESPALDO;

/** Condición SQL común que identifica las filas sembradas por estas pruebas. */
$esE2E = fn ($q) => $q->where('jugador', 'like', 'E2E-%')
    ->orWhere('categoria', 'like', 'E2E-%')
    ->orWhere('nombre_juego', 'like', 'E2E-%');

/**
 * Borra las filas E2E de resultado_juegos.
 */
$borrarE2E = function () use ($esE2E): void {
    DB::connection('mysql')->table('resultado_juegos')->where($esE2E)->delete();
};

$accion = $argv[1] ?? '';

switch ($accion) {
    case 'sembrar':
        // Respaldo del perfil del usuario 1 (solo si no hay ya uno: así repetir `sembrar` no pisa el original).
        if (! is_file($ruta)) {
            $filas = DB::connection('mysql')->table('perfiles_explora')->where('usuario_id', 1)->get()
                ->map(fn ($f) => (array) $f)->all();
            file_put_contents($ruta, json_encode($filas));
        }

        // Idempotencia: se parte siempre de cero antes de insertar.
        $borrarE2E();
        DB::connection('mysql')->table('resultado_juegos')->insert([
            // (a) Ranking con XSS en el nombre del jugador (invitado: id_usuario 0).
            [
                'nombre_juego' => 'Ahorcado', 'nivel' => 'Principiante', 'categoria' => 'E2E-XSS',
                'jugador' => 'E2E-<img src=x onerror="window.__xss=1">', 'avatar' => '3.png', 'grado' => '5',
                'puntaje' => 999, 'tiempo' => 1, 'id_usuario' => 0, 'fecha' => '01-01-2026 10:00:00',
            ],
            // (b) Historial del usuario 1 con XSS en el nombre del juego.
            [
                'nombre_juego' => 'E2E-<img src=x onerror="window.__xss=2">', 'nivel' => 'Principiante',
                'categoria' => 'E2E-XSS-HIST', 'jugador' => 'E2E-Usuario', 'avatar' => '1.png', 'grado' => '6',
                'puntaje' => 1, 'tiempo' => 1, 'id_usuario' => 1, 'fecha' => '01-01-2026 10:00:00',
            ],
        ]);
        echo 'Fixtures E2E sembrados.'.PHP_EOL;
        break;

    case 'limpiar':
        $borrarE2E();

        // Restaura el perfil del usuario 1 solo si existe un respaldo (borra lo que haya y reinserta lo respaldado).
        if (is_file($ruta)) {
            $filas = json_decode((string) file_get_contents($ruta), true);
            if (! is_array($filas)) {
                terminar('ABORTADO: el respaldo del perfil está dañado.');
            }
            DB::connection('mysql')->transaction(function () use ($filas): void {
                DB::connection('mysql')->table('perfiles_explora')->where('usuario_id', 1)->delete();
                foreach ($filas as $fila) {
                    DB::connection('mysql')->table('perfiles_explora')->insert($fila);
                }
            });
            unlink($ruta);
        }
        echo 'Fixtures E2E limpiados.'.PHP_EOL;
        break;

    case 'contar':
        echo DB::connection('mysql')->table('resultado_juegos')->where($esE2E)->count().PHP_EOL;
        break;

    default:
        terminar('Uso: php tests/Browser/fixtures.php sembrar|limpiar|contar', 2);
}
