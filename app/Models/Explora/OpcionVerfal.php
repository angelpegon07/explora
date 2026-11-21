<?php

namespace App\Models\Explora;

use App\Models\Concerns\SoloLectura;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta de una pregunta verdadero/falso: tabla `opciones_verfal` (nombre con error ortográfico conservado; `respuesta` = 1 verdadero / 0 falso).
 *
 * No tiene clave foránea en la base de datos hacia `preguntas` (DAT-04).
 */
class OpcionVerfal extends Model
{
    use SoloLectura;

    protected $table = 'opciones_verfal';

    protected $hidden = ['remember_token'];

    protected $guarded = [];

    /**
     * Pregunta a la que pertenece esta opción. Se llama preguntaPadre() porque la tabla tiene una columna `pregunta`
     * (entero) y un método `pregunta()` quedaría oculto por ella.
     *
     * @return BelongsTo Relación N:1 hacia {@see Pregunta}.
     */
    public function preguntaPadre(): BelongsTo
    {
        return $this->belongsTo(Pregunta::class, 'pregunta');
    }
}
