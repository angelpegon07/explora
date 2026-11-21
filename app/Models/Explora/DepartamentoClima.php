<?php

namespace App\Models\Explora;

/**
 * Sección 1:1 de la ficha: tabla `departamento_clima`.
 *
 * Descripción del clima del departamento (columna `clima`).
 * Hereda la clave `departamento` (-> departamento_descripcion.id), el modo solo lectura y los atributos ocultos de {@see SeccionDepartamento}.
 */
class DepartamentoClima extends SeccionDepartamento
{
    protected $table = 'departamento_clima';
}
