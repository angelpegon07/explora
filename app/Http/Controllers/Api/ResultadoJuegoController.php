<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarResultadoRequest;
use App\Models\Explora\PerfilExplora;
use App\Models\Explora\ResultadoJuego;
use App\Support\CacheRanking;
use Illuminate\Http\JsonResponse;

/**
 * Guarda el resultado de una partida (reemplaza a juegos/php/guardar_resultado.php).
 *
 * Ruta: POST /api/resultados (grupo `web`: CSRF + sesión opcional; límite de uso `throttle:resultados`).
 *
 * Cambios de seguridad frente al legado: SEC-01 (Eloquent, sin SQL concatenado), SEC-03 (el id de usuario sale de la
 * sesión, nunca del cliente), SEC-05 (nombre y categoría validados: no pueden llevar HTML) y SEC-06 (rangos de
 * puntaje y tiempo). El puntaje lo calcula el navegador, así que sigue sin poder garantizarse su honestidad.
 */
class ResultadoJuegoController extends Controller
{
    /**
     * Guarda el resultado.
     *
     * - Con sesión: `id_usuario`, `jugador`, `grado` y `avatar` se toman del usuario autenticado (y del perfil propio de
     *   Explora para el avatar); lo que traiga el cuerpo se ignora. Una persona sin grado (p. ej. profesores) se
     *   guarda con grado vacío.
     * - Sin sesión (invitado, FUN-09): `id_usuario = 0` y los datos del cuerpo, ya validados.
     *
     * `fecha` se guarda como texto «d-m-Y H:i:s» (zona horaria de la aplicación, UTC), igual que el legado, porque la
     * columna es de tipo texto (DAT-03) y el historial del frontend la separa por el espacio.
     *
     * Doble escritura (Fase 16B, patrón expandir/contraer de DAT-03): se escribe además `creado_en` (DATETIME) con el
     * MISMO instante que `fecha`, tomado de una sola llamada a `now()` para que ambas columnas no puedan diferir ni
     * siquiera por un cambio de segundo. Requiere la migración `2026_11_21_000003_…` aplicada antes de desplegar.
     *
     * @param  GuardarResultadoRequest  $request  Cuerpo ya validado.
     * @return JsonResponse 201 `{status:'success', mensaje}`; 422 si los datos no son válidos; 401 si la cuenta de la
     *                      sesión fue desactivada (middleware `cuenta.activa`); 429 por exceso de uso.
     */
    public function store(GuardarResultadoRequest $request): JsonResponse
    {
        $datos = $request->validated();
        $usuario = $request->user('web');
        $ahora = now();

        ResultadoJuego::create([
            'nombre_juego' => $datos['juego'],
            'nivel' => $datos['nivel'],
            'categoria' => $datos['categoria'],
            'puntaje' => $datos['puntaje'],
            'tiempo' => $datos['tiempo'],
            'id_usuario' => $usuario ? (int) $usuario->getAuthIdentifier() : 0,
            'jugador' => $usuario ? $usuario->nombreCompleto() : $datos['nombre'],
            'grado' => $usuario ? $usuario->grado() : (string) $datos['grado'],
            'avatar' => $usuario ? PerfilExplora::avatarDe((int) $usuario->getAuthIdentifier()).'.png' : $datos['avatar'],
            'fecha' => $ahora->format('d-m-Y H:i:s'),
            'creado_en' => $ahora,
        ]);

        // El juego pide el ranking justo después de guardar: la caché del ranking no puede quedar vieja.
        CacheRanking::olvidar();

        return response()->json(
            ['status' => 'success', 'mensaje' => 'Resultado guardado correctamente'],
            201,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }
}
