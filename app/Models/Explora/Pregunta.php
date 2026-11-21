<?php

namespace App\Models\Explora;

use App\Models\Concerns\SoloLectura;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabla `preguntas`. `departamento` es departamento_descripcion.id (sin FK en BD, DAT-04).
 * tipo_pregunta: «multiple» (opciones_multiple) o «verfal» (opciones_verfal, sic).
 */
class Pregunta extends Model
{
    use SoloLectura;

    protected $table = 'preguntas';

    protected $hidden = ['remember_token'];

    protected $guarded = [];

    /**
     * Descripción del departamento a la que pertenece la pregunta (la columna `departamento` guarda el id de departamento_descripcion).
     *
     * @return BelongsTo Relación N:1 hacia {@see DepartamentoDescripcion}.
     */
    public function descripcion(): BelongsTo
    {
        return $this->belongsTo(DepartamentoDescripcion::class, 'departamento');
    }

    /**
     * Opciones de una pregunta de tipo «multiple» (cada una con su indicador `correcta`).
     *
     * @return HasMany Relación 1:N hacia {@see OpcionMultiple}.
     */
    public function opcionesMultiple(): HasMany
    {
        return $this->hasMany(OpcionMultiple::class, 'pregunta');
    }

    /**
     * Respuesta de una pregunta de tipo «verfal» (verdadero/falso): una sola fila con `respuesta`. La tabla se llama `opciones_verfal` (sic).
     *
     * @return HasMany Relación 1:N hacia {@see OpcionVerfal}.
     */
    public function opcionesVerdaderoFalso(): HasMany
    {
        return $this->hasMany(OpcionVerfal::class, 'pregunta');
    }
}
