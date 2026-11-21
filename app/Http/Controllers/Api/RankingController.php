<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RankingRequest;
use App\Models\Explora\ResultadoJuego;
use Illuminate\Http\JsonResponse;

/**
 * Ranking de un juego (reemplaza a juegos/php/consultar_ranking.php).
 *
 * Ruta pública: GET /api/ranking?juego=&nivel=&categoria= (límite `throttle:api`).
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
     * @return JsonResponse Lista de 0 a 5 filas `{jugador, avatar, grado, puntaje, tiempo}`.
     */
    public function __invoke(RankingRequest $request): JsonResponse
    {
        $filas = ResultadoJuego::where('nombre_juego', $request->validated('juego'))
            ->where('nivel', $request->validated('nivel'))
            ->where('categoria', $request->validated('categoria'))
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

        return response()->json($filas, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
