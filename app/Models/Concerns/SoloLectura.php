<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Impide guardar o borrar desde Eloquent. Se usa en modelos de contenido que gestiona otra aplicación
 * (panel de administración) y en la BD de la plataforma pednia/ambientes (`pedigital`), que es de solo lectura.
 *
 * Nota: bloquea save()/update()/delete() del modelo (forceDelete() pasa por delete()) (incluido increment/decrement, que disparan `updating`).
 * NO bloquea consultas masivas (Modelo::query()->update()/delete()), insert(), upsert(), DB::table() ni DB::statement():
 * para eso la defensa real son los permisos del usuario MySQL (SELECT únicamente en producción para el contenido).
 * Aquí: la protección real de `pedigital` es el usuario MySQL con solo SELECT.
 */
trait SoloLectura
{
    /**
     * Laravel invoca este método automáticamente al arrancar cualquier modelo que use el trait (convención `boot<Trait>`). Registra los eventos que bloquean la escritura.
     */
    public static function bootSoloLectura(): void
    {
        $bloquear = static function (): never {
            throw new LogicException('Modelo de solo lectura: no se permite escribir ni borrar.');
        };
        static::saving($bloquear);
        static::updating($bloquear);
        static::deleting($bloquear);
    }
}
