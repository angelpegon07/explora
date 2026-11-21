<?php

namespace App\Models\Explora;

/**
 * Sección 1:1 de la ficha: tabla `departamento_gastronomia`.
 *
 * Gastronomía del departamento (columna `gastronomia`).
 * Hereda la clave `departamento` (-> departamento_descripcion.id), el modo solo lectura y los atributos ocultos de {@see SeccionDepartamento}.
 */
class DepartamentoGastronomia extends SeccionDepartamento
{
    protected $table = 'departamento_gastronomia';
}
