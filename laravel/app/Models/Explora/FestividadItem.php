<?php

namespace App\Models\Explora;

/**
 * Elemento de una lista 1:N de la ficha: tabla `departamento_festividad_list`.
 *
 * Una festividad del departamento: `nombre`, `destacada` y `contenido` (HTML).
 * Usa `update_at` como columna de modificación (ver {@see ItemDepartamento}).
 */
class FestividadItem extends ItemDepartamento
{
    protected $table = 'departamento_festividad_list';
}
