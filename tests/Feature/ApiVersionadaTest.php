<?php

namespace Tests\Feature;

use App\Http\Middleware\AvisoDeprecacionApi;
use App\Http\Middleware\ErroresProblemJson;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RutaFacade;
use Tests\TestCase;

/**
 * Fase 17E: versionado de la API (`/api/v1/...` junto a las rutas `/api/...` heredadas).
 *
 * La prueba central es la guarda de seguridad: cada ruta `v1.X` debe tener su gemela `X` con los MISMOS middleware
 * (throttles, `web`/CSRF, `auth:web`, `cuenta.activa`...), salvo los dos propios de cada versión. Así nadie puede añadir
 * una ruta a una versión sin las mismas protecciones.
 */
class ApiVersionadaTest extends TestCase
{
    /**
     * Rutas v1 registradas por la aplicación.
     *
     * @return array<string, Route> Indexadas por nombre.
     */
    private function rutasV1(): array
    {
        $rutas = [];
        foreach (RutaFacade::getRoutes()->getRoutesByName() as $nombre => $ruta) {
            if (str_starts_with($nombre, 'v1.')) {
                $rutas[$nombre] = $ruta;
            }
        }

        return $rutas;
    }

    /** Hay rutas v1 (la guarda no puede pasar «en vacío»). */
    public function test_existen_rutas_v1(): void
    {
        $this->assertGreaterThanOrEqual(9, count($this->rutasV1()));
    }

    /**
     * Guarda de seguridad: para cada `v1.X` existe `X` con igual método, patrón de URI (salvo `v1/`), restricciones
     * `where` y lista de middleware idéntica salvo los propios de cada versión.
     */
    public function test_cada_ruta_v1_tiene_su_gemela_con_los_mismos_middleware(): void
    {
        foreach ($this->rutasV1() as $nombre => $v1) {
            $antiguo = RutaFacade::getRoutes()->getByName(substr($nombre, 3));
            $this->assertNotNull($antiguo, "Falta la ruta antigua gemela de $nombre");

            $this->assertSame($antiguo->methods(), $v1->methods(), "Métodos distintos en $nombre");
            $this->assertSame($antiguo->uri(), str_replace('api/v1/', 'api/', $v1->uri()), "URI distinta en $nombre");
            $this->assertSame($antiguo->wheres, $v1->wheres, "Restricciones where distintas en $nombre");
            $this->assertSame($antiguo->getActionName(), $v1->getActionName(), "Acción distinta en $nombre");

            $this->assertSame(
                array_values(array_diff($antiguo->gatherMiddleware(), [AvisoDeprecacionApi::class])),
                array_values(array_diff($v1->gatherMiddleware(), [ErroresProblemJson::class])),
                "Middleware distintos en $nombre"
            );
            $this->assertContains(ErroresProblemJson::class, $v1->gatherMiddleware());
            $this->assertContains(AvisoDeprecacionApi::class, $antiguo->gatherMiddleware());
        }
    }

    /** Toda ruta `api/*` con nombre tiene gemela v1 (nadie añade una ruta solo al legado). */
    public function test_toda_ruta_antigua_tiene_su_gemela_v1(): void
    {
        foreach (RutaFacade::getRoutes()->getRoutesByName() as $nombre => $ruta) {
            if (str_starts_with($ruta->uri(), 'api/') && ! str_starts_with($nombre, 'v1.') && ! str_starts_with($ruta->uri(), 'api/v1/')) {
                $this->assertNotNull(RutaFacade::getRoutes()->getByName('v1.'.$nombre), "Falta v1.$nombre");
            }
        }
    }

    /** El middleware de problem json es el primero propio de la ruta, tras el grupo `api`, y antes del grupo `web`. */
    public function test_el_middleware_problem_json_envuelve_al_grupo_web(): void
    {
        $mw = $this->rutasV1()['v1.sesion.store']->gatherMiddleware();
        $this->assertLessThan(array_search('web', $mw, true), array_search(ErroresProblemJson::class, $mw, true));
    }

    /** La ruta antigua responde con `Deprecation` y `Link` a su sucesora; la v1 no. */
    public function test_solo_la_ruta_antigua_avisa_de_deprecacion(): void
    {
        $antigua = $this->getJson('/api/departamentos/2/preguntas')->assertOk();
        $this->assertSame('true', $antigua->headers->get('Deprecation'));
        $this->assertSame('</api/v1/departamentos/2/preguntas>; rel="successor-version"', $antigua->headers->get('Link'));

        $nueva = $this->getJson('/api/v1/departamentos/2/preguntas')->assertOk();
        $this->assertNull($nueva->headers->get('Deprecation'));
        $this->assertNull($nueva->headers->get('Link'));
        $this->assertSame($antigua->json(), $nueva->json(), 'Mismo contenido en ambas versiones');
    }

    /** La v1 conserva la restricción numérica del id: un id no numérico no llega al controlador. */
    public function test_la_v1_aplica_los_patrones_de_id(): void
    {
        $this->getJson('/api/v1/departamentos/abc/ficha')->assertStatus(404);
        $this->getJson('/api/v1/departamentos/1234567890/ficha')->assertStatus(404);
    }
}
