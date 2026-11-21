<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;

class TrimStrings extends Middleware
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array<int, string>
     */
    protected $except = [
        'current_password',
        // La contraseña de Explora se envía como `contrasena`: recortarla impediría entrar a quien tenga espacios
        // al principio o al final (el legado no los recortaba).
        'contrasena',
        'password',
        'password_confirmation',
    ];
}
