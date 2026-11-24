<?php

namespace App\Contracts;

use App\Providers\AppServiceProvider;
use App\Support\Identidad\PedigitalProveedorIdentidad;

/**
 * Origen de las identidades de Explora (consultas de SOLO LECTURA; Explora no crea ni modifica cuentas).
 *
 * La implementación vigente es {@see PedigitalProveedorIdentidad}, registrada en
 * {@see AppServiceProvider}. La autenticación de Laravel (guard `web`) sigue usando el modelo configurado en
 * `config/auth.php`; ese modelo debe implementar {@see IdentidadUsuario}.
 */
interface ProveedorIdentidad
{
    /**
     * Busca una cuenta por su nombre de usuario (comparación de la base, sin distinguir mayúsculas según su colación).
     *
     * @param  string  $login  Nombre de usuario escrito en el formulario.
     * @return IdentidadUsuario|null La cuenta, o null si no existe.
     */
    public function buscarPorLogin(string $login): ?IdentidadUsuario;

    /**
     * Avatar que el proveedor tenía guardado antes de que Explora tuviera perfil propio (valor heredado).
     *
     * @param  int  $id  Identificador de la cuenta.
     * @return int|null El número guardado, o null si no hay dato o la cuenta no existe.
     */
    public function avatarHeredado(int $id): ?int;
}
