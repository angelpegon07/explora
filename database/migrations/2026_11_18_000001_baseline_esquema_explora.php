<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migración BASE: reproduce el esquema REAL de la BD `explora` (39 tablas, obtenido de explora.sql).
 *
 * - Es idempotente: cada tabla se crea solo si NO existe, de modo que es inocua sobre la BD actual.
 * - Replica la estructura tal cual está hoy, INCLUIDOS sus defectos (FK autorreferenciadas en `regiones_*`,
 *   `fecha` como texto en `resultado_juegos`, FK ausentes, nombres con errores como `opciones_verfal`).
 *   Corregirlos exige migraciones posteriores, no destructivas y con autorización (ver DAT-02..DAT-06).
 * - down() NO elimina tablas (acción destructiva; requiere autorización expresa).
 * - Generada a partir del esquema real; no contiene datos.
 */
return new class extends Migration
{
    /**
     * Crea cada tabla de `explora` solo si no existe (idempotente): sobre la base actual no cambia nada; sobre una base vacía
     * reproduce el esquema real. Las tablas con clave foránea se crean después de la tabla que referencian.
     */
    public function up(): void
    {
        if (! Schema::hasTable('departamento_descripcion')) {
            Schema::create('departamento_descripcion', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->string('poblacion', 20)->nullable();
                $table->string('clima', 50)->nullable();
                $table->string('temperatura', 100)->nullable();
                $table->string('extension', 20)->nullable();
                $table->longText('contenido')->nullable();
                $table->longText('geografia')->nullable();
                $table->longText('actividad_economica')->nullable();
                $table->longText('bandera')->nullable();
                $table->longText('escudo')->nullable();
                $table->string('estado', 10)->nullable();
                $table->string('estado_registro', 10)->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('departamento_capital')) {
            Schema::create('departamento_capital', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->longText('capital')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['departamento'], 'departamento');
                $table->foreign('departamento', 'departamento')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_clima')) {
            Schema::create('departamento_clima', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->longText('clima')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['departamento'], 'depart_clima');
                $table->foreign('departamento', 'depart_clima')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_cultura')) {
            Schema::create('departamento_cultura', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->longText('descripcion')->nullable();
                $table->longText('arte')->nullable();
                $table->longText('danza')->nullable();
                $table->longText('musica')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['departamento'], 'depart_cultura');
                $table->foreign('departamento', 'depart_cultura')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_demografia')) {
            Schema::create('departamento_demografia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->longText('poblacion')->nullable();
                $table->longText('densidad')->nullable();
                $table->longText('subregiones')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['departamento'], 'depart_demo');
                $table->foreign('departamento', 'depart_demo')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_economia')) {
            Schema::create('departamento_economia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->longText('economia')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['departamento'], 'depart_economia');
                $table->foreign('departamento', 'depart_economia')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_etnografia')) {
            Schema::create('departamento_etnografia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->longText('contenido')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['departamento'], 'depart_etnografia');
                $table->foreign('departamento', 'depart_etnografia')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
            DB::statement('ALTER TABLE `departamento_etnografia` MODIFY `contenido` LONGBLOB NULL');
        }
        if (! Schema::hasTable('departamento_etnografia_list')) {
            Schema::create('departamento_etnografia_list', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->string('nombre', 250)->nullable();
                $table->string('nombre_alterno', 200)->nullable();
                $table->string('lengua', 200)->nullable();
                $table->longText('contenido')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['departamento'], 'depart_etnografia_list');
                $table->foreign('departamento', 'depart_etnografia_list')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_fauna_flora')) {
            Schema::create('departamento_fauna_flora', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->longText('fauna')->nullable();
                $table->longText('flora')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['departamento'], 'depart_fauna');
                $table->foreign('departamento', 'depart_fauna')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_fauna_list')) {
            Schema::create('departamento_fauna_list', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->string('nombre', 250)->nullable();
                $table->longText('contenido')->nullable();
                $table->char('destacada', 2)->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('update_at')->useCurrent();
                $table->index(['departamento'], 'depart_fest_list');
                $table->foreign('departamento', 'depart_fauna_list')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_festividad_list')) {
            Schema::create('departamento_festividad_list', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->string('nombre', 250)->nullable();
                $table->longText('contenido')->nullable();
                $table->char('destacada', 2)->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('update_at')->useCurrent();
                $table->index(['departamento'], 'depart_fest_list');
                $table->foreign('departamento', 'depart_fest_list')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_flora_list')) {
            Schema::create('departamento_flora_list', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->string('nombre', 250)->nullable();
                $table->longText('contenido')->nullable();
                $table->char('destacada', 2)->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('update_at')->useCurrent();
                $table->index(['departamento'], 'depart_fest_list');
                $table->foreign('departamento', 'depart_flora_list')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_gastronomia')) {
            Schema::create('departamento_gastronomia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->longText('gastronomia')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['departamento'], 'depart_gastronomia');
                $table->foreign('departamento', 'depart_gastronomia')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_geografia')) {
            Schema::create('departamento_geografia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->longText('ubicacion')->nullable();
                $table->longText('coordenadas')->nullable();
                $table->longText('limites')->nullable();
                $table->longText('relieve')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['departamento'], 'depart_geografia');
                $table->foreign('departamento', 'depart_geografia')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_hidrografia')) {
            Schema::create('departamento_hidrografia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->longText('hidrografia')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['departamento'], 'depart_hidrografia');
                $table->foreign('departamento', 'depart_hidrografia')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_historia')) {
            Schema::create('departamento_historia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->longText('historia')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['departamento'], 'depart_historia');
                $table->foreign('departamento', 'depart_historia')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_personaje_list')) {
            Schema::create('departamento_personaje_list', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->string('nombre', 250)->nullable();
                $table->longText('contenido')->nullable();
                $table->char('destacada', 2)->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('update_at')->useCurrent();
                $table->index(['departamento'], 'depart_perso_list');
                $table->foreign('departamento', 'depart_perso_list')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_referencia')) {
            Schema::create('departamento_referencia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->longText('referencia')->nullable();
                $table->longText('webgrafia')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['departamento'], 'depart_refe');
                $table->foreign('departamento', 'depart_refe')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('departamento_sitios_list')) {
            Schema::create('departamento_sitios_list', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->string('nombre', 250)->nullable();
                $table->longText('contenido')->nullable();
                $table->char('destacada', 2)->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('update_at')->useCurrent();
                $table->index(['departamento'], 'depart_sitios_list');
                $table->foreign('departamento', 'depart_sitios_list')->references('id')->on('departamento_descripcion')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('list_departamentos')) {
            Schema::create('list_departamentos', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->string('nombre', 100)->nullable();
                $table->string('capital', 100)->nullable();
            });
        }
        if (! Schema::hasTable('preguntas')) {
            Schema::create('preguntas', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('departamento')->nullable();
                $table->string('tipo_pregunta', 10)->nullable();
                $table->text('pregunta')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->string('origen_pregunta', 15)->nullable();
            });
        }
        if (! Schema::hasTable('opciones_multiple')) {
            Schema::create('opciones_multiple', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('pregunta')->nullable();
                $table->text('opcion')->nullable();
                $table->char('correcta', 1)->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['pregunta'], 'pregunta');
                $table->foreign('pregunta', 'pregunta')->references('id')->on('preguntas')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('opciones_verfal')) {
            Schema::create('opciones_verfal', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('pregunta')->nullable();
                $table->char('respuesta', 1)->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('regiones')) {
            Schema::create('regiones', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->string('nombre', 250)->nullable();
                $table->string('departamentos', 50)->nullable();
                $table->longText('descripcion')->nullable();
                $table->string('estado', 10)->nullable();
                $table->string('remember_token', 200)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });
        }
        if (! Schema::hasTable('regiones_clima')) {
            Schema::create('regiones_clima', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('region')->nullable();
                $table->longText('clima')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['region'], 'depart_clima');
                $table->foreign('region', 'reg_clima')->references('id')->on('regiones_clima')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('regiones_cultura')) {
            Schema::create('regiones_cultura', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('region')->nullable();
                $table->longText('cultura')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['region'], 'depart_cultura');
                $table->foreign('region', 'reg_cultura')->references('id')->on('regiones_cultura')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('regiones_demografia')) {
            Schema::create('regiones_demografia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('region')->nullable();
                $table->longText('demografia')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['region'], 'depart_demo');
                $table->foreign('region', 'reg_demografia')->references('id')->on('regiones_demografia')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('regiones_economia')) {
            Schema::create('regiones_economia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('region')->nullable();
                $table->longText('economia')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['region'], 'depart_economia');
                $table->foreign('region', 'reg_economia')->references('id')->on('regiones_economia')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('regiones_etnografia')) {
            Schema::create('regiones_etnografia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('region')->nullable();
                $table->longText('etnografia')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['region'], 'depart_etnografia');
                $table->foreign('region', 'reg_etnografia')->references('id')->on('regiones_etnografia')->onDelete('cascade');
            });
            DB::statement('ALTER TABLE `regiones_etnografia` MODIFY `etnografia` LONGBLOB NULL');
        }
        if (! Schema::hasTable('regiones_fauna_flora')) {
            Schema::create('regiones_fauna_flora', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('region')->nullable();
                $table->longText('fauna')->nullable();
                $table->longText('flora')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['region'], 'depart_fauna');
                $table->foreign('region', 'reg_flora_fauna')->references('id')->on('regiones_fauna_flora')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('regiones_festividades')) {
            Schema::create('regiones_festividades', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('region')->nullable();
                $table->longText('festividades')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['region'], 'depart_gastronomia');
                $table->foreign('region', 'reg_festividades')->references('id')->on('regiones_festividades')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('regiones_gastronomia')) {
            Schema::create('regiones_gastronomia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('region')->nullable();
                $table->longText('gastronomia')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['region'], 'depart_gastronomia');
                $table->foreign('region', 'reg_gastronomia')->references('id')->on('regiones_gastronomia')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('regiones_geografia')) {
            Schema::create('regiones_geografia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('region')->nullable();
                $table->longText('geografia')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['region'], 'depart_geografia');
                $table->foreign('region', 'reg_geografia')->references('id')->on('regiones_geografia')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('regiones_hidrografia')) {
            Schema::create('regiones_hidrografia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('region')->nullable();
                $table->longText('hidrografia')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['region'], 'depart_hidrografia');
                $table->foreign('region', 'reg_hidro')->references('id')->on('regiones_hidrografia')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('regiones_historia')) {
            Schema::create('regiones_historia', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('region')->nullable();
                $table->longText('historia')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['region'], 'depart_historia');
                $table->foreign('region', 'reg_historia')->references('id')->on('regiones_historia')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('regiones_referencias')) {
            Schema::create('regiones_referencias', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('region')->nullable();
                $table->longText('referencias')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['region'], 'depart_gastronomia');
                $table->foreign('region', 'reg_referencia')->references('id')->on('regiones_referencias')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('regiones_sitios')) {
            Schema::create('regiones_sitios', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_0900_ai_ci';
                $table->integer('id', true);
                $table->integer('region')->nullable();
                $table->longText('sitios')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->index(['region'], 'depart_gastronomia');
                $table->foreign('region', 'reg_sitios')->references('id')->on('regiones_sitios')->onDelete('cascade');
            });
        }
        if (! Schema::hasTable('resultado_juegos')) {
            Schema::create('resultado_juegos', function (Blueprint $table) {
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
                $table->integer('id', true);
                $table->text('nombre_juego')->nullable();
                $table->text('nivel')->nullable();
                $table->integer('tiempo')->nullable();
                $table->text('jugador')->nullable();
                $table->text('avatar')->nullable();
                $table->text('grado')->nullable();
                $table->integer('puntaje')->nullable();
                $table->text('categoria')->nullable();
                $table->integer('id_usuario')->nullable();
                $table->text('fecha')->nullable();
            });
        }
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->charset = 'utf8mb3';
                $table->collation = 'utf8mb3_spanish_ci';
                $table->integer('id', true);
                $table->string('nombre_usuario', 200);
                $table->string('login_usuario', 45);
                $table->string('pasword_usuario', 100);
                $table->string('tipo_usuario', 45);
                $table->string('email_usuario', 100)->nullable()->collation('utf8mb3_general_ci');
                $table->string('foto_usuario', 30)->nullable();
                $table->string('estado_usuario', 10);
                $table->string('remember_token', 100)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    /**
     * No elimina nada a propósito: borrar tablas con datos reales es destructivo y requiere autorización expresa.
     */
    public function down(): void
    {
        // Intencionalmente vacío: la migración base no elimina tablas.
    }
};
