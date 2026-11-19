<?php

namespace App\Models\Explora;

/**
 * Sección 1:1 de la ficha: tabla `departamento_hidrografia`.
 *
 * Hidrografía del departamento (columna `hidrografia`).
 * Hereda la clave `departamento` (-> departamento_descripcion.id), el modo solo lectura y los atributos ocultos de {@see SeccionDepartamento}.
 */
class DepartamentoHidrografia extends SeccionDepartamento
{
    protected $table = 'departamento_hidrografia';
}
