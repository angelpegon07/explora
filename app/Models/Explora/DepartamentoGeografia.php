<?php

namespace App\Models\Explora;

/**
 * Sección 1:1 de la ficha: tabla `departamento_geografia`.
 *
 * Geografía: `ubicacion`, `coordenadas`, `limites` y `relieve`.
 * Hereda la clave `departamento` (-> departamento_descripcion.id), el modo solo lectura y los atributos ocultos de {@see SeccionDepartamento}.
 */
class DepartamentoGeografia extends SeccionDepartamento
{
    protected $table = 'departamento_geografia';
}
