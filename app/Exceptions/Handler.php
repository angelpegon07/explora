<?php

namespace App\Exceptions;

use App\Support\ProblemaApi;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Manejador global de excepciones.
 *
 * Única personalización: toda la ruta `api/*` responde JSON (404, 401, 422, errores) aunque el cliente no envíe
 * `Accept: application/json`, porque el frontend usa jQuery y no siempre lo envía.
 *
 * Fase 17E: `api/v1/*` responde SIEMPRE en RFC 9457 (`application/problem+json`, ver {@see ProblemaApi}) y sin datos
 * internos aunque APP_DEBUG=true; `api/*` (legado) conserva sus cuerpos. Aquí se cubre lo que el middleware de ruta
 * `ErroresProblemJson` no ve: 404/405 previos a elegir ruta, el límite `throttle:api` y los 500.
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
        // 405 del legado: antes salía el texto en inglés de Laravel; ahora JSON en español con `Allow`.
        $this->renderable(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*') && ! ProblemaApi::esV1($request)) {
                return $this->respuestaApi('Método no permitido.', 405, $e->getHeaders());
            }
        });
        $this->renderable(function (TokenMismatchException $e, Request $request) {
            if ($request->is('api/*')) {
                return $this->respuestaApi('Su sesión caducó. Vuelva a iniciar sesión.', 419);
            }
        });
    }

    /**
     * Render de excepciones: la v1 se responde en problem+json sin detalles internos; el resto, como siempre.
     *
     * @param  Request  $request  Petición que originó la excepción.
     * @param  Throwable  $e  Excepción.
     * @return Response Respuesta de error.
     */
    public function render($request, Throwable $e)
    {
        if (ProblemaApi::esV1($request)) {
            return $this->respuestaProblema($request, $this->mapException($e));
        }

        return parent::render($request, $e);
    }

    /**
     * Traduce una excepción a problem+json. Nunca expone el mensaje de la excepción salvo en validación (donde son
     * textos de reglas pensados para el usuario) y en los textos fijos de este método.
     *
     * @param  Request  $request  Petición en curso.
     * @param  Throwable  $e  Excepción ya mapeada.
     */
    private function respuestaProblema(Request $request, Throwable $e): Response
    {
        // Limitadores con respuesta propia (`throttle:login`...) y FormRequests: ya traen su respuesta; se reescribe.
        if ($e instanceof HttpResponseException) {
            return ProblemaApi::convertir($request, $e->getResponse());
        }
        if ($e instanceof AuthenticationException) {
            return ProblemaApi::respuesta($request, 401);
        }
        if ($e instanceof ValidationException) {
            $errores = $e->errors();

            return ProblemaApi::respuesta($request, 422, null, [
                'errors' => $errores,
                'campos' => array_keys($errores),
            ]);
        }

        $e = $this->prepareException($e);
        if ($e instanceof ValidationException) { // por si prepareException convierte a validación
            return ProblemaApi::respuesta($request, 422, null, ['errors' => $e->errors()]);
        }
        if ($e instanceof HttpExceptionInterface) {
            $cabeceras = array_intersect_key($e->getHeaders(), array_flip(['Allow', 'Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining']));

            return ProblemaApi::respuesta($request, $e->getStatusCode(), null, [], $cabeceras);
        }

        return ProblemaApi::respuesta($request, 500);
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
