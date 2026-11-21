<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 16B / DAT-03 — Fase «EXPANDIR» de `resultado_juegos` (patrón expandir/contraer). Migración ADITIVA.
 *
 * Qué añade (y nada más):
 *  1. Columna `creado_en DATETIME NULL`, rellenada a partir de `fecha` (texto «d-m-Y H:i:s») donde el formato es válido.
 *     Lo que no se puede interpretar (vacío, basura, `31-02-2026`…) se deja en NULL y se CUENTA en el aviso de salida:
 *     nunca se inventa una fecha ni se falla por ello.
 *  2. Índice `idx_resultado_ranking` para el ranking:
 *       WHERE nombre_juego=? AND nivel=? AND categoria=? ORDER BY puntaje DESC, tiempo ASC, id ASC LIMIT 5
 *     Las columnas son `text`, así que MySQL solo admite índice con PREFIJO (longitudes que cubren con holgura los
 *     valores válidos: juego ≤ 19 caracteres, nivel ≤ 12, categoría ≤ 100 por la validación de la API). Los prefijos
 *     solo sirven para localizar el grupo; la comparación completa la sigue haciendo la fila. `puntaje DESC` usa el
 *     índice DESCENDENTE de MySQL 8 y `id` va implícito al final de todo índice secundario de InnoDB, de modo que el
 *     ranking se lee ya ordenado, sin «filesort» (medido con EXPLAIN en la verificación a escala).
 *  3. Índice `idx_resultado_historial (id_usuario, id)` para
 *       WHERE id_usuario=? ORDER BY id DESC LIMIT N
 *     (antes recorría la clave primaria entera hacia atrás filtrando).
 *
 * Qué NO hace: no borra ni cambia el tipo de ninguna columna ni toca los datos de `fecha`. La API sigue leyendo y
 * devolviendo `fecha`; la fase «CONTRAER» (leer `creado_en` y retirar `fecha`) queda pendiente y requiere autorización.
 *
 * ORDEN DE DESPLIEGUE OBLIGATORIO: respaldo → ESTA migración → código nuevo. El código nuevo escribe `creado_en`, que
 * no existe hasta que se aplica la migración (con el código viejo y la migración aplicada no pasa nada: la columna es
 * NULL-able). Las filas que el código viejo inserte entre la migración y el despliegue quedan con `creado_en` NULL; el
 * comando `explora:auditar-integridad` las cuenta.
 *
 * Idempotente: columna e índices se crean solo si no existen (INFORMATION_SCHEMA) y el relleno solo toca filas con
 * `creado_en` NULL. `down()` elimina únicamente lo añadido aquí.
 */
return new class extends Migration
{
    private const TABLA = 'resultado_juegos';

    private const COLUMNA = 'creado_en';

    private const IDX_RANKING = 'idx_resultado_ranking';

    private const IDX_HISTORIAL = 'idx_resultado_historial';

    /** Formato en que el código guarda `fecha`; es también el de STR_TO_DATE. */
    private const FORMATO_MYSQL = '%d-%m-%Y %H:%i:%s';

    /** Tamaño de cada lote del relleno (filas leídas / ids actualizados por sentencia). */
    private const LOTE = 2000;

    /** Resumen del relleno de la última ejecución de up() (lo leen las pruebas y el aviso de salida). */
    public array $resumen = ['filas' => 0, 'rellenadas' => 0, 'no_interpretables' => 0];

    /**
     * Añade la columna, rellena `creado_en` y crea los índices (todo solo si falta).
     */
    public function up(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }

        if (! Schema::hasColumn(self::TABLA, self::COLUMNA)) {
            // DATETIME (no TIMESTAMP): sin límite del año 2038 ni conversión de zona; guarda la hora de la app (UTC), como `fecha`.
            DB::statement('ALTER TABLE `'.self::TABLA.'` ADD COLUMN `'.self::COLUMNA.'` DATETIME NULL');
        }

        $this->rellenar();

        if (! $this->existeIndice(self::IDX_RANKING)) {
            // Prefijos: ver el comentario de la clase. `puntaje DESC, tiempo ASC` reproduce el ORDER BY del ranking.
            DB::statement('CREATE INDEX `'.self::IDX_RANKING.'` ON `'.self::TABLA.'` '
                .'(`nombre_juego`(32), `nivel`(16), `categoria`(100), `puntaje` DESC, `tiempo` ASC)');
        }
        if (! $this->existeIndice(self::IDX_HISTORIAL)) {
            DB::statement('CREATE INDEX `'.self::IDX_HISTORIAL.'` ON `'.self::TABLA.'` (`id_usuario`, `id`)');
        }

        $r = $this->resumen;
        $this->avisar("resultado_juegos.creado_en: {$r['rellenadas']} filas rellenadas, {$r['no_interpretables']} con `fecha` no interpretable (quedan en NULL; ver explora:auditar-integridad).");
    }

    /**
     * Elimina SOLO lo añadido por esta migración (índices y columna `creado_en`). Perder `creado_en` no pierde información:
     * `fecha` conserva el mismo dato.
     */
    public function down(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }
        foreach ([self::IDX_RANKING, self::IDX_HISTORIAL] as $indice) {
            if ($this->existeIndice($indice)) {
                DB::statement('DROP INDEX `'.$indice.'` ON `'.self::TABLA.'`');
            }
        }
        if (Schema::hasColumn(self::TABLA, self::COLUMNA)) {
            DB::statement('ALTER TABLE `'.self::TABLA.'` DROP COLUMN `'.self::COLUMNA.'`');
        }
    }

    /**
     * Rellena `creado_en` desde `fecha` en lotes por id (memoria acotada aunque la tabla sea grande).
     *
     * Por qué la validación se hace en PHP y no con un `UPDATE … STR_TO_DATE(fecha)` directo: en modo SQL estricto MySQL
     * ABORTA todo el UPDATE con el error 1411 ante UNA sola fecha ilegal (p. ej. «31-02-2026»). Aquí PHP decide qué filas
     * son válidas (formato exacto, calendario real, hora real) y SQL convierte solo esas ids, así que nunca puede fallar
     * por datos sucios, y la conversión final la hace MySQL con el mismo formato con que se escribió.
     *
     * Tolerancias: se ignoran espacios al inicio y al final. Lo demás (otro orden, mes en letras, año de 2 dígitos…) se
     * considera no interpretable: adivinar un formato ambiguo (¿d-m o m-d?) corrompería datos.
     */
    private function rellenar(): void
    {
        $this->resumen = ['filas' => 0, 'rellenadas' => 0, 'no_interpretables' => 0];
        $ultimo = 0;

        while (true) {
            $filas = DB::table(self::TABLA)
                ->whereNull(self::COLUMNA)
                ->where('id', '>', $ultimo)
                ->orderBy('id')
                ->limit(self::LOTE)
                ->get(['id', 'fecha']);
            if ($filas->isEmpty()) {
                break;
            }

            $validas = [];
            foreach ($filas as $fila) {
                $this->resumen['filas']++;
                if ($this->esFechaValida($fila->fecha)) {
                    $validas[] = (int) $fila->id;
                } else {
                    $this->resumen['no_interpretables']++;
                }
            }
            if ($validas !== []) {
                // Ids enteros ya validados: se enlazan como parámetros por consistencia, no por riesgo de inyección.
                $marcas = implode(',', array_fill(0, count($validas), '?'));
                $this->resumen['rellenadas'] += DB::update(
                    'UPDATE `'.self::TABLA.'` SET `'.self::COLUMNA.'` = STR_TO_DATE(TRIM(`fecha`), \''.self::FORMATO_MYSQL.'\') '
                    ."WHERE `id` IN ($marcas) AND `".self::COLUMNA.'` IS NULL',
                    $validas
                );
            }
            $ultimo = (int) $filas->last()->id;
        }
    }

    /**
     * ¿Es `$fecha` un «d-m-Y H:i:s» exacto, con día de calendario real y hora real?
     *
     * @param  string|null  $fecha  Valor de la columna `fecha`.
     */
    private function esFechaValida(?string $fecha): bool
    {
        if ($fecha === null || ! preg_match('/^ *(\d{2})-(\d{2})-(\d{4}) (\d{2}):(\d{2}):(\d{2}) *$/', $fecha, $m)) {
            return false;
        }
        [, $d, $mes, $a, $h, $i, $s] = array_map('intval', $m);

        // Año ≥ 1000: es el mínimo que admite DATETIME. checkdate descarta 31-02, 29-02 de años no bisiestos, mes 13…
        return $a >= 1000 && checkdate($mes, $d, $a) && $h < 24 && $i < 60 && $s < 60;
    }

    /**
     * ¿Existe en la tabla un índice con ese nombre? (INFORMATION_SCHEMA: Laravel no expone índices con prefijo.)
     */
    private function existeIndice(string $nombre): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', self::TABLA)
            ->where('index_name', $nombre)
            ->exists();
    }

    /**
     * Deja un aviso en el registro y, en consola (no en pruebas), también por la salida de error.
     */
    private function avisar(string $mensaje): void
    {
        Log::notice('[migración 000003] '.$mensaje);
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            fwrite(STDERR, "  AVISO: $mensaje\n");
        }
    }
};
