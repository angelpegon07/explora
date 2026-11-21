<?php

namespace App\Models\Explora;

use App\Models\Concerns\SoloLectura;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Base de las tablas `departamento_*` (FK `departamento` -> departamento_descripcion.id, NO list_departamentos.id).
 */
abstract class SeccionDepartamento extends Model
{
    use SoloLectura;

    protected $hidden = ['remember_token'];

    protected $guarded = [];

    /**
     * Descripción (eje de la ficha) a la que pertenece esta sección. Se llama ficha() y no descripcion() porque
     * departamento_cultura tiene una columna `descripcion`.
     *
     * @return BelongsTo Relación N:1 hacia {@see DepartamentoDescripcion}.
     */
    public function ficha(): BelongsTo
    {
        return $this->belongsTo(DepartamentoDescripcion::class, 'departamento');
    }
}
