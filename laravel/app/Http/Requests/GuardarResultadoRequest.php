<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasDeJuego;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validación de POST /api/resultados (guardar el resultado de una partida).
 *
 * Dos situaciones según haya o no sesión (regla de negocio del MODO INVITADO, FUN-09):
 *  - CON sesión: nombre, grado y avatar salen de la sesión; si el cuerpo los trae, se ignoran. No son obligatorios.
 *  - SIN sesión (invitado): `nombre`, `grado` y `avatar` son obligatorios y estrictos, porque ahí no hay datos fiables.
 *
 * Los textos que acaban mostrándose en el ranking (nombre, categoría) no pueden contener HTML (SEC-05) y los números
 * tienen rango (SEC-06). Nunca se acepta un id de usuario: lo decide la sesión (SEC-03).
 */
class GuardarResultadoRequest extends FormRequest
{
    use ReglasDeJuego;

    /**
     * El modo invitado es público: el acceso lo limitan los límites de uso, no la autorización.
     *
     * @return bool Siempre true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $esInvitado = fn () => $this->user('web') === null;

        return $this->reglasDeJuego() + [
            'puntaje' => ['required', 'integer', 'between:0,'.config('explora.puntaje_max')],
            'tiempo' => ['required', 'integer', 'between:0,'.config('explora.tiempo_max')],
            // Solo se exigen (y validan) para invitados; con sesión se ignoran.
            'nombre' => [Rule::requiredIf($esInvitado), 'nullable', 'string', 'max:60',
                'regex:/^[\p{L}\p{N}][\p{L}\p{N} .\'’_-]{0,59}$/u'],
            'grado' => [Rule::requiredIf($esInvitado), 'nullable', 'regex:/^(?:[1-9]|10|11)$/'],
            'avatar' => [Rule::requiredIf($esInvitado), 'nullable', 'string', function ($atributo, $valor, $fallar) {
                if (! preg_match('/^(\d{1,2})\.png$/', (string) $valor, $m)
                    || (int) $m[1] < 1 || (int) $m[1] > config('explora.avatares_max')) {
                    $fallar('El avatar debe ser «N.png» con N entre 1 y '.config('explora.avatares_max').'.');
                }
            }],
        ];
    }

    /**
     * Responde 422 con la forma que usa el frontend para los errores de guardado (`status` + `mensaje`) y los NOMBRES
     * de los campos inválidos (sin eco de los valores recibidos).
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'mensaje' => 'Los datos del resultado no son válidos.',
            'campos' => array_keys($validator->errors()->toArray()),
        ], 422, [], JSON_UNESCAPED_UNICODE));
    }
}
