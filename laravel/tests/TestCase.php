<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

/**
 * Base de todas las pruebas de Laravel. Añade la guarda de seguridad de {@see self::setUp()}.
 */
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Guarda de seguridad: las pruebas escriben datos, así que SIEMPRE exigen que las conexiones `mysql` y `pedigital`
     * apunten a bases *_test de un servidor local y que LEGACY_BASE_URL (el arnés del legado) sea también local.
     * Si no se cumple, aborta con una excepción antes de ejecutar cualquier prueba.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['mysql', 'pedigital'] as $conexion) {
            $cfg = config("database.connections.$conexion");
            if (! str_ends_with((string) $cfg['database'], '_test')) {
                throw new RuntimeException("Pruebas abortadas: la conexión '$conexion' no apunta a una base *_test.");
            }
            if (! in_array($cfg['host'], ['127.0.0.1', 'localhost', '::1'], true)) {
                throw new RuntimeException("Pruebas abortadas: la conexión '$conexion' no es local.");
            }
        }

        $legado = (string) env('LEGACY_BASE_URL', 'http://127.0.0.1:8081');
        if (! in_array(parse_url($legado, PHP_URL_HOST), ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new RuntimeException('Pruebas abortadas: LEGACY_BASE_URL debe ser un servidor local.');
        }
    }
}
