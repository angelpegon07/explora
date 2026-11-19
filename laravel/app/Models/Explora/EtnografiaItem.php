<?php

namespace App\Models\Explora;

/**
 * Elemento de una lista 1:N de la ficha: tabla `departamento_etnografia_list`.
 *
 * Una etnia del departamento: `nombre`, `nombre_alterno`, `lengua` y `contenido` (HTML).
 * OJO: a diferencia de las otras cinco listas, esta tabla usa `updated_at` (estándar de Laravel) y no `update_at`, por
eso hereda de {@see SeccionDepartamento} y no de {@see ItemDepartamento}.
 */
class EtnografiaItem extends SeccionDepartamento
{
    protected $table = 'departamento_etnografia_list';
}
