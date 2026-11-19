<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasDeJuego;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validación de GET /api/ranking?juego=&nivel=&categoria=.
 *
 * Reutiliza las mismas listas blancas que al guardar ({@see ReglasDeJuego}): un valor que no se podría haber guardado
 * tampoco se puede consultar. El legado concatenaba estos tres valores en SQL (SEC-01) y fallaba si faltaba alguno.
 */
class RankingRequest extends FormRequest
{
    use ReglasDeJuego;

    /**
     * El ranking es público.
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
        return $this->reglasDeJuego();
    }

    /**
     * Responde 422 con `{status:'error', mensaje, campos}`.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'mensaje' => 'Los parámetros del ranking no son válidos.',
            'campos' => array_keys($validator->errors()->toArray()),
        ], 422, [], JSON_UNESCAPED_UNICODE));
    }
}
