<?php

namespace App\Models\Explora;

/**
 * Base de CINCO de las seis listas `departamento_*_list` (fauna, flora, festividad, personaje y sitios): su columna de
modificación es `update_at` (sin «d») en lugar de `updated_at` (DAT-05). La sexta, `departamento_etnografia_list`, sí
usa `updated_at` y por eso EtnografiaItem NO hereda de esta clase.
 */
abstract class ItemDepartamento extends SeccionDepartamento
{
    public const UPDATED_AT = 'update_at';
}
