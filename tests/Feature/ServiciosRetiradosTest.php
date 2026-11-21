<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Fase 15: los 7 PHP legados sustituidos por la API se eliminaron del repositorio y sus URL responden `410 Gone` desde
 * Laravel (ruta `servicio-retirado` de `routes/web.php`, lista en `config/servicios_retirados.php`).
 *
 * Se prueba con el kernel HTTP de Laravel (sin servidor): todos los métodos, las mayúsculas, el JSON EXACTO, que no se
 * filtre nada interno (sin cookies de sesión, sin cabeceras de PHP, sin rutas del sistema) y que `chat.php`, `chat copy.php`
 * y `conexion*.php` NO queden afectados por esas rutas. El mismo contrato en el router de desarrollo (`php -S` real) está en
 * {@see ServidorDesarrolloTest}; el `.htaccess` bajo Apache se verificó a mano (ver REGISTRO_CAMBIOS).
 */
class ServiciosRetiradosTest extends TestCase
{
    /** Métodos HTTP que deben dar 410 (HEAD aparte: no lleva cuerpo). */
    private const METODOS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * Hace la petición pasando por el kernel (a diferencia de `$this->call()`, permite cualquier método y mayúsculas).
     *
     * @param  string  $metodo  Método HTTP.
     * @param  string  $uri  Ruta tal cual se envía.
     * @param  array<string,string>  $datos  Parámetros (en POST/PUT/PATCH van en el cuerpo).
     */
    private function pedir(string $metodo, string $uri, array $datos = []): TestResponse
    {
        return $this->createTestResponse($this->app->make(Kernel::class)->handle(Request::create('http://localhost'.$uri, $metodo, $datos)));
    }

    /**
     * @return array<string, array{0:string}> Las 7 rutas de la lista única, con su barra inicial.
     */
    public static function serviciosRetirados(): array
    {
        $rutas = array_map(static fn (string $r): string => '/'.$r, require dirname(__DIR__, 2).'/config/servicios_retirados.php');

        return array_combine($rutas, array_map(static fn (string $r): array => [$r], $rutas));
    }

    /**
     * La lista son exactamente los 7 scripts sustituidos por la API y ninguno existe ya como archivo.
     */
    public function test_la_lista_son_los_7_scripts_y_sus_archivos_ya_no_existen(): void
    {
        $lista = config('servicios_retirados');
        $this->assertCount(7, $lista);
        $this->assertSame(4, count(array_filter($lista, static fn (string $r): bool => str_starts_with($r, 'mapplic/php/'))));
        $this->assertSame(3, count(array_filter($lista, static fn (string $r): bool => str_starts_with($r, 'juegos/php/'))));
        foreach ($lista as $ruta) {
            $this->assertFileDoesNotExist(public_path($ruta), "$ruta debería estar eliminado");
        }
        // Ni siquiera la carpeta de juegos/php, ni los conexion*.php (credenciales) ni los .htaccess retirados.
        $this->assertDirectoryDoesNotExist(public_path('juegos/php'));
        foreach (['mapplic/php/conexion.php', 'mapplic/php/conexion_pedigital.php', 'mapplic/php/.htaccess'] as $archivo) {
            $this->assertFileDoesNotExist(public_path($archivo), $archivo);
        }
    }

    /**
     * Cada ruta da 410 con el JSON exacto, con todos los métodos HTTP (también POST sin token CSRF: no es un 419).
     *
     * @dataProvider serviciosRetirados
     */
    public function test_responde_410_con_el_json_exacto_con_todos_los_metodos(string $ruta): void
    {
        foreach (self::METODOS as $metodo) {
            $respuesta = $this->pedir($metodo, $ruta, $metodo === 'GET' ? ['id' => '1 OR 1=1'] : ['usuario' => 'x']);
            $respuesta->assertStatus(410);
            $this->assertSame('{"status":"error","mensaje":"Servicio retirado"}', $respuesta->getContent(), "$metodo $ruta");
            $this->assertStringContainsString('application/json', (string) $respuesta->headers->get('Content-Type'), "$metodo $ruta");
        }
        // HEAD: mismo código y sin cuerpo.
        $this->pedir('HEAD', $ruta)->assertStatus(410);
    }

    /**
     * La comparación no distingue mayúsculas (como el `[NC]` de los `.htaccess` retirados y como el router de desarrollo).
     *
     * @dataProvider serviciosRetirados
     */
    public function test_no_distingue_mayusculas(string $ruta): void
    {
        foreach ([strtoupper($ruta), strtolower($ruta), ucwords($ruta, '/_')] as $variante) {
            $this->pedir('GET', $variante)->assertStatus(410);
            $this->pedir('POST', $variante)->assertStatus(410);
        }
    }

    /**
     * La respuesta no revela nada interno: sin cookies (no arranca sesión), sin cabecera de PHP/Laravel, sin rutas del
     * sistema ni trazas, y no refleja los datos de la petición.
     *
     * @dataProvider serviciosRetirados
     */
    public function test_no_filtra_cabeceras_ni_trazas_internas(string $ruta): void
    {
        $respuesta = $this->pedir('POST', $ruta, ['usuario' => '<script>alert(1)</script>', 'contrasena' => 'secreto-de-prueba']);
        $respuesta->assertStatus(410);

        $this->assertCount(0, $respuesta->headers->getCookies(), 'no debe haber Set-Cookie (sin sesión)');
        $this->assertNull($respuesta->headers->get('Set-Cookie'));
        $this->assertNull($respuesta->headers->get('X-Powered-By'));
        $this->assertSame(['status', 'mensaje'], array_keys((array) json_decode($respuesta->getContent(), true)));
        foreach (['/Users/', base_path(), 'vendor', 'Exception', 'trace', 'script', 'secreto-de-prueba'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $respuesta->getContent());
        }
    }

    /**
     * Solo esas 7 rutas, exactamente: un prefijo, un sufijo o una ruta hermana NO dan 410 (la ruta no es un comodín).
     */
    public function test_no_hay_falsos_positivos(): void
    {
        foreach ([
            '/mapplic/php/iniciarSesion.php.bak', '/mapplic/php/iniciarSesion', '/mapplic/php/x/iniciarSesion.php',
            '/xmapplic/php/iniciarSesion.php', '/juegos/php/guardar_resultado.phps', '/juegos/php/otro.php',
            '/mapplic/php/iniciarSesion.php/extra', '/php/iniciarSesion.php',
        ] as $ruta) {
            $this->assertNotSame(410, $this->pedir('GET', $ruta)->getStatusCode(), $ruta);
        }
    }

    /**
     * El chat (fuera de esta fase, decisión del responsable) y los `conexion*.php` NO quedan afectados por las rutas 410:
     * Laravel no tiene ruta para ellos (404 si alguien los pidiera a través del front controller). Bajo Apache `chat.php`
     * existe como archivo y lo sirve PHP sin pasar por Laravel; en desarrollo lo bloquea el router (ver ServidorDesarrolloTest).
     */
    public function test_el_chat_y_los_conexion_no_quedan_afectados(): void
    {
        $this->assertFileExists(public_path('mapplic/php/chat.php'));
        $this->assertFileExists(public_path('mapplic/php/chat copy.php'));

        foreach (['/mapplic/php/chat.php', '/mapplic/php/chat%20copy.php', '/mapplic/php/conexion.php', '/mapplic/php/conexion_pedigital.php', '/juegos/php/conexion.php'] as $ruta) {
            foreach (['GET', 'POST'] as $metodo) {
                $this->pedir($metodo, $ruta)->assertStatus(404);
            }
        }
    }
}
