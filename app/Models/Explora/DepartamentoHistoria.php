<?php

namespace App\Models\Explora;

/**
 * Sección 1:1 de la ficha: tabla `departamento_historia`.
 *
 * Historia del departamento (columna `historia`, HTML).
 * Hereda la clave `departamento` (-> departamento_descripcion.id), el modo solo lectura y los atributos ocultos de {@see SeccionDepartamento}.
 */
class DepartamentoHistoria extends SeccionDepartamento
{
    protected $table = 'departamento_historia';
}
