<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Auditoría de SOLO LECTURA de la integridad de la base de Explora (Fase 16B; DAT-03, DAT-04 y DAT-05).
 *
 * Responde, antes de tocar nada, a la pregunta «¿se pueden añadir las claves foráneas que faltan?» y deja a la vista
 * el resto de la deuda de datos. Se ejecuta contra la base de la conexión por defecto (o `--conexion=`).
 *
 * Qué informa:
 *  1. Relaciones lógicas (FK existentes, candidatas y previstas): ¿hay FK?, filas huérfanas (con los ids concretos, hasta
 *     `--max-ids`), compatibilidad de tipo y de colación de las dos columnas (lo que impediría crear la FK).
 *  2. `preguntas.departamento`, relación POLIMÓRFICA: desglose por `origen_pregunta` y huérfanos de cada rama.
 *  3. Duplicados de `departamento` en las 12 tablas 1:1 (impiden un UNIQUE).
 *  4. Claves foráneas existentes (marcando las autorreferenciadas de `regiones_*`, DAT-02) y las que faltan.
 *  5. `resultado_juegos.fecha`: filas con formato `d-m-Y H:i:s` válido y no interpretables (con muestra truncada) y
 *     filas con `creado_en` NULL (las que la migración 000003 no pudo rellenar o que escribió el código viejo).
 *  6. Índices esperados presentes o ausentes.
 *  7. Charsets y colaciones mezclados (solo se REPORTAN; no se convierten).
 *
 * Gravedad y código de salida:
 *  - «bloqueante»: impide añadir una FK candidata (huérfanos, tipos o colaciones incompatibles, tabla o columna ausente).
 *    El comando termina con código 2.
 *  - «aviso»: deuda conocida que no bloquea nada (duplicados, polimórfica, FK autorreferenciadas, charsets, fechas…).
 *  - Código 0 si no hay bloqueantes; 1 si la auditoría no pudo ejecutarse.
 *
 * Seguridad: solo SELECT sobre las tablas y sobre INFORMATION_SCHEMA, dentro de una transacción `READ ONLY` que el
 * servidor hace cumplir (aunque un error de programación intentara escribir, MySQL lo rechazaría). No imprime contenido
 * de campos largos: solo ids, conteos y, de `fecha`, una muestra de 30 caracteres como máximo sin caracteres de control.
 */
class AuditarIntegridad extends Command
{
    /** @var string */
    protected $signature = 'explora:auditar-integridad
        {--json : Salida en JSON (sin texto adicional), para scripts}
        {--max-ids=20 : Máximo de ids/filas de ejemplo por anomalía}
        {--conexion= : Conexión de base de datos (por defecto la del proyecto)}';

    /** @var string */
    protected $description = 'Informe de solo lectura: huérfanos, duplicados, FK, fechas, índices y charsets (código 2 si hay bloqueantes para añadir FK)';

    /** Formato en que se guarda `resultado_juegos.fecha` (el mismo de la migración 000003). */
    private const FORMATO_FECHA = '%d-%m-%Y %H:%i:%s';

    /** Expresión regular (MySQL) del formato válido; permite espacios en los extremos como la migración. */
    private const REGEXP_FECHA = '^ *[0-9]{2}-[0-9]{2}-[0-9]{4} [0-9]{2}:[0-9]{2}:[0-9]{2} *$';

    /** Las 12 tablas con relación 1:1 con `departamento_descripcion` (las `*_list` son 1:N y no se auditan aquí). */
    private const TABLAS_1A1 = [
        'departamento_capital', 'departamento_clima', 'departamento_cultura', 'departamento_demografia',
        'departamento_economia', 'departamento_etnografia', 'departamento_fauna_flora', 'departamento_gastronomia',
        'departamento_geografia', 'departamento_hidrografia', 'departamento_historia', 'departamento_referencia',
    ];

    /**
     * Relaciones lógicas que NO salen de una FK existente. `candidata` = su FK está en el alcance de la Fase 16B, así que
     * sus problemas son BLOQUEANTES; el resto solo avisa.
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: bool}> [hijo, columna, padre, columna, candidata]
     */
    private const RELACIONES_PREVISTAS = [
        ['departamento_descripcion', 'departamento', 'list_departamentos', 'id', true],
        ['opciones_verfal', 'pregunta', 'preguntas', 'id', true],
    ];

    /**
     * Índices que deben existir tras las migraciones 000003 y 000004: [tabla, nombre, columnas (en orden)].
     *
     * @var array<int, array{0: string, 1: string, 2: array<int, string>}>
     */
    private const INDICES_ESPERADOS = [
        ['resultado_juegos', 'idx_resultado_ranking', ['nombre_juego', 'nivel', 'categoria', 'puntaje', 'tiempo']],
        ['resultado_juegos', 'idx_resultado_historial', ['id_usuario', 'id']],
        ['preguntas', 'idx_preguntas_departamento', ['departamento']],
        ['opciones_verfal', 'idx_opciones_verfal_pregunta', ['pregunta']],
        ['departamento_descripcion', 'idx_departamento_descripcion_departamento', ['departamento']],
    ];

    /** FK que debe existir tras la migración 000004. */
    private const FK_ESPERADA = 'fk_departamento_descripcion_departamento';

    private Connection $conn;

    private string $base;

    private int $maxIds = 20;

    /** @var array<int, array{gravedad: string, codigo: string, detalle: string}> */
    private array $problemas = [];

    /**
     * Ejecuta la auditoría completa y la imprime (texto o JSON).
     *
     * @return int 0 sin bloqueantes; 2 con bloqueantes para añadir FK; 1 si no pudo ejecutarse.
     */
    public function handle(): int
    {
        $this->problemas = [];
        $this->maxIds = max(1, min(200, (int) $this->option('max-ids')));

        $nombreConexion = (string) ($this->option('conexion') ?: config('database.default'));
        if (! is_array(config("database.connections.$nombreConexion"))) {
            $this->error("La conexión '$nombreConexion' no existe.");

            return self::FAILURE;
        }
        $this->conn = DB::connection($nombreConexion);
        $this->conn->disableQueryLog();
        $this->base = (string) $this->conn->getDatabaseName();

        try {
            // El servidor rechaza cualquier escritura dentro de esta transacción: la garantía de «solo lectura» no depende de nosotros.
            $this->conn->unprepared('START TRANSACTION READ ONLY');
            try {
                $informe = $this->auditar();
            } finally {
                $this->conn->unprepared('COMMIT');
            }
        } catch (Throwable $e) {
            $this->error('La auditoría no pudo ejecutarse: '.$e->getMessage());

            return self::FAILURE;
        }

        $bloqueantes = count(array_filter($this->problemas, fn ($p) => $p['gravedad'] === 'bloqueante'));
        $informe['resumen'] = [
            'bloqueantes' => $bloqueantes,
            'avisos' => count($this->problemas) - $bloqueantes,
            'problemas' => $this->problemas,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($informe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        } else {
            $this->imprimir($informe);
        }

        return $bloqueantes > 0 ? 2 : self::SUCCESS;
    }

    // ----------------------------------------------------------------------------------------------- recolección

    /**
     * Reúne todas las secciones del informe.
     *
     * @return array<string, mixed>
     */
    private function auditar(): array
    {
        $fks = $this->fksExistentes();

        return [
            'base' => $this->base,
            'relaciones' => $this->relaciones($fks),
            'preguntas_polimorfica' => $this->polimorficaPreguntas(),
            'duplicados_1a1' => $this->duplicados1a1(),
            'fks_existentes' => $fks,
            'resultado_juegos' => $this->resultadoJuegos(),
            'indices' => $this->indices(),
            'charsets' => $this->charsets(),
        ];
    }

    /**
     * Claves foráneas existentes en la base, con su regla de borrado/actualización.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fksExistentes(): array
    {
        $filas = $this->conn->select(
            'SELECT k.TABLE_NAME t, k.COLUMN_NAME c, k.CONSTRAINT_NAME n, k.REFERENCED_TABLE_NAME rt, k.REFERENCED_COLUMN_NAME rc, '
            .'r.UPDATE_RULE ur, r.DELETE_RULE dr '
            .'FROM information_schema.KEY_COLUMN_USAGE k '
            .'JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME '
            .'WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME'
        );

        $salida = [];
        foreach ($filas as $f) {
            $auto = $f->t === $f->rt;
            $salida[] = [
                'tabla' => $f->t, 'columna' => $f->c, 'nombre' => $f->n, 'referencia' => "{$f->rt}.{$f->rc}",
                'on_update' => $f->ur, 'on_delete' => $f->dr, 'autorreferenciada' => $auto,
            ];
        }
        $autos = count(array_filter($salida, fn ($f) => $f['autorreferenciada']));
        if ($autos > 0) {
            $this->problema('aviso', 'fk_autorreferenciadas', "$autos FK de regiones_* apuntan a su propia tabla en vez de a regiones.id (DAT-02; no se tocan).");
        }

        return $salida;
    }

    /**
     * Para cada relación lógica (FK existentes + previstas): FK sí/no, huérfanos, nulos y compatibilidad de columnas.
     *
     * @param  array<int, array<string, mixed>>  $fks
     * @return array<int, array<string, mixed>>
     */
    private function relaciones(array $fks): array
    {
        $relaciones = [];
        foreach ($fks as $fk) {
            [$padre, $colPadre] = explode('.', $fk['referencia'], 2);
            if ($fk['autorreferenciada']) {
                // La relación PREVISTA de regiones_* es contra `regiones.id` (la FK actual es un error de diseño).
                $relaciones[] = ['hijo' => $fk['tabla'], 'col_hijo' => $fk['columna'], 'padre' => 'regiones', 'col_padre' => 'id', 'candidata' => false, 'fk' => false, 'nota' => 'prevista (la FK actual es autorreferenciada)'];
            } elseif (! $this->esFkEsperada($fk)) {
                $relaciones[] = ['hijo' => $fk['tabla'], 'col_hijo' => $fk['columna'], 'padre' => $padre, 'col_padre' => $colPadre, 'candidata' => false, 'fk' => true, 'nota' => 'FK existente'];
            }
        }
        foreach (self::RELACIONES_PREVISTAS as [$hijo, $col, $padre, $colPadre, $candidata]) {
            $tiene = (bool) array_filter($fks, fn ($f) => $f['tabla'] === $hijo && $f['columna'] === $col && $f['referencia'] === "$padre.$colPadre");
            $relaciones[] = ['hijo' => $hijo, 'col_hijo' => $col, 'padre' => $padre, 'col_padre' => $colPadre, 'candidata' => $candidata, 'fk' => $tiene, 'nota' => $tiene ? 'FK existente' : 'FK candidata'];
        }

        foreach ($relaciones as &$r) {
            $r = array_merge($r, $this->examinarRelacion($r['hijo'], $r['col_hijo'], $r['padre'], $r['col_padre']));
            $this->valorarRelacion($r);
        }

        return $relaciones;
    }

    /** ¿Es la FK que añade la migración 000004? (entonces su relación sale de RELACIONES_PREVISTAS, no se duplica). */
    private function esFkEsperada(array $fk): bool
    {
        return $fk['nombre'] === self::FK_ESPERADA;
    }

    /**
     * Traduce el examen de una relación a problemas con su gravedad.
     *
     * @param  array<string, mixed>  $r
     */
    private function valorarRelacion(array $r): void
    {
        $nombre = "{$r['hijo']}.{$r['col_hijo']} → {$r['padre']}.{$r['col_padre']}";
        $gravedad = $r['candidata'] && ! $r['fk'] ? 'bloqueante' : 'aviso';

        if (! $r['verificable']) {
            // Tabla o columna ausente: no hay nada que verificar. Solo bloquea si era una FK candidata.
            $this->problema($gravedad, 'relacion_no_verificable', "$nombre: {$r['motivo']}.");

            return;
        }
        if ($r['huerfanos'] > 0) {
            $this->problema($gravedad, 'huerfanos', "$nombre: {$r['huerfanos']} fila(s) huérfana(s); ids: ".implode(', ', $r['ids_huerfanos']).($r['huerfanos'] > count($r['ids_huerfanos']) ? ', …' : '').'.');
        }
        if (! $r['compatible']) {
            $this->problema($gravedad, 'tipos_incompatibles', "$nombre: columnas incompatibles para una FK ({$r['tipo_hijo']} frente a {$r['tipo_padre']}).");
        }
    }

    /**
     * Examina una relación: existencia de tablas/columnas, tipos, nulos y huérfanos (con ids de ejemplo).
     *
     * @return array<string, mixed>
     */
    private function examinarRelacion(string $hijo, string $colHijo, string $padre, string $colPadre): array
    {
        $tipoHijo = $this->columna($hijo, $colHijo);
        $tipoPadre = $this->columna($padre, $colPadre);
        if ($tipoHijo === null || $tipoPadre === null) {
            $falta = $tipoHijo === null ? "$hijo.$colHijo" : "$padre.$colPadre";

            return ['verificable' => false, 'motivo' => "no existe $falta"];
        }

        $compatible = $tipoHijo->COLUMN_TYPE === $tipoPadre->COLUMN_TYPE
            && $tipoHijo->CHARACTER_SET_NAME === $tipoPadre->CHARACTER_SET_NAME
            && $tipoHijo->COLLATION_NAME === $tipoPadre->COLLATION_NAME;

        $h = $this->q($hijo);
        $ch = $this->q($colHijo);
        $p = $this->q($padre);
        $cp = $this->q($colPadre);
        $r = [
            'verificable' => true, 'compatible' => $compatible,
            'tipo_hijo' => $this->describirTipo($tipoHijo), 'tipo_padre' => $this->describirTipo($tipoPadre),
        ];
        try {
            $r['filas'] = (int) $this->conn->selectOne("SELECT COUNT(*) n FROM $h")->n;
            $r['nulos'] = (int) $this->conn->selectOne("SELECT COUNT(*) n FROM $h WHERE $ch IS NULL")->n;
            $r['huerfanos'] = (int) $this->conn->selectOne("SELECT COUNT(*) n FROM $h x LEFT JOIN $p y ON y.$cp = x.$ch WHERE x.$ch IS NOT NULL AND y.$cp IS NULL")->n;
            // Id de la fila huérfana: su PK `id` si existe; si no, el propio valor huérfano.
            $idCol = $this->columna($hijo, 'id') !== null ? 'x.`id`' : "x.$ch";
            $r['ids_huerfanos'] = array_map(
                fn ($o) => (int) $o->i,
                $this->conn->select("SELECT $idCol i FROM $h x LEFT JOIN $p y ON y.$cp = x.$ch WHERE x.$ch IS NOT NULL AND y.$cp IS NULL ORDER BY i LIMIT ".$this->maxIds)
            );
        } catch (Throwable $e) {
            // Típico: «Illegal mix of collations» entre columnas de texto: es justamente la incompatibilidad que se quiere ver.
            $r = array_merge($r, ['filas' => null, 'nulos' => null, 'huerfanos' => 0, 'ids_huerfanos' => [], 'compatible' => false, 'error' => substr($e->getMessage(), 0, 200)]);
        }

        return $r;
    }

    /**
     * Desglosa `preguntas` por `origen_pregunta` y comprueba cada rama contra su tabla destino.
     *
     * @return array<string, mixed>
     */
    private function polimorficaPreguntas(): array
    {
        if ($this->columna('preguntas', 'origen_pregunta') === null || $this->columna('preguntas', 'departamento') === null) {
            return ['verificable' => false];
        }
        $destinos = ['departamentos' => 'departamento_descripcion', 'regiones' => 'regiones'];
        $ramas = [];
        $filas = $this->conn->select('SELECT origen_pregunta o, COUNT(*) n, SUM(departamento IS NULL) nulos FROM preguntas GROUP BY origen_pregunta ORDER BY n DESC');
        foreach ($filas as $f) {
            $origen = $f->o === null ? '(NULL)' : (string) $f->o;
            $rama = ['origen_pregunta' => $origen, 'filas' => (int) $f->n, 'departamento_nulo' => (int) $f->nulos, 'destino' => $destinos[$f->o] ?? null];
            if (isset($destinos[$f->o]) && $this->columna($destinos[$f->o], 'id') !== null) {
                $d = $this->q($destinos[$f->o]);
                $cond = 'FROM preguntas x LEFT JOIN '.$d.' y ON y.id = x.departamento WHERE x.origen_pregunta = ? AND x.departamento IS NOT NULL AND y.id IS NULL';
                $rama['huerfanos'] = (int) $this->conn->selectOne("SELECT COUNT(*) n $cond", [$f->o])->n;
                $rama['ids_huerfanos'] = array_map(fn ($o) => (int) $o->i, $this->conn->select("SELECT x.id i $cond ORDER BY i LIMIT ".$this->maxIds, [$f->o]));
                if ($rama['huerfanos'] > 0) {
                    $this->problema('aviso', 'preguntas_huerfanas', "preguntas con origen '$origen': {$rama['huerfanos']} apuntan a un id inexistente en {$destinos[$f->o]}; ids de pregunta: ".implode(', ', $rama['ids_huerfanos']).'.');
                }
            } else {
                $rama['huerfanos'] = null;
                $rama['ids_huerfanos'] = [];
                $this->problema('aviso', 'preguntas_origen_desconocido', "preguntas con origen $origen ({$f->n} filas): sin tabla destino conocida.");
            }
            $ramas[] = $rama;
        }
        $this->problema('aviso', 'preguntas_polimorfica', 'preguntas.departamento es polimórfica (según origen_pregunta apunta a departamento_descripcion o a regiones): no admite una FK única.');

        return ['verificable' => true, 'ramas' => $ramas];
    }

    /**
     * Duplicados de `departamento` en las tablas 1:1 (impiden un UNIQUE(departamento)).
     *
     * @return array<int, array<string, mixed>>
     */
    private function duplicados1a1(): array
    {
        $salida = [];
        foreach (self::TABLAS_1A1 as $t) {
            if ($this->columna($t, 'departamento') === null) {
                continue;
            }
            $tq = $this->q($t);
            $dups = $this->conn->select("SELECT departamento d, COUNT(*) n FROM $tq WHERE departamento IS NOT NULL GROUP BY departamento HAVING COUNT(*) > 1 ORDER BY departamento");
            $sobrantes = array_sum(array_map(fn ($d) => $d->n - 1, $dups));
            $salida[] = [
                'tabla' => $t, 'departamentos_duplicados' => count($dups), 'filas_sobrantes' => (int) $sobrantes,
                'ejemplos' => array_map(fn ($d) => ['departamento' => (int) $d->d, 'veces' => (int) $d->n], array_slice($dups, 0, $this->maxIds)),
            ];
            if ($dups !== []) {
                $this->problema('aviso', 'duplicados_1a1', "$t: ".count($dups).' departamento(s) con más de una fila ('.$sobrantes.' sobrantes); impide UNIQUE(departamento).');
            }
        }

        return $salida;
    }

    /**
     * Estado de `resultado_juegos.fecha` y `creado_en`.
     *
     * @return array<string, mixed>
     */
    private function resultadoJuegos(): array
    {
        if ($this->columna('resultado_juegos', 'fecha') === null) {
            return ['verificable' => false];
        }
        // Mismo criterio que la migración 000003: formato exacto (espacios en extremos tolerados), fecha real y año ≥ 1000.
        $valida = "(fecha REGEXP '".self::REGEXP_FECHA."' AND STR_TO_DATE(TRIM(fecha), '".self::FORMATO_FECHA."') IS NOT NULL AND YEAR(STR_TO_DATE(TRIM(fecha), '".self::FORMATO_FECHA."')) >= 1000)";

        $total = (int) $this->conn->selectOne('SELECT COUNT(*) n FROM resultado_juegos')->n;
        $vacias = (int) $this->conn->selectOne("SELECT COUNT(*) n FROM resultado_juegos WHERE fecha IS NULL OR TRIM(fecha) = ''")->n;
        $parseables = (int) $this->conn->selectOne("SELECT COUNT(*) n FROM resultado_juegos WHERE $valida")->n;
        $noParseables = $total - $parseables;

        $muestra = array_map(function ($f) {
            // Solo 30 caracteres y sin caracteres de control: la columna es `text` y podría contener cualquier cosa.
            $v = mb_substr((string) $f->fecha, 0, 30);

            return ['id' => (int) $f->id, 'longitud' => (int) $f->l, 'valor' => preg_replace('/[^\P{C}]|\s/u', '?', $v) ?? ''];
        }, $this->conn->select("SELECT id, fecha, CHAR_LENGTH(fecha) l FROM resultado_juegos WHERE NOT COALESCE($valida, 0) ORDER BY id LIMIT ".$this->maxIds));

        $r = [
            'verificable' => true, 'filas' => $total, 'fecha_parseable' => $parseables, 'fecha_no_parseable' => $noParseables,
            'fecha_vacia' => $vacias, 'muestra_no_parseables' => $muestra, 'creado_en_existe' => false,
        ];
        if ($noParseables > 0) {
            $this->problema('aviso', 'fecha_no_parseable', "resultado_juegos.fecha: $noParseables de $total fila(s) no son «d-m-Y H:i:s» válidas ($vacias vacías); su creado_en queda NULL.");
        }

        if ($this->columna('resultado_juegos', 'creado_en') !== null) {
            $r['creado_en_existe'] = true;
            $r['creado_en_nulo'] = (int) $this->conn->selectOne('SELECT COUNT(*) n FROM resultado_juegos WHERE creado_en IS NULL')->n;
            $r['creado_en_nulo_con_fecha_valida'] = (int) $this->conn->selectOne("SELECT COUNT(*) n FROM resultado_juegos WHERE creado_en IS NULL AND $valida")->n;
            if ($r['creado_en_nulo_con_fecha_valida'] > 0) {
                $this->problema('aviso', 'creado_en_pendiente', "resultado_juegos.creado_en: {$r['creado_en_nulo_con_fecha_valida']} fila(s) con fecha válida y creado_en NULL (insertadas por código anterior a la doble escritura): se pueden rellenar con STR_TO_DATE.");
            }
        } else {
            $this->problema('aviso', 'creado_en_ausente', 'resultado_juegos.creado_en no existe: la migración 2026_11_21_000003 no está aplicada (el código nuevo la necesita).');
        }

        return $r;
    }

    /**
     * Índices esperados: ¿están presentes con las columnas esperadas, en orden?
     *
     * @return array<int, array<string, mixed>>
     */
    private function indices(): array
    {
        $salida = [];
        foreach (self::INDICES_ESPERADOS as [$tabla, $nombre, $columnas]) {
            $reales = array_map(fn ($o) => $o->c, $this->conn->select(
                'SELECT COLUMN_NAME c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX',
                [$tabla, $nombre]
            ));
            $presente = $reales !== [];
            $salida[] = ['tabla' => $tabla, 'indice' => $nombre, 'columnas' => $columnas, 'presente' => $presente, 'coincide' => $reales === $columnas];
            if (! $presente) {
                $this->problema('aviso', 'indice_ausente', "Índice $nombre ($tabla) ausente: migración 000003/000004 sin aplicar.");
            } elseif ($reales !== $columnas) {
                $this->problema('aviso', 'indice_distinto', "Índice $nombre ($tabla) existe con columnas distintas (".implode(',', $reales).').');
            }
        }
        $fk = (bool) $this->conn->selectOne(
            "SELECT 1 x FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [self::FK_ESPERADA]
        );
        $salida[] = ['tabla' => 'departamento_descripcion', 'indice' => self::FK_ESPERADA, 'columnas' => ['departamento'], 'presente' => $fk, 'coincide' => $fk, 'tipo' => 'FK'];
        if (! $fk) {
            $this->problema('aviso', 'fk_ausente', 'FK '.self::FK_ESPERADA.' ausente (migración 000004 sin aplicar o no creada por huérfanas).');
        }

        return $salida;
    }

    /**
     * Charsets y colaciones: tablas por colación y columnas de texto cuyo charset difiere del de su tabla.
     *
     * @return array<string, mixed>
     */
    private function charsets(): array
    {
        $porColacion = [];
        foreach ($this->conn->select('SELECT TABLE_COLLATION c, TABLE_NAME t FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\' ORDER BY TABLE_NAME') as $f) {
            $porColacion[$f->c][] = $f->t;
        }
        $resumen = [];
        foreach ($porColacion as $colacion => $tablas) {
            $resumen[] = ['colacion' => $colacion, 'tablas' => count($tablas), 'ejemplos' => array_slice($tablas, 0, 5)];
        }
        $mezcladas = array_map(fn ($o) => "{$o->t}.{$o->c}", $this->conn->select(
            'SELECT c.TABLE_NAME t, c.COLUMN_NAME c FROM information_schema.COLUMNS c JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME '
            .'WHERE c.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = \'BASE TABLE\' AND c.COLLATION_NAME IS NOT NULL AND c.COLLATION_NAME <> t.TABLE_COLLATION ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION LIMIT 200'
        ));
        if (count($resumen) > 1) {
            $this->problema('aviso', 'colaciones_mezcladas', 'Tablas con '.count($resumen).' colaciones distintas ('.implode(', ', array_map(fn ($r) => "{$r['colacion']}: {$r['tablas']}", $resumen)).'); no se convierten (DAT-05).');
        }

        return ['por_colacion' => $resumen, 'columnas_con_colacion_distinta_a_su_tabla' => $mezcladas];
    }

    // ------------------------------------------------------------------------------------------------ utilidades

    /**
     * Metadatos de una columna, o null si la tabla/columna no existe.
     */
    private function columna(string $tabla, string $columna): ?object
    {
        return $this->conn->selectOne(
            'SELECT COLUMN_TYPE, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tabla, $columna]
        );
    }

    /** Tipo legible: `int`, `varchar(10) utf8mb4_0900_ai_ci`… */
    private function describirTipo(object $c): string
    {
        return trim($c->COLUMN_TYPE.' '.($c->COLLATION_NAME ?? ''));
    }

    /**
     * Cita un identificador. Los nombres vienen de listas fijas del código o de INFORMATION_SCHEMA; aun así se valida.
     */
    private function q(string $identificador): string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $identificador)) {
            throw new \InvalidArgumentException('Identificador no permitido.');
        }

        return '`'.$identificador.'`';
    }

    private function problema(string $gravedad, string $codigo, string $detalle): void
    {
        $this->problemas[] = ['gravedad' => $gravedad, 'codigo' => $codigo, 'detalle' => $detalle];
    }

    // --------------------------------------------------------------------------------------------------- salida

    /**
     * Imprime el informe legible.
     *
     * @param  array<string, mixed>  $i
     */
    private function imprimir(array $i): void
    {
        $this->info("Auditoría de integridad (solo lectura) — base: {$i['base']}");

        $this->newLine();
        $this->line('<options=bold>1. Relaciones lógicas</>');
        $this->table(['Relación', 'FK', 'Filas', 'NULL', 'Huérfanas', 'Compatible', 'Ids huérfanos'], array_map(fn ($r) => [
            "{$r['hijo']}.{$r['col_hijo']} → {$r['padre']}.{$r['col_padre']}".($r['candidata'] ? ' (candidata)' : ''),
            $r['fk'] ? 'sí' : 'no',
            $r['verificable'] ? ($r['filas'] ?? '?') : '—',
            $r['verificable'] ? ($r['nulos'] ?? '?') : '—',
            $r['verificable'] ? $r['huerfanos'] : $r['motivo'],
            $r['verificable'] ? ($r['compatible'] ? 'sí' : 'NO') : '—',
            $r['verificable'] ? implode(',', $r['ids_huerfanos']) : '',
        ], $i['relaciones']));

        $this->line('<options=bold>2. preguntas.departamento (polimórfica)</>');
        if ($i['preguntas_polimorfica']['verificable']) {
            $this->table(['origen_pregunta', 'Filas', 'Destino', 'Huérfanas', 'Ids huérfanos'], array_map(fn ($r) => [
                $r['origen_pregunta'], $r['filas'], $r['destino'] ?? '(desconocido)', $r['huerfanos'] ?? '—', implode(',', $r['ids_huerfanos']),
            ], $i['preguntas_polimorfica']['ramas']));
        } else {
            $this->line('  no verificable (falta la tabla o la columna).');
        }

        $this->line('<options=bold>3. Duplicados en tablas 1:1</>');
        $this->table(['Tabla', 'Departamentos duplicados', 'Filas sobrantes', 'Ejemplos (departamento×veces)'], array_map(fn ($d) => [
            $d['tabla'], $d['departamentos_duplicados'], $d['filas_sobrantes'],
            implode(', ', array_map(fn ($e) => "{$e['departamento']}×{$e['veces']}", $d['ejemplos'])),
        ], $i['duplicados_1a1']));

        $this->line('<options=bold>4. Claves foráneas existentes</> ('.count($i['fks_existentes']).')');
        $autos = count(array_filter($i['fks_existentes'], fn ($f) => $f['autorreferenciada']));
        $this->line('  Autorreferenciadas (regiones_*, DAT-02): '.$autos.'. Resto: '.(count($i['fks_existentes']) - $autos).'.');

        $this->newLine();
        $this->line('<options=bold>5. resultado_juegos</>');
        $r = $i['resultado_juegos'];
        if ($r['verificable']) {
            $this->line("  Filas: {$r['filas']}; fecha válida: {$r['fecha_parseable']}; no interpretable: {$r['fecha_no_parseable']} (vacías: {$r['fecha_vacia']}).");
            foreach ($r['muestra_no_parseables'] as $m) {
                $this->line("    id {$m['id']} (longitud {$m['longitud']}): «{$m['valor']}»");
            }
            $this->line($r['creado_en_existe']
                ? "  creado_en NULL: {$r['creado_en_nulo']} (de ellas con fecha válida, rellenables: {$r['creado_en_nulo_con_fecha_valida']})."
                : '  creado_en: la columna no existe (migración 000003 sin aplicar).');
        } else {
            $this->line('  no verificable (falta la tabla).');
        }

        $this->newLine();
        $this->line('<options=bold>6. Índices y FK esperados</>');
        $this->table(['Tabla', 'Nombre', 'Columnas', 'Estado'], array_map(fn ($x) => [
            $x['tabla'], $x['indice'], implode(',', $x['columnas']), $x['presente'] ? ($x['coincide'] ? 'presente' : 'DISTINTO') : 'AUSENTE',
        ], $i['indices']));

        $this->line('<options=bold>7. Colaciones</>');
        $this->table(['Colación', 'Tablas', 'Ejemplos'], array_map(fn ($c) => [$c['colacion'], $c['tablas'], implode(', ', $c['ejemplos'])], $i['charsets']['por_colacion']));
        $n = count($i['charsets']['columnas_con_colacion_distinta_a_su_tabla']);
        if ($n > 0) {
            $this->line("  Columnas con colación distinta a la de su tabla: $n (p. ej. ".implode(', ', array_slice($i['charsets']['columnas_con_colacion_distinta_a_su_tabla'], 0, 5)).').');
        }

        $this->newLine();
        $this->line('<options=bold>Resumen</>');
        foreach ($i['resumen']['problemas'] as $p) {
            $etiqueta = $p['gravedad'] === 'bloqueante' ? '<fg=red>BLOQUEANTE</>' : '<fg=yellow>aviso</>';
            $this->line("  [$etiqueta] {$p['detalle']}");
        }
        $this->line("  {$i['resumen']['bloqueantes']} bloqueante(s), {$i['resumen']['avisos']} aviso(s).");
        if ($i['resumen']['bloqueantes'] > 0) {
            $this->warn('Hay problemas que bloquean añadir una FK: el código de salida es 2.');
        }
    }
}
