<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Perfil propio de Explora por usuario. Decisión del responsable (2026-11-18): el avatar se guarda en `explora`
 * y NO en pedigital.users.avatar_explora, porque la BD de pednia/ambientes es de solo lectura para Explora.
 *
 * - `usuario_id` es pedigital.users.id (otra base de datos: no se puede declarar FK).
 * - Sin fila de perfil, el avatar es el valor actual de pedigital.users.avatar_explora (lectura), así que no hace
 *   falta copiar datos para empezar.
 * - Migración NUEVA y aditiva: solo crea una tabla y es idempotente.
 */
return new class extends Migration
{
    /**
     * Crea `perfiles_explora` si no existe.
     */
    public function up(): void
    {
        if (! Schema::hasTable('perfiles_explora')) {
            Schema::create('perfiles_explora', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->id();
                $table->unsignedInteger('usuario_id')->unique();
                $table->unsignedSmallInteger('avatar')->default(1);
                $table->timestamps();
            });
        }
    }

    /**
     * Elimina la tabla solo si está vacía, para no destruir perfiles de usuarios.
     */
    public function down(): void
    {
        // Solo se elimina si está vacía (no se destruyen datos de usuarios).
        if (Schema::hasTable('perfiles_explora') && DB::table('perfiles_explora')->count() === 0) {
            Schema::drop('perfiles_explora');
        }
    }
};
