<?php

namespace Tests\Feature;

use App\Http\Middleware\ErroresProblemJson;
use App\Models\Pedigital\Usuario;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * Fase 17E: los errores de `/api/v1/*` siguen RFC 9457 (`application/problem+json`) y los de `/api/*` no cambian.
 *
 * Cada caso comprueba el tipo de contenido, que `status` sea el entero del código HTTP y las extensiones que lee el
 * frontend (`mensaje`, `message`, `campos`).
 */
class ErroresProblemJsonTest extends TestCase
{
    /**
     * Comprueba la forma RFC 9457 de una respuesta de error de la v1.
     *
     * @param  TestResponse  $r  Respuesta a verificar.
     * @param  int  $estado  Código HTTP esperado.
     * @return array<string, mixed> Cuerpo decodificado.
     */
    private function verificar(TestResponse $r, int $estado): array
    {
        $r->assertStatus($estado);
        $this->assertStringStartsWith('application/problem+json', (string) $r->headers->get('Content-Type'));
        $c = $r->json();
        $this->assertSame('about:blank', $c['type']);
        $this->assertSame($estado, $c['status']);
        $this->assertIsString($c['title']);
        $this->assertNotSame('', $c['title']);
        $this->assertIsString($c['detail']);
        $this->assertStringStartsWith('/api/v1/', $c['instance']);
        $this->assertSame($c['detail'], $c['mensaje']);
        $this->assertSame($c['detail'], $c['message']);

        return $c;
    }

    /** 404 de la ficha inexistente: el texto original (`error`) pasa a `detail`. */
    public function test_404_de_ficha_inexistente(): void
    {
        $c = $this->verificar($this->getJson('/api/v1/departamentos/999999/ficha'), 404);
        $this->assertArrayHasKey('error', $c);
        $this->assertSame($c['error'], $c['detail']);
    }

    /** 404 de una URL v1 desconocida (lo genera el router, no una ruta). */
    public function test_404_de_url_desconocida(): void
    {
        $c = $this->verificar($this->getJson('/api/v1/no-existe'), 404);
        $this->assertSame('Recurso no encontrado.', $c['detail']);
    }

    /** 405 en español con cabecera Allow. */
    public function test_405_con_allow(): void
    {
        $r = $this->deleteJson('/api/v1/ranking');
        $c = $this->verificar($r, 405);
        $this->assertSame('Método no permitido para este recurso.', $c['detail']);
        $this->assertStringContainsString('GET', (string) $r->headers->get('Allow'));
    }

    /** El 405 del legado pasa a JSON en español (mejora aceptada) y conserva `Allow`. */
    public function test_405_del_legado_en_espanol(): void
    {
        $r = $this->deleteJson('/api/ranking')->assertStatus(405);
        $this->assertSame(false, $r->json('success'));
        $this->assertSame('Método no permitido.', $r->json('mensaje'));
        $this->assertSame('Método no permitido.', $r->json('message'));
        $this->assertNotNull($r->headers->get('Allow'));
        $this->assertStringStartsWith('application/json', (string) $r->headers->get('Content-Type'));
    }

    /** 401 sin sesión (respuesta manual del controlador). */
    public function test_401_sin_sesion(): void
    {
        $this->verificar($this->getJson('/api/v1/sesion'), 401);
    }

    /** 401 de `auth:web` (excepción de autenticación): el texto en inglés de Laravel no se filtra. */
    public function test_401_de_auth_web(): void
    {
        $c = $this->verificar($this->getJson('/api/v1/historial'), 401);
        $this->assertStringNotContainsString('Unauthenticated', json_encode($c));
    }

    /** 419 de CSRF (con el entorno simulado como real, porque bajo pruebas Laravel lo desactiva). */
    public function test_419_de_csrf(): void
    {
        $this->app['env'] = 'local';
        $this->verificar($this->postJson('/api/v1/sesion', ['usuario' => 'usuario1', 'contrasena' => 'password']), 419);
    }

    /** 422 de login, resultados, ranking y avatar conservan `campos` donde existía. */
    public function test_422_de_los_formularios(): void
    {
        $this->verificar($this->postJson('/api/v1/sesion', ['usuario' => 'usuario1']), 422);

        $c = $this->verificar($this->postJson('/api/v1/resultados', []), 422);
        $this->assertIsArray($c['campos']);
        $this->assertNotEmpty($c['campos']);
        $this->assertArrayNotHasKey('errors', $c);

        $c = $this->verificar($this->getJson('/api/v1/ranking'), 422);
        $this->assertContains('juego', $c['campos']);

        $r = $this->actingAs(Usuario::findOrFail(1), 'web')->patchJson('/api/v1/perfil/avatar', ['avatar' => 99999]);
        $this->verificar($r, 422);
    }

    /** 422 de la validación estándar de Laravel (ValidationException): añade `errors`. */
    public function test_422_de_validation_exception_lleva_errors(): void
    {
        Route::middleware(['api', ErroresProblemJson::class])->prefix('api/v1')->get('prueba-validacion', function () {
            request()->validate(['x' => 'required']);
        });
        $c = $this->verificar($this->getJson('/api/v1/prueba-validacion'), 422);
        $this->assertArrayHasKey('x', $c['errors']);
        $this->assertSame(['x'], $c['campos']);
    }

    /** 429 del límite de inicio de sesión: conserva Retry-After. */
    public function test_429_con_retry_after(): void
    {
        $login = 'bloqueo_v1_'.uniqid();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/sesion', ['usuario' => $login, 'contrasena' => 'x'])->assertStatus(401);
        }
        $r = $this->postJson('/api/v1/sesion', ['usuario' => $login, 'contrasena' => 'x']);
        $this->verificar($r, 429);
        $this->assertNotNull($r->headers->get('Retry-After'));
    }

    /** 429 del límite general `throttle:api` (fuera del middleware de ruta; lo cubre el manejador). */
    public function test_429_del_limite_general_de_la_api(): void
    {
        config(['explora.api_por_minuto' => 2]);
        $this->getJson('/api/v1/departamentos/2/preguntas')->assertOk();
        $this->getJson('/api/v1/departamentos/2/preguntas')->assertOk();
        $r = $this->getJson('/api/v1/departamentos/2/preguntas');
        $this->verificar($r, 429);
        $this->assertNotNull($r->headers->get('Retry-After'));
    }

    /** Un 500 en v1 es genérico: sin traza, archivo ni mensaje de la excepción, ni con APP_DEBUG=true. */
    public function test_500_no_filtra_detalles_internos(): void
    {
        config(['app.debug' => true]);
        Route::middleware('api')->prefix('api/v1')->get('prueba-explota', function () {
            throw new RuntimeException('SECRETO-INTERNO /var/www/app.php');
        });

        $r = $this->getJson('/api/v1/prueba-explota');
        $c = $this->verificar($r, 500);
        $this->assertSame('Error interno del servidor.', $c['detail']);
        $texto = (string) $r->getContent();
        foreach (['trace', 'file', 'line', 'exception', 'SECRETO-INTERNO', '/var/www'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $texto);
        }
    }

    /** El legado NO cambia: la ficha inexistente sigue devolviendo `{error}` en application/json. */
    public function test_el_legado_conserva_su_cuerpo(): void
    {
        $r = $this->getJson('/api/departamentos/999999/ficha')->assertStatus(404);
        $this->assertStringStartsWith('application/json', (string) $r->headers->get('Content-Type'));
        $this->assertArrayHasKey('error', $r->json());
        $this->assertArrayNotHasKey('status', $r->json());
        $this->getJson('/api/historial')->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
