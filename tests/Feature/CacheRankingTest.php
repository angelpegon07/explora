<?php

namespace Tests\Feature;

use App\Models\Explora\ResultadoJuego;
use App\Support\CacheRanking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 17E: caché del ranking (servidor + HTTP).
 *
 * Comprueba que la segunda lectura sale de la caché sin tocar la base, que las cabeceras `ETag`/`Cache-Control`/`X-Cache`
 * son las acordadas, que los vacíos y los errores no se cachean y, sobre todo, que guardar una partida invalida la caché:
 * el juego pide el ranking justo después de guardar y no puede ver datos viejos.
 *
 * Las filas se crean con una categoría de prefijo {@see self::MARCA} y se borran al terminar. La caché de pruebas es `array`
 * (se reinicia en cada prueba).
 */
class CacheRankingTest extends TestCase
{
    /** Prefijo de las categorías de prueba. */
    private const MARCA = 'Zz Prueba CacheRanking ';

    /** @var string Categoría única de esta ejecución. */
    private string $categoria;

    /**
     * Activa la caché (TTL 30 s) y prepara una categoría única.
     */
    protected function setUp(): void
    {
        parent::setUp();
        config(['explora.ranking_cache_ttl' => 30]);
        $this->categoria = self::MARCA.uniqid();
    }

    /**
     * Borra lo que crearon las pruebas.
     */
    protected function tearDown(): void
    {
        ResultadoJuego::where('categoria', 'like', self::MARCA.'%')->delete();
        parent::tearDown();
    }

    /**
     * Inserta una partida directamente en la tabla.
     *
     * @param  array<string, mixed>  $campos  Campos a sobrescribir.
     */
    private function sembrar(array $campos = []): ResultadoJuego
    {
        return ResultadoJuego::create(array_merge([
            'nombre_juego' => 'laberinto', 'nivel' => 'Principiante', 'categoria' => $this->categoria,
            'puntaje' => 5, 'tiempo' => 100, 'jugador' => 'Jugador Prueba', 'avatar' => '1.png', 'grado' => '6',
            'id_usuario' => 0, 'fecha' => '01-01-2026 10:00:00',
        ], $campos));
    }

    /**
     * URL del ranking de la categoría de prueba (u otra).
     *
     * @param  string|null  $categoria  Categoría; por defecto la de esta ejecución.
     */
    private function url(?string $categoria = null): string
    {
        return '/api/v1/ranking?'.http_build_query(['juego' => 'laberinto', 'nivel' => 'Principiante', 'categoria' => $categoria ?? $this->categoria]);
    }

    /** La 1.ª petición es MISS y la 2.ª es HIT sin ninguna consulta a la base, con el mismo cuerpo. */
    public function test_la_segunda_peticion_sale_de_la_cache_sin_consultar_la_base(): void
    {
        $this->sembrar();

        $primera = $this->getJson($this->url())->assertOk()->assertHeader('X-Cache', 'MISS');

        DB::enableQueryLog();
        $segunda = $this->getJson($this->url())->assertOk()->assertHeader('X-Cache', 'HIT');
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame($primera->getContent(), $segunda->getContent());
        $this->assertSame([], $consultas, 'Un HIT no debe consultar la base.');
        $segunda->assertJsonPath('0.jugador', 'Jugador Prueba');
    }

    /** Toda 200 lleva ETag débil (md5 del cuerpo) y Cache-Control que obliga a revalidar. */
    public function test_la_respuesta_lleva_etag_y_cache_control(): void
    {
        $this->sembrar();

        $r = $this->getJson($this->url())->assertOk();

        $this->assertSame('W/"'.md5($r->getContent()).'"', $r->headers->get('ETag'));
        $cc = (string) $r->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cc);
        $this->assertStringContainsString('max-age=0', $cc);
        $this->assertStringContainsString('must-revalidate', $cc);
    }

    /** Con If-None-Match igual al ETag, 304 sin cuerpo (tanto en MISS como en HIT); con otro valor, 200. */
    public function test_if_none_match_devuelve_304_sin_cuerpo(): void
    {
        $this->sembrar();
        $etag = $this->getJson($this->url())->assertOk()->headers->get('ETag');

        $r = $this->getJson($this->url(), ['If-None-Match' => $etag])->assertStatus(304);
        $this->assertSame('', (string) $r->getContent());

        $this->getJson($this->url(), ['If-None-Match' => 'W/"otro"'])->assertOk();
    }

    /** Un ranking vacío no se guarda (defensa contra llenar la caché con categorías inventadas). */
    public function test_los_resultados_vacios_no_se_cachean(): void
    {
        $this->getJson($this->url())->assertOk()->assertExactJson([])->assertHeader('X-Cache', 'MISS');
        $this->getJson($this->url())->assertOk()->assertHeader('X-Cache', 'MISS');
    }

    /** Un 422 no se cachea ni lleva ETag. */
    public function test_un_422_no_se_cachea_ni_lleva_etag(): void
    {
        $r = $this->getJson('/api/v1/ranking?juego=inexistente&nivel=Principiante&categoria=x')->assertStatus(422);
        $this->assertNull($r->headers->get('ETag'));
        $this->assertNull($r->headers->get('X-Cache'));

        $this->getJson('/api/v1/ranking?juego=inexistente&nivel=Principiante&categoria=x')->assertStatus(422);
    }

    /** Con EXPLORA_RANKING_CACHE_TTL=0 nunca hay HIT, pero la respuesta sigue llevando ETag. */
    public function test_con_ttl_cero_la_cache_esta_desactivada(): void
    {
        config(['explora.ranking_cache_ttl' => 0]);
        $this->sembrar();

        $this->getJson($this->url())->assertOk()->assertHeader('X-Cache', 'MISS');
        $this->getJson($this->url())->assertOk()->assertHeader('X-Cache', 'MISS')->assertHeader('ETag');
    }

    /** Guardar un resultado por POST invalida la caché: la siguiente lectura ya incluye la partida nueva. */
    public function test_guardar_un_resultado_invalida_la_cache(): void
    {
        $this->sembrar(['puntaje' => 5, 'jugador' => 'Antiguo']);
        $antes = $this->getJson($this->url())->assertOk();
        $this->getJson($this->url())->assertHeader('X-Cache', 'HIT');
        $versionAntes = CacheRanking::version();

        $this->postJson('/api/resultados', [
            'juego' => 'laberinto', 'nivel' => 'Principiante', 'categoria' => $this->categoria,
            'puntaje' => 9, 'tiempo' => 50, 'nombre' => 'Nuevo Jugador', 'grado' => '5', 'avatar' => '3.png',
        ])->assertStatus(201);

        $this->assertGreaterThan($versionAntes, CacheRanking::version());
        $despues = $this->getJson($this->url())->assertOk()->assertHeader('X-Cache', 'MISS');
        $despues->assertJsonCount(2)->assertJsonPath('0.jugador', 'Nuevo Jugador');
        $this->assertNotSame($antes->headers->get('ETag'), $despues->headers->get('ETag'));
    }

    /** Dos categorías distintas no comparten entrada de caché. */
    public function test_dos_categorias_no_se_mezclan(): void
    {
        $otra = $this->categoria.' otra';
        $this->sembrar(['jugador' => 'Uno']);
        $this->sembrar(['jugador' => 'Dos', 'categoria' => $otra]);

        $this->getJson($this->url())->assertJsonPath('0.jugador', 'Uno');
        $this->getJson($this->url($otra))->assertJsonPath('0.jugador', 'Dos')->assertHeader('X-Cache', 'MISS');
        $this->getJson($this->url())->assertJsonPath('0.jugador', 'Uno')->assertHeader('X-Cache', 'HIT');
        $this->assertNotNull(Cache::get(CacheRanking::clave('laberinto', 'Principiante', $otra)));
    }
}
