<?php

namespace App\Models\Explora;

/**
 * Sección 1:1 de la ficha: tabla `departamento_etnografia`.
 *
 * Texto general de etnografía (`contenido`, columna LONGBLOB que contiene HTML). Las etnias individuales están en EtnografiaItem.
 * Hereda la clave `departamento` (-> departamento_descripcion.id), el modo solo lectura y los atributos ocultos de {@see SeccionDepartamento}.
 */
class DepartamentoEtnografia extends SeccionDepartamento
{
    protected $table = 'departamento_etnografia';
}
