<?php

namespace Tests\Feature;

use App\Http\Middleware\CacheLecturaPublica;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pruebas de la caché de lectura pública (Fase 17B; {@see CacheLecturaPublica}) sobre la ficha y las preguntas.
 * Solo leen la base de pruebas; la caché de las pruebas es `array` (se reinicia en cada prueba).
 */
class CacheLecturaTest extends TestCase
{
    /** La primera petición reconstruye la respuesta (MISS) y la segunda sale de la caché (HIT) con el mismo cuerpo. */
    public function test_la_segunda_peticion_sale_de_la_cache_sin_consultar_la_base(): void
    {
        $primera = $this->getJson('/api/departamentos/2/preguntas')->assertOk();
        $this->assertSame('MISS', $primera->headers->get('X-Cache'));

        DB::enableQueryLog();
        $segunda = $this->getJson('/api/departamentos/2/preguntas')->assertOk();

        $this->assertSame('HIT', $segunda->headers->get('X-Cache'));
        $this->assertSame([], DB::getQueryLog(), 'Un HIT no debe consultar la base.');
        $this->assertSame($primera->getContent(), $segunda->getContent());
    }

    /** Toda respuesta 200 lleva ETag débil y Cache-Control público con revalidación. */
    public function test_la_respuesta_lleva_etag_y_cache_control(): void
    {
        $r = $this->getJson('/api/departamentos/2/preguntas')->assertOk();

        $this->assertMatchesRegularExpression('/^W\/"[0-9a-f]{32}"$/', (string) $r->headers->get('ETag'));
        $cc = (string) $r->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cc);
        $this->assertStringContainsString('max-age=300', $cc);
        $this->assertStringContainsString('must-revalidate', $cc);
    }

    /** Con If-None-Match igual al ETag se responde 304 sin cuerpo; con otro ETag, 200 completo. */
    public function test_if_none_match_devuelve_304_sin_cuerpo(): void
    {
        $etag = (string) $this->getJson('/api/departamentos/2/preguntas')->headers->get('ETag');

        $this->getJson('/api/departamentos/2/preguntas', ['If-None-Match' => $etag])
            ->assertStatus(304)->assertNoContent(304);
        $this->getJson('/api/departamentos/2/preguntas', ['If-None-Match' => 'W/"otro"'])->assertOk();
    }

    /** Los errores (404) no se cachean ni llevan ETag. */
    public function test_los_errores_no_se_cachean(): void
    {
        $r = $this->getJson('/api/departamentos/999999/preguntas')->assertNotFound();
        $this->assertNull($r->headers->get('ETag'));
        $this->assertNotSame('HIT', $this->getJson('/api/departamentos/999999/preguntas')->headers->get('X-Cache'));
    }

    /** Un cuerpo mayor que `cache_lectura_max_bytes` no se guarda en el servidor, pero sí lleva ETag. */
    public function test_los_cuerpos_grandes_no_se_guardan(): void
    {
        config(['explora.cache_lectura_max_bytes' => 10]);

        $this->getJson('/api/departamentos/2/preguntas')->assertOk();
        $segunda = $this->getJson('/api/departamentos/2/preguntas')->assertOk();

        $this->assertSame('MISS', $segunda->headers->get('X-Cache'));
        $this->assertNotNull($segunda->headers->get('ETag'));
    }

    /** `EXPLORA_CACHE_TTL=0` desactiva todo: sin ETag ni X-Cache. */
    public function test_con_ttl_cero_queda_desactivada(): void
    {
        config(['explora.cache_lectura_ttl' => 0]);

        $r = $this->getJson('/api/departamentos/2/preguntas')->assertOk();
        $this->assertNull($r->headers->get('ETag'));
        $this->assertNull($r->headers->get('X-Cache'));
    }

    /** El comando sube la versión y la siguiente petición vuelve a ser MISS. */
    public function test_el_comando_invalida_la_cache(): void
    {
        $this->getJson('/api/departamentos/2/preguntas')->assertOk();
        $this->assertSame('HIT', $this->getJson('/api/departamentos/2/preguntas')->headers->get('X-Cache'));

        $this->artisan('explora:limpiar-cache-lectura')->assertSuccessful();

        $this->assertSame(2, Cache::get(CacheLecturaPublica::CLAVE_VERSION));
        $this->assertSame('MISS', $this->getJson('/api/departamentos/2/preguntas')->headers->get('X-Cache'));
    }

    /** La ficha también pasa por la caché (HIT en la segunda petición). */
    public function test_la_ficha_tambien_se_cachea(): void
    {
        config(['explora.cache_lectura_max_bytes' => 50 * 1024 * 1024]);

        $this->getJson('/api/departamentos/2/ficha')->assertOk();
        $this->assertSame('HIT', $this->getJson('/api/departamentos/2/ficha')->headers->get('X-Cache'));
    }
}
