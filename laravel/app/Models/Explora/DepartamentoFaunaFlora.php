<?php

namespace App\Models\Explora;

/**
 * Sección 1:1 de la ficha: tabla `departamento_fauna_flora`.
 *
 * Textos generales de fauna y flora (`fauna`, `flora`). Los elementos individuales están en FaunaItem y FloraItem.
 * Hereda la clave `departamento` (-> departamento_descripcion.id), el modo solo lectura y los atributos ocultos de {@see SeccionDepartamento}.
 */
class DepartamentoFaunaFlora extends SeccionDepartamento
{
    protected $table = 'departamento_fauna_flora';
}
