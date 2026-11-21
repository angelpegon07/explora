<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarAvatarRequest;
use App\Models\Explora\PerfilExplora;
use Illuminate\Http\JsonResponse;

/**
 * Cambio de avatar del usuario con sesión (reemplaza a mapplic/php/cambiarAvatar.php).
 *
 * Ruta: PATCH /api/perfil/avatar (middleware `web` + `auth:web` + `cuenta.activa`).
 *
 * Diferencias de seguridad respecto al legado (SEC-01, SEC-03):
 *  - Solo actúa sobre el usuario de la SESIÓN; el cuerpo no puede indicar otro usuario.
 *  - El valor está validado (entero entre 1 y el máximo) y se guarda con Eloquent (sin SQL concatenado).
 *  - No escribe en `pedigital` (de solo lectura): el avatar vive en `explora.perfiles_explora`.
 */
class PerfilAvatarController extends Controller
{
    /**
     * Guarda el avatar elegido en el perfil del usuario autenticado (lo crea si no existe).
     *
     * @return JsonResponse 200 `{status:'success', message, avatar}`; 401 sin sesión; 422 si el avatar no es válido.
     */
    public function __invoke(CambiarAvatarRequest $request): JsonResponse
    {
        // Dos primeras peticiones simultáneas del mismo usuario pueden chocar con la clave única (`usuario_id`):
        // se reintenta una vez, y en el reintento la fila ya existe y se actualiza.
        $perfil = retry(2, fn () => PerfilExplora::updateOrCreate(
            ['usuario_id' => $request->user('web')->getAuthIdentifier()],
            ['avatar' => $request->validated('avatar')],
        ), 50);

        return response()->json([
            'status' => 'success',
            'message' => 'Avatar cambiado correctamente',
            'avatar' => $perfil->avatar,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
