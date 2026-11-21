<?php

namespace App\Models\Explora;

/**
 * Sección 1:1 de la ficha: tabla `departamento_referencia`.
 *
 * Referencias bibliográficas (`referencia`, `webgrafia`). No se expone en la ficha actual.
 * Hereda la clave `departamento` (-> departamento_descripcion.id), el modo solo lectura y los atributos ocultos de {@see SeccionDepartamento}.
 */
class DepartamentoReferencia extends SeccionDepartamento
{
    protected $table = 'departamento_referencia';
}
