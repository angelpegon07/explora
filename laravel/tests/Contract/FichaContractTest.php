<?php

namespace Tests\Contract;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * CONTRATO de la ficha de departamento: GET /api/departamentos/{id}/ficha
 * debe entregar al frontend lo mismo que mapplic/php/consultarInfo.php (menos columnas internas).
 */
class FichaContractTest extends TestCase
{
    use ContractoLegado;

    private function ficha(int|string $id)
    {
        return $this->getJson("/api/departamentos/$id/ficha");
    }

    /**
     * Contrato: para cada departamento la ficha nueva debe tener exactamente las mismas claves y valores que
     * consultarInfo.php (menos columnas internas); necesita el servidor legado.
     */
    public function test_equivale_al_legado_en_los_33_departamentos(): void
    {
        $base = $this->legadoDisponible();
        $fallos = [];
        for ($id = 1; $id <= 33; $id++) {
            $legado = Http::timeout(120)->get($base.'/mapplic/php/consultarInfo.php', ['id' => $id])->json();
            $nuevo = $this->ficha($id);
            $nuevo->assertOk();
            $a = $this->normalizar($this->sinInternas($legado));
            $b = $this->normalizar($nuevo->json());
            if (($d = $this->primeraDiferencia($a, $b)) !== null) {
                $fallos[] = "dep $id: $d";
            }
            unset($legado, $nuevo, $a, $b);
        }
        $this->assertSame([], $fallos, implode("\n", $fallos));
    }

    /**
     * Contrato con el frontend: extrae del propio `verMas.js` cada ruta `data.x.y` que lee y comprueba que existe en
     * la respuesta de los 33 departamentos.
     */
    public function test_contiene_todas_las_claves_que_lee_ver_mas_js(): void
    {
        $js = file_get_contents(base_path('public/mapplic/verMas.js'));
        preg_match_all('/\bdata\.([A-Za-z_]+(?:\.[A-Za-z_]+)*)/', $js, $m);
        $rutas = array_values(array_unique($m[1]));
        $this->assertGreaterThan(30, count($rutas), 'No se pudo extraer el contrato de verMas.js');
        $faltan = [];
        for ($id = 1; $id <= 33; $id++) {
            $json = $this->ficha($id)->json();
            foreach ($rutas as $ruta) {
                // Las secciones sin fila llegan como null en el legado: allí solo se exige la clave de primer nivel.
                $partes = explode('.', $ruta);
                $nivel1 = $json[$partes[0]] ?? null;
                $ok = array_key_exists($partes[0], $json) && ($nivel1 === null || $this->contieneClave($json, $partes));
                if (! $ok) {
                    $faltan["dep $id"][] = $ruta;
                }
            }
        }
        $this->assertSame([], $faltan, json_encode($faltan));
    }

    /**
     * Lista blanca: ninguna sección contiene `remember_token` ni marcas de tiempo.
     */
    public function test_no_expone_columnas_internas(): void
    {
        foreach ([1, 2, 7, 33] as $id) {
            $this->assertNull($this->claveInterna($this->ficha($id)->assertOk()->json()), "dep $id");
        }
    }

    /**
     * La respuesta es `application/json` y la ruta lleva límite de peticiones (cabecera X-RateLimit-Limit).
     */
    public function test_responde_json_con_cabecera_y_limite_de_peticiones(): void
    {
        $r = $this->ficha(2);
        $r->assertOk()->assertHeader('Content-Type', 'application/json');
        $this->assertNotNull($r->headers->get('X-RateLimit-Limit'), 'falta throttle en la ruta');
    }

    /**
     * FUN-02: id inexistente → 404 con el mismo texto de error que el legado.
     */
    public function test_departamento_inexistente_devuelve_404_con_el_mensaje_del_legado(): void
    {
        $this->ficha(999)->assertNotFound()
            ->assertExactJson(['error' => 'No se encontraron registros para el departamento con ID: 999']);
    }

    /**
     * SEC-01/07: ids como «abc» o «2 OR 1=1» se rechazan con 404 sin SQL, sin error fatal y sin filtrar trazas ni
     * rutas.
     */
    public function test_id_no_numerico_o_inyectado_se_rechaza_sin_error_fatal(): void
    {
        $this->ficha(2)->assertOk(); // la ruta existe: los 404 siguientes son rechazos reales
        foreach (['abc', rawurlencode('2 OR 1=1'), rawurlencode("2' OR '1'='1")] as $malo) {
            $r = $this->ficha($malo);
            $r->assertNotFound();
            $this->assertStringNotContainsString('Fatal error', $r->getContent());
            $this->assertStringNotContainsString('vendor/laravel', $r->getContent(), 'la respuesta no debe filtrar rutas ni trazas');
            $this->assertStringNotContainsString('"trace"', $r->getContent());
            $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
        }
    }

    /**
     * TEC-01: la ficha se arma con ≤ 24 consultas (una por tabla) y no crece con los datos.
     */
    public function test_no_hay_consultas_en_bucle(): void
    {
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });
        $this->ficha(2)->assertOk();
        $this->assertLessThanOrEqual(24, $n, "demasiadas consultas: $n");
    }
}
