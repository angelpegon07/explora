<?php

namespace App\Models\Explora;

/**
 * Sección 1:1 de la ficha: tabla `departamento_cultura`.
 *
 * Cultura del departamento: `descripcion`, `arte`, `danza` y `musica` (HTML).
 * Hereda la clave `departamento` (-> departamento_descripcion.id), el modo solo lectura y los atributos ocultos de {@see SeccionDepartamento}.
 */
class DepartamentoCultura extends SeccionDepartamento
{
    protected $table = 'departamento_cultura';
}
