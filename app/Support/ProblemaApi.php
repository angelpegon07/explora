<?php

namespace App\Support;

use App\Exceptions\Handler;
use App\Http\Middleware\ErroresProblemJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Constructor de errores «problem details» (RFC 9457) de la API versionada `/api/v1` (Fase 17E).
 *
 * Por qué existe: los errores del legado `/api/*` son inconsistentes (`{success,mensaje}`, `{error}`, `{message}`,
 * `{status:"error",mensaje,campos}`...). La v1 los unifica en un solo formato, `application/problem+json`, sin romper
 * al frontend actual, que solo lee `mensaje`, `message` y `campos`: esas claves se conservan como miembros de extensión.
 *
 * Seguridad: aquí NUNCA se incluye información interna (excepción, traza, archivo, línea). Quien llame solo debe pasar
 * textos pensados para el usuario; para los errores 500 se usa siempre el texto genérico.
 *
 * Lo usan {@see ErroresProblemJson} (respuestas ya construidas) y
 * {@see Handler} (excepciones, incluidas las que ocurren fuera del middleware de ruta).
 */
final class ProblemaApi
{
    /** Tipo de contenido de los errores de la v1. */
    public const TIPO_CONTENIDO = 'application/problem+json';

    /** Frase estándar (title) en español por código de estado. */
    private const TITULOS = [
        400 => 'Solicitud incorrecta',
        401 => 'No autenticado',
        403 => 'Prohibido',
        404 => 'No encontrado',
        405 => 'Método no permitido',
        408 => 'Tiempo de espera agotado',
        409 => 'Conflicto',
        413 => 'Contenido demasiado grande',
        415 => 'Tipo de contenido no soportado',
        419 => 'Sesión caducada',
        422 => 'Entidad no procesable',
        429 => 'Demasiadas solicitudes',
        500 => 'Error interno del servidor',
        501 => 'No implementado',
        502 => 'Puerta de enlace incorrecta',
        503 => 'Servicio no disponible',
        504 => 'Tiempo de espera de la puerta de enlace agotado',
    ];

    /** Texto de detail por defecto (para el usuario, sin datos internos) por código de estado. */
    private const DETALLES = [
        400 => 'La solicitud no es válida.',
        401 => 'Debe iniciar sesión para continuar.',
        403 => 'No tiene permiso para realizar esta acción.',
        404 => 'Recurso no encontrado.',
        405 => 'Método no permitido para este recurso.',
        419 => 'Su sesión caducó. Vuelva a iniciar sesión.',
        422 => 'Los datos enviados no son válidos.',
        429 => 'Demasiadas solicitudes. Espere un momento e inténtelo de nuevo.',
        500 => 'Error interno del servidor.',
        503 => 'Servicio no disponible temporalmente.',
    ];

    /** Claves del cuerpo legado que se conservan como miembros de extensión (las que lee el frontend). */
    private const CLAVES_LEGADO = ['mensaje', 'message', 'campos', 'success', 'error'];

    /**
     * Indica si la petición pertenece a la API versionada.
     *
     * @param  Request  $request  Petición en curso.
     * @return bool true si la ruta es `api/v1` o `api/v1/*`.
     */
    public static function esV1(Request $request): bool
    {
        return $request->is('api/v1') || $request->is('api/v1/*');
    }

    /**
     * Frase estándar en español de un código de estado.
     *
     * @param  int  $estado  Código HTTP.
     * @return string Título (genérico según la clase del estado si no está en la tabla).
     */
    public static function titulo(int $estado): string
    {
        return self::TITULOS[$estado] ?? ($estado >= 500 ? 'Error del servidor' : 'Error de la solicitud');
    }

    /**
     * Texto de detalle por defecto de un estado.
     *
     * @param  int  $estado  Código HTTP.
     * @return string Mensaje en español apto para el usuario.
     */
    public static function detallePorDefecto(int $estado): string
    {
        return self::DETALLES[$estado] ?? ($estado >= 500 ? self::DETALLES[500] : self::DETALLES[400]);
    }

    /**
     * Construye el cuerpo RFC 9457 con las extensiones indicadas.
     *
     * `mensaje` y `message` siempre existen y valen lo mismo que `detail` salvo que las extensiones traigan otro valor
     * (el frontend lee ambas claves). Las extensiones nunca pueden pisar los miembros estándar.
     *
     * @param  Request  $request  Petición (de ella sale `instance`).
     * @param  int  $estado  Código HTTP.
     * @param  string|null  $detalle  Texto para el usuario; null usa el de por defecto del estado.
     * @param  array<string, mixed>  $extensiones  Miembros de extensión (campos, errors, success...).
     * @return array<string, mixed> Cuerpo listo para serializar.
     */
    public static function cuerpo(Request $request, int $estado, ?string $detalle = null, array $extensiones = []): array
    {
        $detalle = ($detalle !== null && $detalle !== '') ? $detalle : self::detallePorDefecto($estado);

        unset($extensiones['type'], $extensiones['title'], $extensiones['status'], $extensiones['detail'], $extensiones['instance']);

        return [
            'type' => 'about:blank',
            'title' => self::titulo($estado),
            'status' => $estado,
            'detail' => $detalle,
            'instance' => '/'.ltrim($request->path(), '/'),
        ] + $extensiones + ['mensaje' => $detalle, 'message' => $detalle];
    }

    /**
     * Convierte el cuerpo de error del legado en un cuerpo RFC 9457.
     *
     * El `detail` sale de `mensaje`, `message` o `error` (en ese orden) si son texto; se conservan `mensaje`, `message`,
     * `campos`, `success` y `error`. La clave `status:"error"` se descarta (chocaría con el `status` entero). Cualquier
     * otra clave del cuerpo original se ignora.
     *
     * @param  Request  $request  Petición en curso.
     * @param  int  $estado  Código HTTP de la respuesta original.
     * @param  mixed  $original  Cuerpo JSON ya decodificado (puede no ser un arreglo).
     * @return array<string, mixed> Cuerpo problem+json.
     */
    public static function desdeCuerpoLegado(Request $request, int $estado, mixed $original): array
    {
        $original = is_array($original) ? $original : [];
        $detalle = null;
        foreach (['mensaje', 'message', 'error'] as $clave) {
            if (isset($original[$clave]) && is_string($original[$clave]) && $original[$clave] !== '') {
                $detalle = $original[$clave];
                break;
            }
        }

        $extensiones = array_intersect_key($original, array_flip(self::CLAVES_LEGADO));

        return self::cuerpo($request, $estado, $detalle, $extensiones);
    }

    /**
     * Respuesta problem+json completa.
     *
     * @param  Request  $request  Petición en curso.
     * @param  int  $estado  Código HTTP.
     * @param  string|null  $detalle  Texto para el usuario (null = el de por defecto).
     * @param  array<string, mixed>  $extensiones  Miembros de extensión.
     * @param  array<string, string>  $cabeceras  Cabeceras a conservar (Retry-After, Allow...).
     */
    public static function respuesta(Request $request, int $estado, ?string $detalle = null, array $extensiones = [], array $cabeceras = []): JsonResponse
    {
        unset($cabeceras['Content-Type'], $cabeceras['content-type']);

        return new JsonResponse(
            self::cuerpo($request, $estado, $detalle, $extensiones),
            $estado,
            $cabeceras + ['Content-Type' => self::TIPO_CONTENIDO],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * Reescribe, si procede, una respuesta ya construida al formato problem+json.
     *
     * Solo actúa sobre errores (estado >= 400) JSON que aún no son problem+json (es idempotente); conserva el resto de
     * cabeceras (Retry-After, X-RateLimit-*, Allow...). Lo usan el middleware y el manejador de excepciones.
     *
     * @param  Request  $request  Petición en curso.
     * @param  Response  $respuesta  Respuesta original (se modifica y se devuelve).
     * @return Response La misma respuesta, reescrita o intacta.
     */
    public static function convertir(Request $request, Response $respuesta): Response
    {
        $estado = $respuesta->getStatusCode();
        $tipo = strtolower((string) $respuesta->headers->get('Content-Type'));

        if ($estado < 400 || ! str_contains($tipo, 'json') || str_contains($tipo, 'problem+json')) {
            return $respuesta;
        }

        $cuerpo = self::desdeCuerpoLegado($request, $estado, json_decode((string) $respuesta->getContent(), true));

        $respuesta->setContent((string) json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $respuesta->headers->set('Content-Type', self::TIPO_CONTENIDO);
        $respuesta->headers->remove('Content-Length');

        return $respuesta;
    }
}
