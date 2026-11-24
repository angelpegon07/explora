<?php

namespace App\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Lo que el dominio de Explora necesita saber de una persona con sesión, sin conocer de dónde vienen los datos.
 *
 * Hoy lo implementa `Pedigital\Usuario` (tabla `pedigital.users`, solo lectura). Los controladores, el
 * middleware y los recursos dependen de ESTA interfaz y no de los nombres de columna del legado (`nombre_usuario`,
 * `grado_usuario`…), de modo que cambiar de proveedor de identidad (otra base, SSO…) implica un solo adaptador nuevo.
 * El identificador es `getAuthIdentifier()` (de {@see Authenticatable}).
 */
interface IdentidadUsuario extends Authenticatable
{
    /** Nombre completo para mostrar. */
    public function nombreCompleto(): string;

    /** Nombre de usuario con el que inicia sesión. */
    public function login(): string;

    /** Grado escolar tal como lo guarda el proveedor (texto; vacío si no hay dato). */
    public function grado(): string;

    /** Rol de la persona (Estudiante, Profesor, Administrador, root…), tal como lo guarda el proveedor. */
    public function rol(): string;

    /** Si la cuenta está habilitada. Una cuenta inactiva no puede iniciar sesión ni conservar la sesión (DAT-01). */
    public function esActivo(): bool;
}
