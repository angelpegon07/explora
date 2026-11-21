<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Explora\ResultadoJuego;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Historial de partidas del usuario con sesión (reemplaza a juegos/php/consultar_historial.php).
 *
 * Ruta: GET /api/historial (middleware `auth:web` y `cuenta.activa`).
 *
 * Cierra SEC-03 e IDOR: el legado recibía `?id_usuario=` y entregaba el historial de cualquiera. Aquí el usuario sale
 * SIEMPRE de la sesión y cualquier `id_usuario` enviado se ignora.
 */
class HistorialController extends Controller
{
    /**
     * Devuelve las partidas del usuario, de la más reciente a la más antigua.
     *
     * Se ordena por `id` descendente (equivale al orden cronológico de inserción y, a diferencia de `fecha`, que es un
     * texto «d-m-Y», se puede ordenar en la base). Se limita a `explora.historial_max` filas. Salen solo los campos que
     * muestra `miPerfil/script.js` más el `id`; no se repite el nombre, el grado ni el avatar.
     *
     * @param  Request  $request  Petición con sesión.
     * @return JsonResponse Lista de `{id, nombre_juego, categoria, nivel, puntaje, tiempo, fecha}`.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $filas = ResultadoJuego::where('id_usuario', $request->user('web')->getAuthIdentifier())
            ->orderByDesc('id')
            ->limit(config('explora.historial_max'))
            ->get(['id', 'nombre_juego', 'categoria', 'nivel', 'puntaje', 'tiempo', 'fecha'])
            ->map(fn (ResultadoJuego $r) => [
                'id' => (int) $r->id,
                'nombre_juego' => (string) $r->nombre_juego,
                'categoria' => (string) $r->categoria,
                'nivel' => (string) $r->nivel,
                'puntaje' => (int) $r->puntaje,
                'tiempo' => (int) $r->tiempo,
                'fecha' => (string) $r->fecha,
            ])->values();

        return response()->json($filas, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
