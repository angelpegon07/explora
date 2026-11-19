<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Exige sesión en las rutas con `auth`. Ver {@see self::redirectTo()}: nunca redirige, responde 401.
 */
class Authenticate extends Middleware
{
    /**
     * Explora no tiene página de inicio de sesión de Laravel (el formulario es un modal del sitio estático), así que
     * NUNCA se redirige: una petición sin sesión recibe 401. Devolver null evita además el error «Route [login] not
     * defined» cuando el cliente no envía `Accept: application/json`.
     *
     * @param  Request  $request  Petición sin sesión.
     * @return string|null Siempre null (sin redirección).
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
