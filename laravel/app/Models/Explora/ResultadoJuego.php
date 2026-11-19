<?php

namespace App\Models\Explora;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `resultado_juegos`. Hoy `fecha` es texto «d-m-Y H:i:s» (DAT-03); se conserva tal cual hasta que se
 * autorice una migración de datos. `id_usuario` es un id de pedigital.users (0 = invitado), sin FK posible.
 */
class ResultadoJuego extends Model
{
    protected $table = 'resultado_juegos';

    public $timestamps = false;

    protected $fillable = [
        'nombre_juego', 'nivel', 'tiempo', 'jugador', 'avatar', 'grado', 'puntaje', 'categoria', 'id_usuario', 'fecha',
    ];

    protected $casts = ['tiempo' => 'integer', 'puntaje' => 'integer', 'id_usuario' => 'integer'];
}
