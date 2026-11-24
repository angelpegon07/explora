<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Manejador global de excepciones.
 *
 * Única personalización: toda la ruta `api/*` responde JSON (404, 401, 422, errores) aunque el cliente no envíe
 * `Accept: application/json`, porque el frontend usa jQuery y no siempre lo envía.
 */
class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'contrasena', // contraseña de Explora: nunca se reinyecta en formularios tras un error
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Mensajes en español para la API (antes salían los de Laravel en inglés: «Too Many Attempts.», etc.).
        // Se conservan `message` (compatibilidad) y `mensaje` (forma del contrato de sesión).
        $this->renderable(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return $this->respuestaApi('Demasiadas solicitudes. Espere un momento e inténtelo de nuevo.', 429, $e->getHeaders());
            }
        });
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->is('api')) {
                return $this->respuestaApi('Recurso no encontrado.', 404);
            }
        });
        $this->renderable(function (TokenMismatchException $e, Request $request) {
            if ($request->is('api/*')) {
                return $this->respuestaApi('Su sesión caducó. Vuelva a iniciar sesión.', 419);
            }
        });
    }

    /**
     * Toda la API responde JSON (incluidos 404 y errores), aunque el cliente no envíe `Accept: application/json`.
     *
     * @param  Request  $request  Petición que originó la excepción.
     * @param  Throwable  $e  Excepción que se va a mostrar.
     * @return bool true si la respuesta debe ser JSON (siempre en `api/*`).
     */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return $request->is('api/*') || parent::shouldReturnJson($request, $e);
    }

    /**
     * Respuesta JSON de error uniforme de la API.
     *
     * @param  string  $mensaje  Texto en español para el usuario.
     * @param  int  $estado  Código HTTP.
     * @param  array<string, string>  $cabeceras  Cabeceras a conservar (p. ej. Retry-After).
     */
    private function respuestaApi(string $mensaje, int $estado, array $cabeceras = []): JsonResponse
    {
        return response()->json(
            ['success' => false, 'message' => $mensaje, 'mensaje' => $mensaje],
            $estado,
            $cabeceras,
            JSON_UNESCAPED_UNICODE
        );
    }
}
