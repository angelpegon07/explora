<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revalida en CADA petición que la cuenta de la sesión siga activa (`estado_usuario` = ACTIVO).
 *
 * Por qué: el estado solo se comprobaba al iniciar sesión. Una cuenta que se deshabilita en pednia/ambientes
 * conservaría su sesión abierta hasta que caducara (120 min, y se renueva con la actividad), así que DAT-01
 * quedaba cerrado únicamente en el login. El usuario se vuelve a leer de `pedigital` en cada petición, por lo que
 * este middleware ve siempre el estado vigente.
 *
 * Si no hay sesión, no hace nada (de eso se encarga `auth`).
 */
class CuentaActiva
{
    /**
     * Cierra la sesión y responde 401 si la cuenta ya no está activa; en otro caso continúa.
     *
     * @param  Request  $request  Petición entrante.
     * @param  Closure  $next  Siguiente capa del pipeline.
     * @return Response 401 JSON si la cuenta está inactiva; si no, la respuesta de la capa siguiente.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user('web');

        if ($usuario && ! $usuario->esActivo()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return response()->json(
                ['success' => false, 'mensaje' => 'La cuenta no está activa.'],
                401,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        return $next($request);
    }
}
