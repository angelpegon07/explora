<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validación del inicio de sesión (POST /api/sesion).
 *
 * Los nombres de campo (`usuario`, `contrasena`) son los mismos que usa el formulario actual
 * (mapplic/js/iniciarSesion.js), para poder conmutar el frontend sin cambiar el formulario.
 *
 * Solo se validan tipo y longitud: la corrección de las credenciales la decide el controlador. El máximo
 * de `usuario` (45) coincide con la columna `pedigital.users.login_usuario` (varchar(45)); el de `contrasena` (200) es un
 * tope propio (la columna del hash mide 100 pero la clave en claro puede ser más larga) que evita enviar cadenas
 * enormes al algoritmo de hash (denegación de servicio).
 */
class LoginRequest extends FormRequest
{
    /**
     * El inicio de sesión es público: la autorización se decide por las credenciales.
     *
     * @return bool Siempre true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'usuario' => ['required', 'string', 'max:45'],
            'contrasena' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * Responde con la misma forma que el resto del contrato de sesión (`success` + `mensaje`) y HTTP 422,
     * en lugar de la estructura estándar de errores de validación de Laravel.
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'mensaje' => 'Por favor, ingrese su usuario y contraseña.',
        ], 422, [], JSON_UNESCAPED_UNICODE));
    }
}
