<?php

namespace App\Models\Explora;

/**
 * Elemento de una lista 1:N de la ficha: tabla `departamento_sitios_list`.
 *
 * Un sitio de interés del departamento: `nombre`, `destacada` y `contenido` (HTML). Es la tabla que más pesa (imágenes en base64, DAT-07).
 * Usa `update_at` como columna de modificación (ver {@see ItemDepartamento}).
 */
class SitioItem extends ItemDepartamento
{
    protected $table = 'departamento_sitios_list';
}
