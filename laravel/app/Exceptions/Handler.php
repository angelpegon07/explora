<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
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
    }

    /** Toda la API responde JSON (incluidos 404 y errores), aunque el cliente no envíe `Accept: application/json`. */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return $request->is('api/*') || parent::shouldReturnJson($request, $e);
    }
}
