<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RankingRequest;
use App\Models\Explora\ResultadoJuego;
use App\Support\CacheRanking;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Ranking de un juego (reemplaza a juegos/php/consultar_ranking.php).
 *
 * Ruta pública: GET /api/ranking?juego=&nivel=&categoria= (límite `throttle:api`).
 *
 * Caché (Fase 17E):
 *  - **Servidor.** El cuerpo JSON se guarda `explora.ranking_cache_ttl` segundos (0 = desactivada) con una clave hecha SOLO
 *    con los valores validados más un contador de versión ({@see CacheRanking}). Guardar una partida sube la versión, así
 *    que el ranking nunca queda desactualizado tras `POST /api/resultados`; el TTL corto solo acota el espacio usado.
 *  - **Seguridad / DoS.** La categoría es texto libre (hasta 100 caracteres) y alguien podría pedir millones de categorías
 *    distintas para llenar la caché. Por eso NO se guardan los resultados vacíos (una categoría inventada siempre da `[]`):
 *    solo se cachean combinaciones que existen de verdad en la base, y el TTL corto acota el resto.
 *  - **HTTP.** Toda 200 lleva `ETag` débil y `Cache-Control: public, max-age=0, must-revalidate`: el navegador revalida
 *    siempre (nunca muestra un ranking viejo) y, si nada cambió, recibe 304 sin cuerpo. Los errores no llevan ETag.
 */
class RankingController extends Controller
{
    /** Cantidad de posiciones del ranking (el frontend dibuja 3 con corona y 2 sin ella). */
    private const POSICIONES = 5;

    /**
     * Devuelve las mejores {@see self::POSICIONES} partidas de la combinación juego + nivel + categoría.
     *
     * Orden: más puntaje primero; a igual puntaje, menos tiempo; y a igualdad total, la más antigua (id) para que el
     * resultado sea estable. Solo salen los campos que dibuja `terminar_juego.js` (jugador, avatar, grado, puntaje,
     * tiempo): el `id_usuario` NO se revela (el legado devolvía toda la fila).
     *
     * @param  RankingRequest  $request  Parámetros ya validados.
     * @return Response 200 con lista de 0 a 5 filas `{jugador, avatar, grado, puntaje, tiempo}` (`ETag`, `X-Cache`), o 304.
     */
    public function __invoke(RankingRequest $request): Response
    {
        $juego = (string) $request->validated('juego');
        $nivel = (string) $request->validated('nivel');
        $categoria = (string) $request->validated('categoria');

        $ttl = (int) config('explora.ranking_cache_ttl', 0);
        $clave = $ttl > 0 ? CacheRanking::clave($juego, $nivel, $categoria) : null;
        $cuerpo = $clave !== null ? Cache::get($clave) : null;
        $acierto = is_string($cuerpo);

        if (! $acierto) {
            $cuerpo = $this->consultar($juego, $nivel, $categoria);
            // Los vacíos no se guardan (ver «Seguridad / DoS» en el docblock de la clase).
            if ($clave !== null && $cuerpo !== '[]') {
                Cache::put($clave, $cuerpo, $ttl);
            }
        }

        $etag = 'W/"'.md5($cuerpo).'"';
        $respuesta = response($cuerpo, 200, ['Content-Type' => 'application/json']);
        $respuesta->headers->set('ETag', $etag);
        $respuesta->headers->set('Cache-Control', 'public, max-age=0, must-revalidate');
        $respuesta->headers->set('X-Cache', $acierto ? 'HIT' : 'MISS');

        // 304: el navegador ya tiene esta versión.
        if (in_array($etag, array_map('trim', explode(',', (string) $request->headers->get('If-None-Match'))), true)) {
            $respuesta->setNotModified();
        }

        return $respuesta;
    }

    /**
     * Consulta la base y devuelve el cuerpo JSON del ranking (mismo formato que siempre).
     *
     * @param  string  $juego  Juego validado.
     * @param  string  $nivel  Nivel validado.
     * @param  string  $categoria  Categoría validada.
     * @return string JSON de 0 a 5 filas `{jugador, avatar, grado, puntaje, tiempo}`.
     */
    private function consultar(string $juego, string $nivel, string $categoria): string
    {
        $filas = ResultadoJuego::where('nombre_juego', $juego)
            ->where('nivel', $nivel)
            ->where('categoria', $categoria)
            ->orderByDesc('puntaje')->orderBy('tiempo')->orderBy('id')
            ->limit(self::POSICIONES)
            ->get(['jugador', 'avatar', 'grado', 'puntaje', 'tiempo'])
            ->map(fn (ResultadoJuego $r) => [
                'jugador' => (string) $r->jugador,
                'avatar' => (string) $r->avatar,
                'grado' => (string) $r->grado,
                'puntaje' => (int) $r->puntaje,
                'tiempo' => (int) $r->tiempo,
            ])->values();

        return (string) json_encode($filas, JSON_UNESCAPED_UNICODE);
    }
}
