<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 16B / DAT-04 — Integridad referencial. Migración ADITIVA (no borra ni cambia datos ni tipos de columnas).
 *
 * Qué añade:
 *  1. Índice `idx_preguntas_departamento` en `preguntas(departamento)` (el quiz filtra por esa columna y no tenía índice).
 *  2. Índice `idx_opciones_verfal_pregunta` en `opciones_verfal(pregunta)` (las opciones se piden por pregunta).
 *  3. Clave foránea `fk_departamento_descripcion_departamento`: `departamento_descripcion.departamento` →
 *     `list_departamentos.id` (ON UPDATE CASCADE, ON DELETE RESTRICT: un departamento con ficha no se puede borrar).
 *     SOLO se crea si no hay filas huérfanas (se consulta antes). Si las hay, o si MySQL la rechaza por cualquier
 *     otra causa, NO falla la migración: deja un AVISO claro (salida de consola y registro) y el comando
 *     `explora:auditar-integridad` reporta las filas concretas. Para poder declarar la FK se crea antes el índice
 *     `idx_departamento_descripcion_departamento` (si no había ya uno que empiece por esa columna).
 *
 * Qué NO hace, a propósito (decisiones del alcance, solo se REPORTAN en la auditoría):
 *  - `opciones_verfal.pregunta` → `preguntas.id`: hay filas huérfanas; qué hacer con ellas lo decide el dueño del contenido.
 *  - `preguntas.departamento` es POLIMÓRFICA (apunta a `departamento_descripcion` o a `regiones` según
 *    `origen_pregunta`): no admite una FK única.
 *  - FK autorreferenciadas de `regiones_*` (DAT-02), UNIQUE(departamento) en las tablas 1:1 (hay duplicados) y la
 *    conversión de charsets/colaciones (utf8mb3/utf8mb4).
 *
 * Idempotente: cada elemento se crea solo si no existe (INFORMATION_SCHEMA). `down()` elimina únicamente lo añadido aquí
 * (la FK y los tres índices por su nombre), sin tocar datos.
 */
return new class extends Migration
{
    private const FK = 'fk_departamento_descripcion_departamento';

    private const IDX_FK = 'idx_departamento_descripcion_departamento';

    /** @var array<int, array{0: string, 1: string, 2: string}> [índice, tabla, columna] */
    private const INDICES = [
        ['idx_preguntas_departamento', 'preguntas', 'departamento'],
        ['idx_opciones_verfal_pregunta', 'opciones_verfal', 'pregunta'],
    ];

    /** Avisos de la última ejecución de up() (los leen las pruebas; también van a consola y al registro). */
    public array $avisos = [];

    /**
     * Crea los índices y, si los datos lo permiten, la clave foránea.
     */
    public function up(): void
    {
        $this->avisos = [];

        foreach (self::INDICES as [$nombre, $tabla, $columna]) {
            if (Schema::hasTable($tabla) && ! $this->existeIndice($tabla, $nombre)) {
                DB::statement("CREATE INDEX `$nombre` ON `$tabla` (`$columna`)");
            }
        }

        $this->crearFkDeDescripcion();
    }

    /**
     * Elimina la FK y los índices añadidos por esta migración (por nombre; no toca los que ya existían antes).
     */
    public function down(): void
    {
        if (Schema::hasTable('departamento_descripcion')) {
            if ($this->existeFk()) {
                DB::statement('ALTER TABLE `departamento_descripcion` DROP FOREIGN KEY `'.self::FK.'`');
            }
            // Después de la FK: MySQL no deja soltar el índice que una FK necesita.
            if ($this->existeIndice('departamento_descripcion', self::IDX_FK)) {
                DB::statement('DROP INDEX `'.self::IDX_FK.'` ON `departamento_descripcion`');
            }
        }
        foreach (self::INDICES as [$nombre, $tabla]) {
            if (Schema::hasTable($tabla) && $this->existeIndice($tabla, $nombre)) {
                DB::statement("DROP INDEX `$nombre` ON `$tabla`");
            }
        }
    }

    /**
     * Crea la FK de `departamento_descripcion.departamento` solo si no hay huérfanas; si no, avisa y sigue.
     */
    private function crearFkDeDescripcion(): void
    {
        if (! Schema::hasTable('departamento_descripcion') || ! Schema::hasTable('list_departamentos')) {
            $this->avisar('FK departamento_descripcion → list_departamentos NO creada: falta una de las dos tablas.');

            return;
        }
        if ($this->existeFk()) {
            return;
        }

        // Huérfanas: departamento informado que no existe en list_departamentos (NULL no cuenta: una FK admite NULL).
        $huerfanas = DB::table('departamento_descripcion as d')
            ->leftJoin('list_departamentos as l', 'l.id', '=', 'd.departamento')
            ->whereNotNull('d.departamento')->whereNull('l.id')
            ->orderBy('d.id')->limit(20)->pluck('d.id')->all();
        if ($huerfanas !== []) {
            $this->avisar('FK departamento_descripcion.departamento → list_departamentos.id NO creada: hay filas huérfanas (ids de departamento_descripcion, hasta 20): '
                .implode(', ', $huerfanas).'. Corríjalas y vuelva a ejecutar la migración (es idempotente) o consulte explora:auditar-integridad.');

            return;
        }

        try {
            if (! $this->hayIndiceQueEmpiecePor('departamento_descripcion', 'departamento')) {
                DB::statement('CREATE INDEX `'.self::IDX_FK.'` ON `departamento_descripcion` (`departamento`)');
            }
            DB::statement('ALTER TABLE `departamento_descripcion` ADD CONSTRAINT `'.self::FK.'` '
                .'FOREIGN KEY (`departamento`) REFERENCES `list_departamentos` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT');
        } catch (QueryException $e) {
            // Tipos incompatibles, motor sin FK, etc.: no es motivo para tumbar el despliegue; se avisa con el motivo.
            $this->avisar('FK departamento_descripcion → list_departamentos NO creada: MySQL la rechazó ('.($e->errorInfo[2] ?? $e->getMessage()).').');
        }
    }

    /**
     * ¿Existe esa FK en la base actual?
     */
    private function existeFk(): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'departamento_descripcion')
            ->where('constraint_name', self::FK)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }

    /**
     * ¿Existe un índice con ese nombre en la tabla?
     */
    private function existeIndice(string $tabla, string $nombre): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $tabla)
            ->where('index_name', $nombre)
            ->exists();
    }

    /**
     * ¿Hay algún índice cuya PRIMERA columna sea `$columna`? (entonces la FK lo reutiliza y no creamos otro).
     */
    private function hayIndiceQueEmpiecePor(string $tabla, string $columna): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $tabla)
            ->where('seq_in_index', 1)
            ->where('column_name', $columna)
            ->exists();
    }

    /**
     * Registra un aviso: lista para las pruebas, registro de Laravel y salida de error de la consola (no en pruebas).
     */
    private function avisar(string $mensaje): void
    {
        $this->avisos[] = $mensaje;
        Log::warning('[migración 000004] '.$mensaje);
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            fwrite(STDERR, "  AVISO: $mensaje\n");
        }
    }
};
