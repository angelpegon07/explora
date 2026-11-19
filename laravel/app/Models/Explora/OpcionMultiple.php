<?php

namespace App\Models\Explora;

use App\Models\Concerns\SoloLectura;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opción de respuesta de una pregunta de selección múltiple: tabla `opciones_multiple` (`opcion` = texto, `correcta` = 1 si es la correcta).
 *
 * Es de solo lectura. NO debe devolverse al cliente tal cual: la API usa PreguntasDepartamentoResource (lista blanca, SEC-06).
 */
class OpcionMultiple extends Model
{
    use SoloLectura;

    protected $table = 'opciones_multiple';

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
