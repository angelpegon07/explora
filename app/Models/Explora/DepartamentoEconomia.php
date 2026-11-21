<?php

namespace App\Models\Explora;

/**
 * Sección 1:1 de la ficha: tabla `departamento_economia`.
 *
 * Economía del departamento (columna `economia`).
 * Hereda la clave `departamento` (-> departamento_descripcion.id), el modo solo lectura y los atributos ocultos de {@see SeccionDepartamento}.
 */
class DepartamentoEconomia extends SeccionDepartamento
{
    protected $table = 'departamento_economia';
}
