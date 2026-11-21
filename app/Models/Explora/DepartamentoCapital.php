<?php

namespace App\Models\Explora;

/**
 * Sección 1:1 de la ficha: tabla `departamento_capital`.
 *
 * Texto sobre la capital del departamento (columna `capital`).
 * Hereda la clave `departamento` (-> departamento_descripcion.id), el modo solo lectura y los atributos ocultos de {@see SeccionDepartamento}.
 */
class DepartamentoCapital extends SeccionDepartamento
{
    protected $table = 'departamento_capital';
}
