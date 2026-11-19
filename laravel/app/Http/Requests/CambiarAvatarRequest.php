<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validación del cambio de avatar (PATCH /api/perfil/avatar).
 *
 * Solo se acepta `avatar`: el usuario se toma SIEMPRE de la sesión, nunca del cuerpo de la petición (SEC-03:
 * el legado confiaba en el `id_usuario` enviado por el cliente y permitía cambiar el avatar de cualquiera).
 */
class CambiarAvatarRequest extends FormRequest
{
    /**
     * El acceso lo controla el middleware `auth:web` de la ruta.
     *
     * @return bool Siempre true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El avatar es un número entero de 1 al máximo configurado (`config('explora.avatares_max')`, que corresponde
     * a las imágenes `juegos/images/avatars/N.png`).
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'integer', 'between:1,'.config('explora.avatares_max')],
        ];
    }

    /**
     * Respuesta 422 con la misma forma que usa el frontend actual para errores (`status` + `message`).
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'Avatar no válido.',
        ], 422, [], JSON_UNESCAPED_UNICODE));
    }
}
