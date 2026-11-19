<?php

namespace Tests\Contract;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * CONTRATO del quiz: GET /api/departamentos/{id}/preguntas debe servir a mapplic/js/quiz.js lo mismo que
 * mapplic/php/buscarPreguntas.php: { departamento:{id,nombre,capital}, preguntas:[{pregunta,tipo_pregunta,opciones}] }.
 */
class QuizContractTest extends TestCase
{
    use ContractoLegado;

    private function quiz(int|string $id)
    {
        return $this->getJson("/api/departamentos/$id/preguntas");
    }

    /** Proyección del legado a los campos que el contrato conserva. */
    private function proyectarLegado(array $l): array
    {
        $preguntas = array_map(function ($p) {
            $opc = array_map(fn ($o) => $p['tipo_pregunta'] === 'multiple'
                ? ['id' => $o['id'], 'opcion' => $o['opcion'], 'correcta' => $o['correcta']]
                : ['id' => $o['id'], 'respuesta' => $o['respuesta']], $p['opciones']);

            return ['id' => $p['id'], 'tipo_pregunta' => $p['tipo_pregunta'], 'pregunta' => $p['pregunta'],
                'origen_pregunta' => $p['origen_pregunta'], 'opciones' => $opc];
        }, $l['preguntas']);

        return ['departamento' => $l['departamento'], 'preguntas' => $preguntas];
    }

    /**
     * Contrato: preguntas, opciones, `correcta`/`respuesta` y departamento coinciden con buscarPreguntas.php en los 33
     * departamentos; necesita el servidor legado.
     */
    public function test_equivale_al_legado_en_los_33_departamentos(): void
    {
        $base = $this->legadoDisponible();
        $fallos = [];
        for ($id = 1; $id <= 33; $id++) {
            $legado = Http::timeout(60)->get($base.'/mapplic/php/buscarPreguntas.php', ['id' => $id])->json();
            $nuevo = $this->quiz($id);
            $nuevo->assertOk();
            $a = $this->normalizar($this->proyectarLegado($legado));
            $b = $this->normalizar($nuevo->json());
            if (($d = $this->primeraDiferencia($a, $b)) !== null) {
                $fallos[] = "dep $id: $d";
            }
        }
        $this->assertSame([], $fallos, implode("\n", $fallos));
    }

    /**
     * Contrato con `quiz.js`: `departamento.{id,nombre,capital}`, y por pregunta `pregunta`, `tipo_pregunta` y las
     * opciones que usa cada tipo.
     */
    public function test_forma_que_consume_quiz_js(): void
    {
        $j = $this->quiz(2)->assertOk()->json();
        foreach (['id', 'nombre', 'capital'] as $k) {
            $this->assertArrayHasKey($k, $j['departamento']);
        }
        $this->assertNotEmpty($j['preguntas']);
        $tipos = [];
        foreach ($j['preguntas'] as $p) {
            $this->assertArrayHasKey('pregunta', $p);
            $this->assertContains($p['tipo_pregunta'], ['multiple', 'verfal']);
            $tipos[$p['tipo_pregunta']] = true;
            if ($p['tipo_pregunta'] === 'multiple') {
                $this->assertGreaterThanOrEqual(2, count($p['opciones']));
                foreach ($p['opciones'] as $o) {
                    $this->assertArrayHasKey('opcion', $o);
                    $this->assertArrayHasKey('correcta', $o);
                }
            } else {
                $this->assertCount(1, $p['opciones']);
                $this->assertArrayHasKey('respuesta', $p['opciones'][0]);
            }
        }
        $this->assertArrayHasKey('multiple', $tipos);
    }

    /**
     * Lista blanca: sin `remember_token` ni marcas de tiempo en preguntas ni opciones.
     */
    public function test_no_expone_columnas_internas(): void
    {
        foreach ([1, 2, 33] as $id) {
            $this->assertNull($this->claveInterna($this->quiz($id)->assertOk()->json()), "dep $id");
        }
    }

    /**
     * SEC-06: con `explora.quiz_incluir_respuestas = false` no sale ninguna respuesta correcta (NO activar con el
     * cliente actual: corrige en el navegador).
     */
    public function test_las_respuestas_pueden_ocultarse_por_configuracion(): void
    {
        config(['explora.quiz_incluir_respuestas' => false]);
        $j = $this->quiz(2)->assertOk()->json();
        foreach ($j['preguntas'] as $p) {
            foreach ($p['opciones'] as $o) {
                $this->assertArrayNotHasKey('correcta', $o);
                $this->assertArrayNotHasKey('respuesta', $o);
            }
        }
    }

    /**
     * Fija que el valor por defecto es true mientras `quiz.js` corrija en el navegador.
     */
    public function test_por_defecto_conserva_las_respuestas_para_no_romper_el_frontend_actual(): void
    {
        $this->assertTrue(config('explora.quiz_incluir_respuestas'));
    }

    /**
     * FUN-02: departamento sin datos → 404 con `{error}` (el legado devolvía cuerpo vacío).
     */
    public function test_departamento_inexistente_devuelve_404_json(): void
    {
        $this->quiz(999)->assertNotFound()->assertJsonStructure(['error']);
    }

    /**
     * SEC-01: ids no numéricos o inyectados → 404 sin error fatal ni trazas.
     */
    public function test_id_no_numerico_o_inyectado_se_rechaza(): void
    {
        $this->quiz(2)->assertOk(); // la ruta existe: los 404 siguientes son rechazos reales
        foreach (['abc', rawurlencode('2 OR 1=1')] as $malo) {
            $r = $this->quiz($malo);
            $r->assertNotFound();
            $this->assertStringNotContainsString('Fatal error', $r->getContent());
            $this->assertStringNotContainsString('vendor/laravel', $r->getContent(), 'la respuesta no debe filtrar rutas ni trazas');
            $this->assertStringNotContainsString('"trace"', $r->getContent());
        }
    }

    /**
     * La respuesta es `application/json` y lleva límite de peticiones.
     */
    public function test_cabecera_json_y_limite_de_peticiones(): void
    {
        $r = $this->quiz(2)->assertOk()->assertHeader('Content-Type', 'application/json');
        $this->assertNotNull($r->headers->get('X-RateLimit-Limit'));
    }

    /**
     * El límite del grupo `throttle:api` es configurable (`explora.api_por_minuto`): con 5 por minuto la 6.ª
     * petición recibe 429 con cabecera Retry-After (misma ruta que la ficha). Se baja el valor para no lanzar
     * cientos de peticiones; el limitador lee la configuración en cada petición.
     */
    public function test_el_limite_configurable_de_peticiones_por_minuto_corta_a_la_siguiente(): void
    {
        config(['explora.api_por_minuto' => 5]);
        for ($i = 0; $i < 5; $i++) {
            $this->quiz(2)->assertOk();
        }
        $r = $this->quiz(2)->assertStatus(429);
        $this->assertNotNull($r->headers->get('Retry-After'));
    }
}
