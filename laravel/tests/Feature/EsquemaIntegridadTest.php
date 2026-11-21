<?php

namespace Tests\Feature;

use App\Models\Pedigital\Usuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fase 16B: migraciones 000003 (`resultado_juegos`: `creado_en` + índices) y 000004 (índices y FK de integridad), código
 * de doble escritura y comando `explora:auditar-integridad`.
 *
 * SEGURIDAD DE LAS PRUEBAS: todo ocurre en la base de sandbox `explora_migtest` (conexión `migtest`, que aquí se
 * convierte en la conexión POR DEFECTO de la prueba, de modo que migraciones, modelos y el comando auditan esa base). Antes
 * de cualquier escritura se comprueba con `SELECT DATABASE()` que es el sandbox; si no, la prueba falla sin tocar nada.
 * NO se usa `explora_test`. Los usuarios con sesión salen de `pedigital_test` (solo lectura).
 *
 * Cada prueba parte del estado «antes de la Fase 16B»: se crea el esquema base si falta (baseline idempotente), se
 * deshacen las dos migraciones con sus `down()` y se vacían las tablas que usan estas pruebas.
 * Si la base `explora_migtest` no existe, las pruebas se omiten (`CREATE DATABASE explora_migtest`).
 */
class EsquemaIntegridadTest extends TestCase
{
    private const M3 = '2026_11_21_000003_ampliar_resultado_juegos_creado_en_e_indices.php';

    private const M4 = '2026_11_21_000004_agregar_indices_y_fk_de_integridad.php';

    /** Tablas que estas pruebas llenan (se vacían al empezar y al terminar). Hijas antes que padres. */
    private const TABLAS = [
        'departamento_capital', 'departamento_descripcion', 'list_departamentos', 'opciones_verfal',
        'preguntas', 'regiones', 'resultado_juegos',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.migtest' => array_merge(config('database.connections.mysql'), ['database' => 'explora_migtest'])]);
        try {
            DB::connection('migtest')->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('No existe la base de sandbox explora_migtest (CREATE DATABASE explora_migtest).');
        }

        // Todo (Schema, DB, modelos, comando) pasa a usar el sandbox; y se verifica ANTES de escribir.
        DB::setDefaultConnection('migtest');
        config(['database.default' => 'migtest']);
        $this->assertSame('explora_migtest', DB::selectOne('SELECT DATABASE() d')->d, 'las pruebas solo pueden escribir en el sandbox');

        (require base_path('database/migrations/2026_11_18_000001_baseline_esquema_explora.php'))->up();
        (require base_path('database/migrations/2026_11_18_000002_crear_perfiles_explora.php'))->up();
        $this->restaurarEstadoPrevio();
    }

    protected function tearDown(): void
    {
        if (DB::getDefaultConnection() === 'migtest') {
            $this->restaurarEstadoPrevio();
        }
        parent::tearDown();
    }

    /** Deja el sandbox como «antes de la Fase 16B»: sin lo que añaden las migraciones y sin datos de estas pruebas. */
    private function restaurarEstadoPrevio(): void
    {
        $this->assertSame('explora_migtest', DB::selectOne('SELECT DATABASE() d')->d);
        $this->migracion(self::M4)->down();
        $this->migracion(self::M3)->down();
        // Una prueba de tipos incompatibles cambia esta columna: se devuelve a su tipo original.
        DB::statement('ALTER TABLE departamento_descripcion MODIFY departamento INT NULL');
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLAS as $t) {
            DB::statement("DELETE FROM `$t`");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    // ------------------------------------------------------------------------------------------------ utilidades

    /** Instancia de una de las migraciones de la fase (el archivo devuelve una clase anónima). */
    private function migracion(string $archivo): Migration
    {
        return require base_path("database/migrations/$archivo");
    }

    /** Inserta un resultado con valores por defecto y devuelve su id. @param array<string, mixed> $f */
    private function sembrar(array $f = []): int
    {
        return (int) DB::table('resultado_juegos')->insertGetId(array_merge([
            'nombre_juego' => 'laberinto', 'nivel' => 'Normal', 'tiempo' => 10, 'jugador' => 'J', 'avatar' => '1.png',
            'grado' => '5', 'puntaje' => 5, 'categoria' => 'Capitales', 'id_usuario' => 0, 'fecha' => '01-01-2026 10:00:00',
        ], $f));
    }

    /** `SHOW CREATE TABLE` sin el contador AUTO_INCREMENT (cambia con cada inserción). */
    private function ddl(string $tabla): string
    {
        $r = (array) DB::selectOne("SHOW CREATE TABLE `$tabla`");

        return (string) preg_replace('/ AUTO_INCREMENT=\d+/', '', $r['Create Table']);
    }

    /** Huella de todos los datos de una tabla, para comprobar que no cambian. */
    private function huella(string $tabla): string
    {
        return md5(json_encode(DB::table($tabla)->orderBy('id')->get()->all()));
    }

    private function hayIndice(string $tabla, string $nombre): bool
    {
        return DB::table('information_schema.statistics')->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $tabla)->where('index_name', $nombre)->exists();
    }

    private function hayFk(): bool
    {
        return DB::table('information_schema.table_constraints')->where('constraint_schema', DB::raw('DATABASE()'))
            ->where('constraint_name', 'fk_departamento_descripcion_departamento')->exists();
    }

    /** Ejecuta el comando de auditoría en JSON. @return array{0: int, 1: array<string, mixed>} [código de salida, informe] */
    private function auditar(array $opciones = []): array
    {
        $codigo = Artisan::call('explora:auditar-integridad', array_merge(['--json' => true], $opciones));

        return [$codigo, json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR)];
    }

    /** Relación del informe por su par hijo.columna. @return array<string, mixed> */
    private function relacion(array $informe, string $hijo, string $columna): array
    {
        foreach ($informe['relaciones'] as $r) {
            if ($r['hijo'] === $hijo && $r['col_hijo'] === $columna) {
                return $r;
            }
        }
        $this->fail("No aparece la relación $hijo.$columna en el informe.");
    }

    // -------------------------------------------------------------------------------- migración 000003: relleno

    /**
     * Relleno de `creado_en`: los formatos válidos se convierten (incluidos espacios en los extremos y el 29-02 de un año
     * bisiesto); vacío, NULL, basura, fechas imposibles (31-02, 29-02 de año no bisiesto, mes 13, 24:00:00, 00-00-0000),
     * otro formato y un intento de inyección quedan en NULL y se cuentan. `fecha` no cambia.
     */
    public function test_el_relleno_convierte_lo_valido_y_deja_null_lo_no_interpretable(): void
    {
        $validas = [
            '05-03-2026 14:07:09' => '2026-03-05 14:07:09',
            '31-12-2025 23:59:59' => '2025-12-31 23:59:59',
            '  01-02-2026 00:00:00  ' => '2026-02-01 00:00:00',
            '29-02-2024 10:00:00' => '2024-02-29 10:00:00',
        ];
        $malas = ['', '   ', 'basura', '31-02-2026 10:00:00', '29-02-2025 10:00:00', '05-13-2026 10:00:00', '05-03-2026 24:00:00',
            '00-00-0000 00:00:00', '2026-03-05 14:07:09', '5-3-2026 1:2:3', "05-03-2026 14:07:09'; DROP TABLE resultado_juegos;--", '05-03-0500 10:00:00'];

        $esperado = [];
        foreach ($validas as $fecha => $dt) {
            $esperado[$this->sembrar(['fecha' => $fecha])] = $dt;
        }
        foreach ($malas as $fecha) {
            $esperado[$this->sembrar(['fecha' => $fecha])] = null;
        }
        $esperado[$this->sembrar(['fecha' => null])] = null;
        $antesFecha = DB::table('resultado_juegos')->orderBy('id')->pluck('fecha', 'id')->all();

        $m = $this->migracion(self::M3);
        $m->up();

        $this->assertSame(count($validas), $m->resumen['rellenadas']);
        $this->assertSame(count($malas) + 1, $m->resumen['no_interpretables']);
        foreach ($esperado as $id => $dt) {
            $this->assertSame($dt, DB::table('resultado_juegos')->where('id', $id)->value('creado_en'), "fila $id");
        }
        $this->assertSame($antesFecha, DB::table('resultado_juegos')->orderBy('id')->pluck('fecha', 'id')->all(), '`fecha` no se modifica');
    }

    /** Con la tabla vacía la migración no falla y no rellena nada. */
    public function test_con_la_tabla_vacia_no_hay_nada_que_rellenar(): void
    {
        $m = $this->migracion(self::M3);
        $m->up();

        $this->assertSame(0, $m->resumen['filas']);
        $this->assertTrue(Schema::hasColumn('resultado_juegos', 'creado_en'));
    }

    /** `creado_en` es coherente con `fecha` en TODAS las filas rellenadas (tanda mayor que un lote para cubrir la paginación). */
    public function test_creado_en_es_coherente_con_fecha_en_todas_las_filas(): void
    {
        $filas = [];
        for ($i = 0; $i < 4500; $i++) {
            $t = mktime(rand(0, 23), rand(0, 59), rand(0, 59), rand(1, 12), rand(1, 28), rand(2020, 2026));
            // Cada 10.ª fila es basura: la paginación debe seguir más allá de las filas que no se rellenan.
            $filas[] = $this->fila(['fecha' => $i % 10 === 0 ? "x$i" : date('d-m-Y H:i:s', $t)]);
        }
        foreach (array_chunk($filas, 500) as $lote) {
            DB::table('resultado_juegos')->insert($lote);
        }

        $m = $this->migracion(self::M3);
        $m->up();

        $this->assertSame(450, $m->resumen['no_interpretables']);
        $this->assertSame(4050, $m->resumen['rellenadas']);
        $incoherentes = DB::selectOne("SELECT COUNT(*) n FROM resultado_juegos WHERE creado_en IS NOT NULL AND DATE_FORMAT(creado_en, '%d-%m-%Y %H:%i:%s') <> TRIM(fecha)")->n;
        $this->assertSame(0, (int) $incoherentes);
        $this->assertSame(450, (int) DB::selectOne('SELECT COUNT(*) n FROM resultado_juegos WHERE creado_en IS NULL')->n);
    }

    /** Fila lista para insertar en lote. @param array<string, mixed> $f @return array<string, mixed> */
    private function fila(array $f = []): array
    {
        return array_merge([
            'nombre_juego' => 'laberinto', 'nivel' => 'Normal', 'tiempo' => 10, 'jugador' => 'J', 'avatar' => '1.png',
            'grado' => '5', 'puntaje' => 5, 'categoria' => 'Capitales', 'id_usuario' => 0, 'fecha' => '01-01-2026 10:00:00',
        ], $f);
    }

    /** Idempotencia: una segunda ejecución no cambia el esquema ni los datos (ni duplica índices). */
    public function test_la_migracion_3_es_idempotente(): void
    {
        $this->sembrar(['fecha' => '05-03-2026 14:07:09']);
        $this->sembrar(['fecha' => 'basura']);
        $m = $this->migracion(self::M3);
        $m->up();
        $ddl = $this->ddl('resultado_juegos');
        $datos = $this->huella('resultado_juegos');

        $m->up();

        $this->assertSame($ddl, $this->ddl('resultado_juegos'));
        $this->assertSame($datos, $this->huella('resultado_juegos'));
        $this->assertSame(0, $m->resumen['rellenadas'], 'la 2.ª ejecución no vuelve a rellenar nada');
    }

    /** `down()` elimina SOLO lo añadido: el DDL vuelve a ser idéntico al original y los datos no cambian. */
    public function test_down_de_la_migracion_3_deja_la_tabla_como_estaba(): void
    {
        $this->sembrar(['fecha' => '05-03-2026 14:07:09']);
        $ddlOriginal = $this->ddl('resultado_juegos');
        $datos = $this->huella('resultado_juegos');
        $m = $this->migracion(self::M3);
        $m->up();
        $this->assertNotSame($ddlOriginal, $this->ddl('resultado_juegos'));

        $m->down();

        $this->assertSame($ddlOriginal, $this->ddl('resultado_juegos'));
        $this->assertSame($datos, $this->huella('resultado_juegos'));
        $m->down(); // y también es idempotente
        $this->assertSame($ddlOriginal, $this->ddl('resultado_juegos'));
    }

    /**
     * Índices del ranking y del historial: existen con las columnas esperadas (prefijo y orden descendente de `puntaje`) y
     * el optimizador los usa: el ranking se lee ya ordenado, SIN «filesort».
     */
    public function test_los_indices_existen_y_el_ranking_no_usa_filesort(): void
    {
        for ($n = 0; $n < 12; $n++) {
            $lote = [];
            for ($i = 0; $i < 1000; $i++) {
                $lote[] = $this->fila([
                    'nombre_juego' => ['laberinto', 'Ahorcado', 'Rompecabezas', 'Sopa de letras'][$i % 4],
                    'categoria' => 'Cat '.($i % 5), 'puntaje' => rand(0, 100) * 10, 'tiempo' => rand(5, 900), 'id_usuario' => $i % 3 === 0 ? rand(1, 200) : 0,
                ]);
            }
            DB::table('resultado_juegos')->insert($lote);
        }
        $this->migracion(self::M3)->up();
        DB::statement('ANALYZE TABLE resultado_juegos');

        $cols = DB::table('information_schema.statistics')->where('table_schema', DB::raw('DATABASE()'))->where('table_name', 'resultado_juegos')
            ->where('index_name', 'idx_resultado_ranking')->orderBy('seq_in_index')->selectRaw('column_name AS columna, sub_part AS prefijo, collation AS orden')->get();
        $this->assertSame(['nombre_juego', 'nivel', 'categoria', 'puntaje', 'tiempo'], $cols->pluck('columna')->all());
        $this->assertSame(['D'], [$cols[3]->orden], 'puntaje va en orden descendente');
        $this->assertSame([32, 16, 100], [(int) $cols[0]->prefijo, (int) $cols[1]->prefijo, (int) $cols[2]->prefijo], 'prefijos de las columnas text');
        $this->assertTrue($this->hayIndice('resultado_juegos', 'idx_resultado_historial'));

        $ranking = DB::selectOne("EXPLAIN SELECT jugador FROM resultado_juegos WHERE nombre_juego = 'laberinto' AND nivel = 'Normal' AND categoria = 'Cat 2' ORDER BY puntaje DESC, tiempo ASC, id ASC LIMIT 5");
        $this->assertSame('idx_resultado_ranking', $ranking->key);
        $this->assertStringNotContainsString('filesort', (string) $ranking->Extra);

        $historial = DB::selectOne('EXPLAIN SELECT id FROM resultado_juegos WHERE id_usuario = 3 ORDER BY id DESC LIMIT 500');
        $this->assertSame('idx_resultado_historial', $historial->key);
        $this->assertStringNotContainsString('filesort', (string) $historial->Extra);
    }

    // ---------------------------------------------------------------------------------- migración 000004

    /** Con datos limpios: crea los 2 índices, el de apoyo y la FK con ON UPDATE CASCADE / ON DELETE RESTRICT. */
    public function test_la_migracion_4_crea_indices_y_la_fk_con_datos_limpios(): void
    {
        DB::table('list_departamentos')->insert([['id' => 1, 'nombre' => 'A'], ['id' => 2, 'nombre' => 'B']]);
        DB::table('departamento_descripcion')->insert([['departamento' => 1], ['departamento' => 2], ['departamento' => null]]);
        $m = $this->migracion(self::M4);

        $m->up();

        $this->assertSame([], $m->avisos);
        $this->assertTrue($this->hayIndice('preguntas', 'idx_preguntas_departamento'));
        $this->assertTrue($this->hayIndice('opciones_verfal', 'idx_opciones_verfal_pregunta'));
        $this->assertTrue($this->hayFk());
        $regla = DB::selectOne("SELECT update_rule u, delete_rule d FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE() AND constraint_name = 'fk_departamento_descripcion_departamento'");
        $this->assertSame(['CASCADE', 'RESTRICT'], [$regla->u, $regla->d]);

        // La FK protege de verdad: no admite huérfanos, no deja borrar un departamento con ficha y propaga el cambio de id.
        try {
            DB::table('departamento_descripcion')->insert(['departamento' => 99]);
            $this->fail('Debió rechazar un departamento inexistente.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        try {
            DB::table('list_departamentos')->where('id', 1)->delete();
            $this->fail('Debió impedir borrar un departamento con ficha (RESTRICT).');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
        DB::table('list_departamentos')->where('id', 2)->update(['id' => 22]);
        $this->assertSame(1, DB::table('departamento_descripcion')->where('departamento', 22)->count());
    }

    /** Idempotente: una 2.ª ejecución no cambia nada; `down()` quita FK e índices y deja el DDL original. */
    public function test_la_migracion_4_es_idempotente_y_su_down_es_limpio(): void
    {
        $originales = array_map(fn ($t) => $this->ddl($t), ['preguntas', 'opciones_verfal', 'departamento_descripcion']);
        $m = $this->migracion(self::M4);
        $m->up();
        $trasUp = array_map(fn ($t) => $this->ddl($t), ['preguntas', 'opciones_verfal', 'departamento_descripcion']);
        $this->assertNotSame($originales, $trasUp);

        $m->up();
        $this->assertSame($trasUp, array_map(fn ($t) => $this->ddl($t), ['preguntas', 'opciones_verfal', 'departamento_descripcion']));

        $m->down();
        $this->assertSame($originales, array_map(fn ($t) => $this->ddl($t), ['preguntas', 'opciones_verfal', 'departamento_descripcion']));
        $m->down();
        $this->assertFalse($this->hayFk());
    }

    /**
     * Con huérfanas NO crea la FK, NO falla, deja un aviso con los ids y SÍ crea los índices (que no dependen de los datos).
     */
    public function test_con_huerfanas_no_crea_la_fk_pero_avisa_y_no_falla(): void
    {
        DB::table('list_departamentos')->insert(['id' => 1, 'nombre' => 'A']);
        $buena = DB::table('departamento_descripcion')->insertGetId(['departamento' => 1]);
        $huerfana = DB::table('departamento_descripcion')->insertGetId(['departamento' => 77]);
        $m = $this->migracion(self::M4);

        $m->up();

        $this->assertFalse($this->hayFk());
        $this->assertCount(1, $m->avisos);
        $this->assertStringContainsString((string) $huerfana, $m->avisos[0]);
        $this->assertStringNotContainsString("$buena,", $m->avisos[0].',');
        $this->assertTrue($this->hayIndice('preguntas', 'idx_preguntas_departamento'));
        $this->assertTrue($this->hayIndice('opciones_verfal', 'idx_opciones_verfal_pregunta'));

        // Al corregir los datos y repetir la migración (idempotente) la FK ya se puede crear.
        DB::table('departamento_descripcion')->where('id', $huerfana)->update(['departamento' => 1]);
        $m->up();
        $this->assertTrue($this->hayFk());
    }

    // ------------------------------------------------------------------------------------------------ auditoría

    /** Una base limpia y con las dos migraciones aplicadas no tiene bloqueantes (código 0). */
    public function test_la_auditoria_de_una_base_limpia_termina_en_cero(): void
    {
        $this->migracion(self::M3)->up();
        $this->migracion(self::M4)->up();

        [$codigo, $informe] = $this->auditar();

        $this->assertSame(0, $codigo);
        $this->assertSame(0, $informe['resumen']['bloqueantes']);
        $this->assertSame('explora_migtest', $informe['base']);
        $this->assertTrue($this->relacion($informe, 'departamento_descripcion', 'departamento')['fk']);
        foreach ($informe['indices'] as $i) {
            $this->assertTrue($i['presente'], $i['indice']);
        }
    }

    /**
     * Cada anomalía sembrada aparece en el informe con sus ids concretos y el código de salida es 2 (bloqueantes):
     * huérfanas de ambas relaciones candidatas, duplicados 1:1, polimórfica con huérfanos por rama, fecha basura y
     * `creado_en` NULL con fecha válida.
     */
    public function test_la_auditoria_detecta_cada_anomalia_sembrada(): void
    {
        // Antes de las migraciones (FK candidata ausente): se pueden sembrar huérfanas.
        DB::table('list_departamentos')->insert(['id' => 1, 'nombre' => 'A']);
        $d1 = DB::table('departamento_descripcion')->insertGetId(['departamento' => 1]);
        $dh = DB::table('departamento_descripcion')->insertGetId(['departamento' => 55]);
        DB::table('departamento_capital')->insert([['departamento' => $d1], ['departamento' => $d1]]); // duplicado 1:1
        DB::table('preguntas')->insert([
            ['id' => 10, 'departamento' => $d1, 'origen_pregunta' => 'departamentos'],
            ['id' => 11, 'departamento' => 4040, 'origen_pregunta' => 'departamentos'], // huérfana rama departamentos
            ['id' => 12, 'departamento' => 5050, 'origen_pregunta' => 'regiones'],      // huérfana rama regiones
        ]);
        $oh = DB::table('opciones_verfal')->insertGetId(['pregunta' => 999, 'respuesta' => 'V']);
        DB::table('opciones_verfal')->insert(['pregunta' => 10, 'respuesta' => 'F']);
        $this->sembrar(['fecha' => '05-03-2026 14:07:09']);
        $fb = $this->sembrar(['fecha' => '31-02-2026 10:00:00']);
        $this->migracion(self::M3)->up();
        $nuevo = $this->sembrar(['fecha' => '06-03-2026 10:00:00']); // insertada por «código viejo»: creado_en NULL

        [$codigo, $i] = $this->auditar();

        $this->assertSame(2, $codigo, 'huérfanas en relaciones candidatas = bloqueante');
        $verfal = $this->relacion($i, 'opciones_verfal', 'pregunta');
        $this->assertSame([1, [$oh]], [$verfal['huerfanos'], $verfal['ids_huerfanos']]);
        $this->assertFalse($verfal['fk']);
        $desc = $this->relacion($i, 'departamento_descripcion', 'departamento');
        $this->assertSame([1, [$dh]], [$desc['huerfanos'], $desc['ids_huerfanos']]);

        $cap = collect($i['duplicados_1a1'])->firstWhere('tabla', 'departamento_capital');
        $this->assertSame([1, 1], [$cap['departamentos_duplicados'], $cap['filas_sobrantes']]);
        $this->assertSame([['departamento' => $d1, 'veces' => 2]], $cap['ejemplos']);

        $ramas = collect($i['preguntas_polimorfica']['ramas'])->keyBy('origen_pregunta');
        $this->assertSame([2, 1, [11]], [$ramas['departamentos']['filas'], $ramas['departamentos']['huerfanos'], $ramas['departamentos']['ids_huerfanos']]);
        $this->assertSame([1, [12]], [$ramas['regiones']['huerfanos'], $ramas['regiones']['ids_huerfanos']]);

        $f = $i['resultado_juegos'];
        $this->assertSame([3, 2, 1], [$f['filas'], $f['fecha_parseable'], $f['fecha_no_parseable']]);
        $this->assertSame($fb, $f['muestra_no_parseables'][0]['id']);
        $this->assertSame(2, $f['creado_en_nulo'], 'la de fecha basura y la insertada por código anterior');
        $this->assertSame(1, $f['creado_en_nulo_con_fecha_valida']);
        $this->assertGreaterThan(0, $nuevo);

        $this->assertSame(2, $i['resumen']['bloqueantes'], 'dos bloqueantes: una por cada relación candidata con huérfanas');
        $codigos = array_column($i['resumen']['problemas'], 'codigo');
        foreach (['huerfanos', 'duplicados_1a1', 'preguntas_polimorfica', 'preguntas_huerfanas', 'fecha_no_parseable', 'creado_en_pendiente', 'fk_autorreferenciadas'] as $c) {
            $this->assertContains($c, $codigos, $c);
        }
    }

    /** Tipos incompatibles entre las dos columnas de una FK candidata: bloqueante (código 2) con el motivo. */
    public function test_la_auditoria_detecta_tipos_incompatibles(): void
    {
        DB::statement('ALTER TABLE departamento_descripcion MODIFY departamento BIGINT NULL');

        [$codigo, $i] = $this->auditar();

        $this->assertSame(2, $codigo);
        $this->assertFalse($this->relacion($i, 'departamento_descripcion', 'departamento')['compatible']);
        $this->assertContains('tipos_incompatibles', array_column($i['resumen']['problemas'], 'codigo'));
    }

    /** Solo avisos (sin bloqueantes) mantienen el código 0; y el texto legible también funciona. */
    public function test_solo_avisos_no_bloquean_y_la_salida_de_texto_funciona(): void
    {
        $this->sembrar(['fecha' => 'basura']); // aviso: fecha no interpretable; sin migraciones: avisos de índices ausentes

        $codigo = Artisan::call('explora:auditar-integridad');
        $texto = Artisan::output();

        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('Auditoría de integridad (solo lectura)', $texto);
        $this->assertStringContainsString('0 bloqueante(s)', $texto);
        $this->assertStringContainsString('resultado_juegos.creado_en no existe', $texto);
    }

    /**
     * Solo lectura y sin volcar contenido: el comando no cambia ningún dato ni deja la conexión en modo lectura, y de un
     * `fecha` enorme solo muestra 30 caracteres sin caracteres de control.
     */
    public function test_la_auditoria_no_escribe_ni_vuelca_campos_largos(): void
    {
        $this->sembrar(['fecha' => str_repeat('A', 5000)]);
        $this->sembrar(['fecha' => "mal\x01\x02\tformato"]);
        $antes = $this->huella('resultado_juegos');

        [, $i] = $this->auditar();

        $this->assertSame($antes, $this->huella('resultado_juegos'));
        $this->assertSame(0, (int) DB::selectOne('SELECT @@transaction_read_only r')->r, 'la transacción READ ONLY se cierra');
        foreach ($i['resultado_juegos']['muestra_no_parseables'] as $m) {
            $this->assertLessThanOrEqual(30, mb_strlen($m['valor']));
            $this->assertDoesNotMatchRegularExpression('/[\x00-\x08\x0B-\x1F]/', $m['valor']);
        }
        $this->assertSame(5000, $i['resultado_juegos']['muestra_no_parseables'][0]['longitud']);
        $this->assertLessThan(60000, strlen((string) json_encode($i)), 'el informe no contiene los 5000 caracteres');
        $this->assertStringNotContainsString(str_repeat('A', 31), (string) json_encode($i));

        // El servidor rechazaría una escritura dentro de la transacción de solo lectura.
        DB::unprepared('START TRANSACTION READ ONLY');
        try {
            DB::table('resultado_juegos')->insert($this->fila());
            $this->fail('MySQL debió rechazar la escritura en una transacción READ ONLY.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        } finally {
            DB::unprepared('ROLLBACK');
        }
    }

    // ------------------------------------------------------------------------- API: doble escritura y sin cambios

    /** POST /api/resultados escribe `fecha` (mismo formato de siempre) y `creado_en` (mismo instante). */
    public function test_guardar_un_resultado_escribe_fecha_y_creado_en(): void
    {
        $this->migracion(self::M3)->up();
        $this->travelTo(now()->setDateTime(2026, 11, 21, 15, 4, 5));

        $this->postJson('/api/resultados', [
            'juego' => 'laberinto', 'nivel' => 'Normal', 'categoria' => 'Capitales', 'puntaje' => 7, 'tiempo' => 30,
            'nombre' => 'Ana', 'grado' => '5', 'avatar' => '3.png',
        ])->assertStatus(201)->assertExactJson(['status' => 'success', 'mensaje' => 'Resultado guardado correctamente']);

        $fila = DB::table('resultado_juegos')->first();
        $this->assertSame('21-11-2026 15:04:05', $fila->fecha, 'el texto de `fecha` es idéntico al de antes');
        $this->assertSame('2026-11-21 15:04:05', $fila->creado_en);
        $this->assertSame(0, (int) $fila->id_usuario);
    }

    /** Con sesión también se escriben las dos columnas. */
    public function test_con_sesion_tambien_se_escriben_las_dos_columnas(): void
    {
        $this->migracion(self::M3)->up();

        $this->actingAs(Usuario::findOrFail(1), 'web')->postJson('/api/resultados', [
            'juego' => 'Ahorcado', 'nivel' => 'Normal', 'categoria' => 'Capitales', 'puntaje' => 3, 'tiempo' => 12,
        ])->assertStatus(201);

        $fila = DB::table('resultado_juegos')->first();
        $this->assertSame(1, (int) $fila->id_usuario);
        $this->assertSame($fila->fecha, date('d-m-Y H:i:s', strtotime($fila->creado_en)));
    }

    /**
     * El ranking y el historial devuelven EXACTAMENTE lo mismo antes y después de aplicar las migraciones (y coinciden con
     * el orden esperado), y el historial sigue devolviendo `fecha` como texto «d-m-Y H:i:s».
     */
    public function test_ranking_e_historial_devuelven_lo_mismo_antes_y_despues(): void
    {
        $this->sembrar(['jugador' => 'A', 'puntaje' => 50, 'tiempo' => 20, 'id_usuario' => 1, 'fecha' => '01-01-2026 10:00:00']);
        $this->sembrar(['jugador' => 'B', 'puntaje' => 90, 'tiempo' => 99, 'id_usuario' => 2, 'fecha' => '02-01-2026 10:00:00']);
        $this->sembrar(['jugador' => 'C', 'puntaje' => 90, 'tiempo' => 10, 'id_usuario' => 1, 'fecha' => '03-01-2026 10:00:00']);
        $this->sembrar(['jugador' => 'D', 'puntaje' => 90, 'tiempo' => 10, 'id_usuario' => 0, 'fecha' => 'basura']);
        $this->sembrar(['jugador' => 'E', 'puntaje' => 50, 'tiempo' => 20, 'id_usuario' => 1, 'fecha' => '05-01-2026 10:00:00']);
        $this->sembrar(['jugador' => 'F', 'puntaje' => 1, 'tiempo' => 1, 'id_usuario' => 1, 'categoria' => 'Otra']);
        $url = '/api/ranking?juego=laberinto&nivel=Normal&categoria=Capitales';
        $usuario = Usuario::findOrFail(1);

        $rankingAntes = $this->getJson($url)->assertOk()->json();
        $historialAntes = $this->actingAs($usuario, 'web')->getJson('/api/historial')->assertOk()->json();

        $this->migracion(self::M3)->up();
        $this->migracion(self::M4)->up();

        $this->assertSame($rankingAntes, $this->getJson($url)->assertOk()->json());
        $this->assertSame($historialAntes, $this->actingAs($usuario, 'web')->getJson('/api/historial')->assertOk()->json());

        // Orden esperado: puntaje desc, tiempo asc, id asc (C antes que D por id); E tras A por id.
        $this->assertSame(['C', 'D', 'B', 'A', 'E'], array_column($rankingAntes, 'jugador'));
        $this->assertEqualsCanonicalizing(['jugador', 'avatar', 'grado', 'puntaje', 'tiempo'], array_keys($rankingAntes[0]));
        // Historial del usuario 1: de la fila más reciente (id) a la más antigua: F, E, C, A.
        $this->assertSame([1, 50, 90, 50], array_column($historialAntes, 'puntaje'));
        foreach ($historialAntes as $h) {
            $this->assertIsString($h['fecha']);
            $this->assertArrayNotHasKey('creado_en', $h, 'el contrato de la API no cambia');
        }
        $this->assertSame('05-01-2026 10:00:00', $historialAntes[1]['fecha']);
    }
}
