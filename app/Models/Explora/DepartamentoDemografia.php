<?php

namespace App\Models\Explora;

/**
 * Sección 1:1 de la ficha: tabla `departamento_demografia`.
 *
 * Demografía: `poblacion`, `densidad` y `subregiones`.
 * Hereda la clave `departamento` (-> departamento_descripcion.id), el modo solo lectura y los atributos ocultos de {@see SeccionDepartamento}.
 */
class DepartamentoDemografia extends SeccionDepartamento
{
    protected $table = 'departamento_demografia';
}
