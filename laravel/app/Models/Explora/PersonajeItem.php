<?php

namespace App\Models\Explora;

/**
 * Elemento de una lista 1:N de la ficha: tabla `departamento_personaje_list`.
 *
 * Un personaje del departamento: `nombre`, `destacada` y `contenido` (HTML).
 * Usa `update_at` como columna de modificación (ver {@see ItemDepartamento}).
 */
class PersonajeItem extends ItemDepartamento
{
    protected $table = 'departamento_personaje_list';
}
