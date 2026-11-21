<?php

namespace App\Models\Explora;

use Illuminate\Database\Eloquent\Model;

/**
 * Tabla `resultado_juegos`.
 *
 * DAT-03, patrón expandir/contraer: `fecha` sigue siendo texto «d-m-Y H:i:s» (la API y el frontend la consumen así) y,
 * desde la Fase 16B, cada partida se escribe TAMBIÉN en `creado_en` (DATETIME, ordenable e indexable). Las lecturas aún
 * usan `fecha`; cuando se autorice la fase «contraer» se leerá `creado_en` y se retirará `fecha`.
 * `creado_en` exige la migración `2026_11_21_000003_…` aplicada ANTES de desplegar este código.
 * `id_usuario` es un id de pedigital.users (0 = invitado), sin FK posible (otra base de datos).
 */
class ResultadoJuego extends Model
{
    protected $table = 'resultado_juegos';

    public $timestamps = false;

    protected $fillable = [
        'nombre_juego', 'nivel', 'tiempo', 'jugador', 'avatar', 'grado', 'puntaje', 'categoria', 'id_usuario', 'fecha',
        'creado_en',
    ];

    protected $casts = ['tiempo' => 'integer', 'puntaje' => 'integer', 'id_usuario' => 'integer', 'creado_en' => 'datetime'];
}
