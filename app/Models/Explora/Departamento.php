<?php

namespace App\Models\Explora;

use App\Models\Concerns\SoloLectura;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Tabla `list_departamentos` (33 divisiones: 32 departamentos + Bogotá D. C.). */
class Departamento extends Model
{
    use SoloLectura;

    protected $table = 'list_departamentos';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * Descripción de este departamento (1:1): `departamento_descripcion.departamento` apunta a `list_departamentos.id`. Su `id` es la clave de las demás secciones.
     *
     * @return HasOne Relación 1:1 hacia {@see DepartamentoDescripcion}.
     */
    public function descripcion(): HasOne
    {
        return $this->hasOne(DepartamentoDescripcion::class, 'departamento');
    }
}
