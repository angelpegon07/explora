<?php

namespace App\Support\Identidad;

use App\Contracts\IdentidadUsuario;
use App\Contracts\ProveedorIdentidad;
use App\Models\Pedigital\Usuario;

/**
 * Proveedor de identidad respaldado por la plataforma pednia/ambientes (`pedigital.users`, conexión de solo lectura).
 * Es el ÚNICO lugar fuera del propio modelo que conoce las tablas y columnas de `pedigital`.
 */
final class PedigitalProveedorIdentidad implements ProveedorIdentidad
{
    /** {@inheritDoc} */
    public function buscarPorLogin(string $login): ?IdentidadUsuario
    {
        return Usuario::where('login_usuario', $login)->first();
    }

    /** {@inheritDoc} */
    public function avatarHeredado(int $id): ?int
    {
        $valor = Usuario::whereKey($id)->value('avatar_explora');

        return $valor === null ? null : (int) $valor;
    }
}
