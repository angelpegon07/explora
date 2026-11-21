<?php

namespace App\Console\Commands;

use App\Support\ImagenesEmbebidas;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Saca de la base de datos las imágenes embebidas en base64 en el HTML de las fichas (DAT-07 / REN-03 / FUN-11) y las
 * deja como archivos servidos por Apache; en la base queda solo la URL.
 *
 * Funcionamiento (ver también README y PROPUESTA_DESPLIEGUE.md):
 *  - Descubre SOLO las columnas de texto/blob de la base conectada que contienen `data:image/` (INFORMATION_SCHEMA),
 *    sin listas escritas a mano: así no se escapa ninguna tabla, y las técnicas se excluyen por configuración.
 *  - Por cada `<img src="data:image/<mime>;base64,<datos>">` decodifica de forma estricta, detecta el tipo REAL por
 *    «magic bytes» (el MIME declarado miente: muchos GIF se declaran PNG), guarda `<dir>/<2 hex>/<sha256>.<ext>` y
 *    sustituye SOLO el valor del `src` por `<contenido_url>/<2 hex>/<sha256>.<ext>`. El resto del HTML no cambia.
 *  - El nombre del archivo sale únicamente del hash del contenido: deduplica, es idempotente y no admite entrada del
 *    usuario (sin recorrido de directorios).
 *  - `--dry-run` (por defecto) no escribe nada; `--aplicar` escribe archivos y filas; `--revertir` vuelve a incrustar
 *    base64 (funcionalmente equivalente, NO idéntico byte a byte: el MIME declarado pasa a ser el real y la codificación
 *    es la estándar); `--verificar=<informe.json>` comprueba un informe anterior contra el disco y la base.
 *
 * Seguridad: `--aplicar`/`--revertir` se niegan a correr si la base no termina en `_test` o `_migtest`, salvo que se
 * pasen `--base-real` Y `--confirmo-respaldo`. El destino debe quedar dentro de `public/`.
 */
class ExternalizarImagenes extends Command
{
    /** @var string */
    protected $signature = 'explora:externalizar-imagenes
        {--dry-run : Simula y muestra cifras sin escribir nada (es el modo por defecto)}
        {--aplicar : Escribe los archivos y actualiza las filas (una transacción por fila)}
        {--revertir : Vuelve a incrustar en base64 las URL de contenido_url leyendo los archivos}
        {--verificar= : Ruta de un informe JSON de --aplicar: comprueba archivos (sha256) y URL en la base}
        {--base-real : Permite --aplicar/--revertir en una base cuyo nombre no acaba en _test/_migtest (exige --confirmo-respaldo)}
        {--confirmo-respaldo : Confirma que se hizo un mysqldump previo de la base}
        {--tabla= : Limita a estas tablas (separadas por comas)}
        {--columna= : Limita a estas columnas (separadas por comas)}
        {--limite= : Máximo de filas a procesar por columna}
        {--destino= : Carpeta de destino (dentro de public/) en vez de explora.contenido_dir}
        {--conexion= : Conexión de base de datos (por defecto la del proyecto)}';

    /** @var string */
    protected $description = 'Externaliza a archivos las imágenes base64 del contenido de las fichas (dry-run por defecto)';

    /** Sufijos de nombre de base en los que `--aplicar`/`--revertir` están permitidos sin banderas especiales. */
    private const SUFIJOS_SEGUROS = ['_test', '_migtest'];

    /** Tipos de columna que pueden contener HTML con imágenes. */
    private const TIPOS_COLUMNA = ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'tinyblob', 'blob', 'mediumblob', 'longblob'];

    /** Un `data:image/x;base64,` mínimo ocupa más de 20 bytes: las columnas más cortas no se miran. */
    private const LONGITUD_MINIMA_COLUMNA = 30;

    /** Margen que se deja bajo `max_allowed_packet` para el resto de la sentencia UPDATE. */
    private const MARGEN_PAQUETE = 1048576;

    private Connection $conn;

    /** Carpeta de destino ya validada (ruta absoluta, sin barra final). */
    private string $raiz;

    /** Prefijo público validado (sin barra final). */
    private string $urlBase;

    private int $maxPaquete = 0;

    /** @var array<string, int> Hashes ya contados en esta ejecución => bytes (para duplicados y cifras de «archivos nuevos»). */
    private array $vistos = [];

    /** @var array<string, array<string, mixed>> Cifras por `tabla.columna`. */
    private array $stats = [];

    /** @var array<int, array<string, mixed>> Anomalías (nunca incluyen contenido, solo ubicación y motivo). */
    private array $anomalias = [];

    /** @var array<int, array<string, mixed>> Una entrada por fila modificada (o por modificar en dry-run). */
    private array $filasInforme = [];

    private int $errores = 0;

    /**
     * Punto de entrada: valida opciones y guardas, descubre las columnas y procesa fila por fila.
     *
     * @return int Command::SUCCESS, o Command::FAILURE si una guarda falla, hay errores de fila o la verificación no cuadra.
     */
    public function handle(): int
    {
        // Estado limpio en cada ejecución: Laravel reutiliza la MISMA instancia del comando si se invoca varias veces en un
        // mismo proceso (Artisan::call en pruebas), y las cifras de una ejecución no deben sumarse a las de la siguiente.
        $this->vistos = [];
        $this->stats = [];
        $this->anomalias = [];
        $this->filasInforme = [];
        $this->errores = 0;

        $verificar = $this->option('verificar');
        $modos = array_filter([
            'dry-run' => (bool) $this->option('dry-run'),
            'aplicar' => (bool) $this->option('aplicar'),
            'revertir' => (bool) $this->option('revertir'),
            'verificar' => $verificar !== null,
        ]);
        if (count($modos) > 1) {
            return $this->fallo('Las opciones --dry-run, --aplicar, --revertir y --verificar son excluyentes.');
        }
        $modo = array_key_first($modos) ?? 'dry-run';
        $escribe = in_array($modo, ['aplicar', 'revertir'], true);

        $nombreConexion = (string) ($this->option('conexion') ?: config('database.default'));
        if (! is_array(config("database.connections.$nombreConexion"))) {
            return $this->fallo("La conexión '$nombreConexion' no existe.");
        }
        $this->conn = DB::connection($nombreConexion);
        $this->conn->disableQueryLog();
        $base = (string) $this->conn->getDatabaseName();

        if ($escribe && ($error = $this->comprobarGuardaDeBase($base)) !== null) {
            return $this->fallo($error);
        }

        try {
            $this->urlBase = $this->validarUrl((string) config('explora.contenido_url'));
            $this->raiz = $this->resolverDestino($this->option('destino') !== null ? (string) $this->option('destino') : (string) config('explora.contenido_dir'));
        } catch (RuntimeException $e) {
            return $this->fallo($e->getMessage());
        }

        $this->subirMemoria();
        $this->maxPaquete = (int) ($this->conn->selectOne('SELECT @@max_allowed_packet AS p')->p ?? 0);

        if ($modo === 'verificar') {
            return $this->verificar((string) $verificar);
        }

        $limite = $this->option('limite') !== null ? (int) $this->option('limite') : null;
        if ($limite !== null && $limite < 1) {
            return $this->fallo('--limite debe ser un entero positivo.');
        }

        $this->line("Base: $base · modo: $modo · destino: ".$this->rutaRelativa($this->raiz).' · URL: '.$this->urlBase);
        if ($modo === 'dry-run') {
            $this->comment('Simulación: no se escribe nada en disco ni en la base.');
        }

        $inicio = microtime(true);
        $columnas = $this->descubrirColumnas($base);
        $this->line(count($columnas).' columna(s) candidata(s) tras filtros y exclusiones. Buscando `data:image/`…');

        foreach ($columnas as $col) {
            $this->procesarColumna($col, $modo, $limite);
        }

        $segundos = microtime(true) - $inicio;
        $this->resumen($modo, $segundos);
        $informe = $escribe ? $this->escribirInforme($modo, $base, $segundos) : null;
        if ($informe !== null) {
            $this->line('Informe: '.$this->rutaRelativa($informe));
        }

        return $this->errores > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Guarda de seguridad: solo se escribe en bases de prueba, salvo confirmación explícita del responsable.
     *
     * @param  string  $base  Nombre de la base conectada.
     * @return string|null Mensaje de error, o null si se puede continuar.
     */
    private function comprobarGuardaDeBase(string $base): ?string
    {
        foreach (self::SUFIJOS_SEGUROS as $sufijo) {
            if (str_ends_with($base, $sufijo)) {
                return null;
            }
        }
        $real = (bool) $this->option('base-real');
        $respaldo = (bool) $this->option('confirmo-respaldo');
        if ($real && $respaldo) {
            $this->warn("ATENCIÓN: se modificará la base '$base' (no es de prueba). Se asume que existe un mysqldump previo.");

            return null;
        }

        return "La base '$base' no termina en _test ni _migtest: --aplicar/--revertir se niegan a escribir en ella. "
            .'Si es la base real, haga primero un mysqldump y repita con --base-real --confirmo-respaldo (las dos banderas).';
    }

    /**
     * Valida el prefijo público: ruta absoluta del sitio, sin `..`, sin `//`, sin comillas ni caracteres raros.
     *
     * @throws RuntimeException Si no es válido.
     */
    private function validarUrl(string $url): string
    {
        $url = rtrim($url, '/');
        if (preg_match('#^/[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$#', $url) !== 1 || str_contains($url, '..')) {
            throw new RuntimeException("explora.contenido_url no es válida: '$url' (debe ser una ruta como /contenido/fichas).");
        }

        return $url;
    }

    /**
     * Resuelve y valida la carpeta de destino: sin `..` ni byte nulo, y SIEMPRE estrictamente dentro de `public/`
     * (resolviendo enlaces simbólicos: un enlace que sale del docroot se rechaza). La carpeta puede no existir aún.
     *
     * @param  string  $dir  Ruta absoluta o relativa a la raíz del proyecto.
     * @return string Ruta absoluta normalizada, sin barra final.
     *
     * @throws RuntimeException Si queda fuera de `public/` o contiene `..`.
     */
    private function resolverDestino(string $dir): string
    {
        if ($dir === '' || str_contains($dir, "\0") || in_array('..', preg_split('#[\\\\/]+#', $dir) ?: [], true)) {
            throw new RuntimeException('El destino no es válido (vacío, con byte nulo o con «..»).');
        }
        $ruta = rtrim(str_starts_with($dir, '/') ? $dir : base_path($dir), '/');
        $publico = realpath(public_path());
        if ($publico === false) {
            throw new RuntimeException('No se encontró public/.');
        }

        // Se resuelve el ancestro existente más cercano (así se detectan enlaces simbólicos) y se le añade el resto.
        $resto = [];
        $actual = $ruta;
        while ($actual !== '' && $actual !== '/' && ! file_exists($actual)) {
            array_unshift($resto, basename($actual));
            $actual = dirname($actual);
        }
        $real = realpath($actual === '' ? '/' : $actual);
        if ($real === false) {
            throw new RuntimeException('El destino no se puede resolver.');
        }
        $final = rtrim($real.'/'.implode('/', $resto), '/');
        if (! str_starts_with($final.'/', $publico.'/') || $final === $publico) {
            throw new RuntimeException('El destino debe estar DENTRO de public/ (carpeta propia, p. ej. public/contenido/fichas).');
        }

        return $final;
    }

    /**
     * Sube `memory_limit` a 1 GB si es menor: las filas llegan a decenas de MB y se procesan con varias copias.
     */
    private function subirMemoria(): void
    {
        $v = trim((string) ini_get('memory_limit'));
        $n = (int) $v;
        $bytes = match (strtoupper(substr($v, -1))) {
            'G' => $n * 1024 ** 3,
            'M' => $n * 1024 ** 2,
            'K' => $n * 1024,
            default => $n,
        };
        if ($bytes !== -1 && $bytes < 1024 ** 3) {
            ini_set('memory_limit', '1G');
        }
    }

    /**
     * Descubre las columnas candidatas con INFORMATION_SCHEMA y aplica exclusiones y filtros de línea de comandos.
     * Los filtros `--tabla`/`--columna` solo SELECCIONAN entre lo descubierto (nunca se usan como identificador directo).
     *
     * @param  string  $base  Nombre de la base conectada.
     * @return array<int, array{tabla: string, columna: string, pk: array<int, string>}>
     */
    private function descubrirColumnas(string $base): array
    {
        $marcadores = implode(',', array_fill(0, count(self::TIPOS_COLUMNA), '?'));
        $filas = $this->conn->select(
            "SELECT c.TABLE_NAME AS t, c.COLUMN_NAME AS c, c.CHARACTER_MAXIMUM_LENGTH AS l
               FROM information_schema.COLUMNS c
               JOIN information_schema.TABLES x ON x.TABLE_SCHEMA = c.TABLE_SCHEMA AND x.TABLE_NAME = c.TABLE_NAME
              WHERE c.TABLE_SCHEMA = ? AND x.TABLE_TYPE = 'BASE TABLE' AND c.DATA_TYPE IN ($marcadores)
              ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION",
            [$base, ...self::TIPOS_COLUMNA]
        );
        $pks = [];
        foreach ($this->conn->select(
            "SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = ? AND INDEX_NAME = 'PRIMARY' ORDER BY TABLE_NAME, SEQ_IN_INDEX",
            [$base]
        ) as $p) {
            $pks[$p->t][] = $p->c;
        }

        $excluir = (array) config('explora.externalizar_excluir', []);
        $soloTablas = $this->lista($this->option('tabla'));
        $soloColumnas = $this->lista($this->option('columna'));
        $res = [];
        foreach ($filas as $f) {
            $t = $f->t;
            $c = $f->c;
            if (($f->l !== null && (int) $f->l < self::LONGITUD_MINIMA_COLUMNA)
                || in_array("$t.*", $excluir, true) || in_array("$t.$c", $excluir, true)
                || ($soloTablas !== [] && ! in_array($t, $soloTablas, true))
                || ($soloColumnas !== [] && ! in_array($c, $soloColumnas, true))) {
                continue;
            }
            $res[] = ['tabla' => $t, 'columna' => $c, 'pk' => $pks[$t] ?? []];
        }

        return $res;
    }

    /**
     * @param  mixed  $valor  Valor de una opción con lista separada por comas.
     * @return array<int, string>
     */
    private function lista(mixed $valor): array
    {
        return $valor === null ? [] : array_values(array_filter(array_map('trim', explode(',', (string) $valor))));
    }

    /**
     * Busca las filas candidatas de una columna (solo sus claves primarias, nunca el contenido) y las procesa una a una.
     *
     * @param  array{tabla: string, columna: string, pk: array<int, string>}  $col
     */
    private function procesarColumna(array $col, string $modo, ?int $limite): void
    {
        ['tabla' => $t, 'columna' => $c, 'pk' => $pk] = $col;
        // Para --revertir se buscan filas que contengan la URL base; para lo demás, `data:image/`. Los comodines de LIKE no
        // aparecen en ninguno de los dos patrones (la URL está validada).
        $patron = $modo === 'revertir' ? '%'.$this->urlBase.'/%' : '%data:image/%';

        if ($pk === []) {
            if ($this->conn->table($t)->where($c, 'like', $patron)->exists()) {
                $this->anomalia($t, $c, null, 'sin_clave_primaria', 'La tabla no tiene clave primaria: no se puede actualizar fila a fila.');
            }

            return;
        }

        $q = $this->conn->table($t)->select($pk)->where($c, 'like', $patron);
        foreach ($pk as $columnaPk) {
            $q->orderBy($columnaPk);
        }
        if ($limite !== null) {
            $q->limit($limite);
        }
        $claves = $q->get();
        if ($claves->isEmpty()) {
            return;
        }

        $this->stats["$t.$c"] = [
            'tabla' => $t, 'columna' => $c, 'filas' => 0, 'filas_omitidas' => 0, 'imagenes' => 0, 'bytes_base64' => 0,
            'archivos_nuevos' => 0, 'bytes_archivos_nuevos' => 0, 'duplicadas' => 0, 'ya_existentes' => 0,
            'tipos' => [], 'mime_declarado_distinto' => 0,
        ];

        foreach ($claves as $fila) {
            $clave = (array) $fila;
            try {
                $this->procesarFila($t, $c, $clave, $modo);
            } catch (Throwable $e) {
                $this->errores++;
                $detalle = $e instanceof QueryException ? (string) ($e->errorInfo[2] ?? 'error SQL') : $e->getMessage();
                $this->anomalia($t, $c, $clave, 'error', mb_substr($detalle, 0, 200));
            }
        }
    }

    /**
     * Procesa UNA fila. En `--aplicar`/`--revertir` va en su propia transacción: se bloquea la fila, se lee el valor
     * ACTUAL (si cambió desde la búsqueda se trabaja sobre el nuevo), se escriben primero los archivos y después se
     * actualiza la fila; si algo falla, la transacción se deshace y la fila queda como estaba.
     *
     * @param  array<string, mixed>  $clave  Columna de clave primaria => valor.
     *
     * @throws Throwable Cualquier fallo (el llamador lo registra y sigue con la fila siguiente).
     */
    private function procesarFila(string $t, string $c, array $clave, string $modo): void
    {
        $escribe = in_array($modo, ['aplicar', 'revertir'], true);
        if ($escribe) {
            $this->conn->beginTransaction();
        }
        try {
            $consulta = $this->conn->table($t)->where($clave);
            if ($escribe) {
                // Bloqueo solo durante esta fila: el sitio solo lee y MySQL (InnoDB) no bloquea las lecturas normales.
                $consulta->lockForUpdate();
            }
            $valor = $consulta->value($c);
            $analisis = is_string($valor)
                ? ($modo === 'revertir' ? $this->analizarRevertir($valor) : $this->analizarAplicar($valor))
                : ['cambios' => [], 'imagenes' => [], 'omitir' => false, 'anomalias' => [], 'archivos' => []];

            foreach ($analisis['anomalias'] as $a) {
                $this->anomalia($t, $c, $clave, $a[0], $a[1]);
            }
            if ($analisis['omitir']) {
                $this->stats["$t.$c"]['filas_omitidas']++;
            }
            if ($analisis['omitir'] || $analisis['cambios'] === []) {
                if ($escribe) {
                    $this->conn->commit();
                }

                return;
            }

            $nuevo = ImagenesEmbebidas::reemplazarTramos((string) $valor, $analisis['cambios']);
            if ($this->maxPaquete > 0 && strlen($nuevo) > $this->maxPaquete - self::MARGEN_PAQUETE) {
                $this->anomalia($t, $c, $clave, 'fila_demasiado_grande', 'El valor resultante supera max_allowed_packet; fila sin cambios.');
                $this->stats["$t.$c"]['filas_omitidas']++;
                if ($escribe) {
                    $this->conn->commit();
                }

                return;
            }

            if ($escribe) {
                if ($modo === 'aplicar') {
                    foreach ($analisis['archivos'] as $hash => [$ext, $bin]) {
                        $this->guardarArchivo($hash, $ext, $bin);
                    }
                }
                $this->conn->table($t)->where($clave)->update([$c => $nuevo]);
                $this->conn->commit();
            }
        } catch (Throwable $e) {
            if ($escribe && $this->conn->transactionLevel() > 0) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        $this->contabilizar($t, $c, $clave, $analisis['imagenes'], $modo);
    }

    /**
     * Analiza el valor de una celda para `--aplicar`/`--dry-run`: decodifica y clasifica cada `data:image` sin escribir nada.
     *
     * @return array{cambios: array<int, array{inicio: int, fin: int, nuevo: string}>, imagenes: array<int, array<string, mixed>>, omitir: bool, anomalias: array<int, array{0: string, 1: string}>, archivos: array<string, array{0: string, 1: string}>}
     */
    private function analizarAplicar(string $valor): array
    {
        $loc = ImagenesEmbebidas::localizarDataUris($valor);
        $anomalias = [];
        foreach ($loc['desconocidas'] as $motivo) {
            $anomalias[] = ['contexto_no_reconocido', "data:image/ $motivo: se deja intacto."];
        }
        $cambios = [];
        $imagenes = [];
        $archivos = [];
        $locales = [];
        $omitir = false;
        foreach ($loc['uris'] as $u) {
            $b64 = substr($valor, $u['b64_inicio'], $u['fin'] - $u['b64_inicio']);
            $bin = ImagenesEmbebidas::decodificarEstricto($b64);
            if ($bin === null) {
                // Base64 dañado: no se adivina nada y la fila ENTERA queda sin tocar (se reporta).
                $anomalias[] = ['base64_invalido', 'Base64 inválido o truncado ('.strlen($b64).' bytes, MIME declarado '.$u['mime'].'): fila sin cambios.'];
                $omitir = true;

                continue;
            }
            $ext = ImagenesEmbebidas::tipoReal($bin);
            if ($ext === null) {
                // SVG u otro tipo: no se sirve contenido arbitrario de la base como archivo; se deja en la base.
                $anomalias[] = ['tipo_no_soportado', 'Tipo no admitido (MIME declarado '.$u['mime'].', '.strlen($bin).' bytes): se deja intacto.'];

                continue;
            }
            $hash = hash('sha256', $bin);
            if (isset($this->vistos[$hash]) || isset($locales[$hash])) {
                $clase = 'duplicada';
            } else {
                $clase = is_file($this->rutaArchivo($hash, $ext)) ? 'existente' : 'nueva';
            }
            $locales[$hash] = true;
            $archivos[$hash] = [$ext, $bin];
            $imagenes[] = [
                'sha256' => $hash, 'ext' => $ext, 'bytes' => strlen($bin), 'bytes_base64' => strlen($b64),
                'mime_declarado' => $u['mime'], 'clase' => $clase,
            ];
            $cambios[] = [
                'inicio' => $u['inicio'], 'fin' => $u['fin'],
                'nuevo' => $this->urlBase.'/'.substr($hash, 0, 2).'/'.$hash.'.'.$ext,
            ];
        }

        return compact('cambios', 'imagenes', 'omitir', 'anomalias', 'archivos');
    }

    /**
     * Analiza el valor de una celda para `--revertir`: lee cada archivo referenciado, comprueba su sha256 y prepara el
     * `data:` equivalente con el MIME REAL. Si falta un archivo o no coincide el hash, la fila entera queda sin tocar.
     *
     * @return array{cambios: array<int, array{inicio: int, fin: int, nuevo: string}>, imagenes: array<int, array<string, mixed>>, omitir: bool, anomalias: array<int, array{0: string, 1: string}>, archivos: array<string, array{0: string, 1: string}>}
     */
    private function analizarRevertir(string $valor): array
    {
        $cambios = [];
        $imagenes = [];
        $anomalias = [];
        $omitir = false;
        $cache = [];
        foreach (ImagenesEmbebidas::localizarUrls($valor, $this->urlBase) as $u) {
            $ruta = $this->rutaArchivo($u['hash'], $u['ext']);
            if (! isset($cache[$u['hash']])) {
                $bin = is_file($ruta) && ! is_link($ruta) ? file_get_contents($ruta) : false;
                if ($bin === false) {
                    $anomalias[] = ['archivo_faltante', 'No se encuentra el archivo '.$u['hash'].'.'.$u['ext'].': fila sin cambios.'];
                    $omitir = true;

                    continue;
                }
                if (hash('sha256', $bin) !== $u['hash']) {
                    $anomalias[] = ['archivo_corrupto', 'El contenido de '.$u['hash'].'.'.$u['ext'].' no coincide con su sha256: fila sin cambios.'];
                    $omitir = true;

                    continue;
                }
                $cache[$u['hash']] = base64_encode($bin);
            }
            $mime = ImagenesEmbebidas::TIPOS[$u['ext']];
            $cambios[] = ['inicio' => $u['inicio'], 'fin' => $u['fin'], 'nuevo' => 'data:'.$mime.';base64,'.$cache[$u['hash']]];
            $imagenes[] = [
                'sha256' => $u['hash'], 'ext' => $u['ext'], 'bytes' => (int) (strlen($cache[$u['hash']]) * 3 / 4),
                'bytes_base64' => strlen($cache[$u['hash']]), 'mime_declarado' => $mime, 'clase' => 'revertida',
            ];
        }

        return ['cambios' => $cambios, 'imagenes' => $imagenes, 'omitir' => $omitir, 'anomalias' => $anomalias, 'archivos' => []];
    }

    /**
     * Ruta absoluta de un archivo de contenido. Solo recibe hash y extensión VALIDADOS (hex y lista cerrada).
     */
    private function rutaArchivo(string $hash, string $ext): string
    {
        return $this->raiz.'/'.substr($hash, 0, 2).'/'.$hash.'.'.$ext;
    }

    /**
     * Escribe un archivo de forma atómica (temporal + rename) y comprueba que lo escrito tiene el sha256 esperado.
     * Si ya existe con el mismo hash no hace nada (idempotencia y deduplicación).
     *
     * @throws RuntimeException Si no se puede crear la carpeta, la carpeta sale del destino o falla la escritura.
     */
    private function guardarArchivo(string $hash, string $ext, string $bin): void
    {
        if (! is_dir($this->raiz) && ! mkdir($this->raiz, 0755, true) && ! is_dir($this->raiz)) {
            throw new RuntimeException('No se pudo crear la carpeta de destino.');
        }
        $dir = $this->raiz.'/'.substr($hash, 0, 2);
        if (! is_dir($dir) && ! mkdir($dir, 0755) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la subcarpeta de destino.');
        }
        // Defensa extra: ni la raíz ni el subdirectorio pueden ser (ahora) un enlace que salga de public/.
        $publico = realpath(public_path());
        $realDir = realpath($dir);
        if ($realDir === false || $publico === false || ! str_starts_with($realDir.'/', $publico.'/')) {
            throw new RuntimeException('La subcarpeta de destino quedó fuera de public/.');
        }
        $final = $realDir.'/'.$hash.'.'.$ext;
        if (is_file($final) && ! is_link($final) && hash_file('sha256', $final) === $hash) {
            return;
        }
        $tmp = $realDir.'/.'.$hash.'.'.bin2hex(random_bytes(4)).'.tmp';
        if (file_put_contents($tmp, $bin) !== strlen($bin)) {
            @unlink($tmp);
            throw new RuntimeException('Escritura incompleta de un archivo de imagen.');
        }
        chmod($tmp, 0644);
        if (! rename($tmp, $final)) {
            @unlink($tmp);
            throw new RuntimeException('No se pudo colocar un archivo de imagen.');
        }
        if (hash_file('sha256', $final) !== $hash) {
            throw new RuntimeException('El archivo escrito no coincide con su sha256.');
        }
    }

    /**
     * Suma a las cifras y al informe una fila ya procesada con éxito (o simulada en dry-run).
     *
     * @param  array<string, mixed>  $clave
     * @param  array<int, array<string, mixed>>  $imagenes
     */
    private function contabilizar(string $t, string $c, array $clave, array $imagenes, string $modo): void
    {
        if ($imagenes === []) {
            return;
        }
        $s = &$this->stats["$t.$c"];
        $s['filas']++;
        $quitados = 0;
        foreach ($imagenes as $i) {
            $s['imagenes']++;
            $s['bytes_base64'] += $i['bytes_base64'];
            $quitados += $i['bytes_base64'];
            $s['tipos'][$i['ext']] = ($s['tipos'][$i['ext']] ?? 0) + 1;
            if ($modo !== 'revertir' && $i['mime_declarado'] !== ImagenesEmbebidas::TIPOS[$i['ext']]) {
                $s['mime_declarado_distinto']++;
            }
            if ($i['clase'] === 'duplicada') {
                $s['duplicadas']++;
            } elseif ($i['clase'] === 'existente') {
                $s['ya_existentes']++;
                $this->vistos[$i['sha256']] = $i['bytes'];
            } elseif ($i['clase'] === 'nueva') {
                $s['archivos_nuevos']++;
                $s['bytes_archivos_nuevos'] += $i['bytes'];
                $this->vistos[$i['sha256']] = $i['bytes'];
            }
        }
        $this->filasInforme[] = [
            'tabla' => $t, 'columna' => $c, 'clave' => $clave, 'imagenes' => count($imagenes),
            'bytes_base64' => $quitados,
            'archivos' => array_map(fn ($i) => ['sha256' => $i['sha256'], 'ext' => $i['ext'], 'bytes' => $i['bytes']], $imagenes),
        ];
    }

    /**
     * Registra una anomalía (ubicación y motivo; nunca contenido).
     *
     * @param  array<string, mixed>|null  $clave
     */
    private function anomalia(string $t, string $c, ?array $clave, string $motivo, string $detalle): void
    {
        $this->anomalias[] = ['tabla' => $t, 'columna' => $c, 'clave' => $clave, 'motivo' => $motivo, 'detalle' => $detalle];
    }

    /**
     * Imprime la tabla por columna, los totales y las anomalías.
     */
    private function resumen(string $modo, float $segundos): void
    {
        $verbo = match ($modo) {
            'aplicar' => 'Aplicado',
            'revertir' => 'Revertido',
            default => 'Simulado (nada escrito)',
        };
        $filas = [];
        $tot = ['filas' => 0, 'imagenes' => 0, 'bytes_base64' => 0, 'archivos_nuevos' => 0, 'bytes_archivos_nuevos' => 0, 'duplicadas' => 0, 'ya_existentes' => 0, 'filas_omitidas' => 0, 'distinto' => 0];
        $tipos = [];
        foreach ($this->stats as $s) {
            if ($s['filas'] === 0 && $s['filas_omitidas'] === 0) {
                continue;
            }
            $filas[] = [$s['tabla'].'.'.$s['columna'], $s['filas'], $s['filas_omitidas'], $s['imagenes'], $this->mb($s['bytes_base64']), $s['archivos_nuevos'].' ('.$this->mb($s['bytes_archivos_nuevos']).')', $s['duplicadas']];
            $tot['filas'] += $s['filas'];
            $tot['filas_omitidas'] += $s['filas_omitidas'];
            $tot['imagenes'] += $s['imagenes'];
            $tot['bytes_base64'] += $s['bytes_base64'];
            $tot['archivos_nuevos'] += $s['archivos_nuevos'];
            $tot['bytes_archivos_nuevos'] += $s['bytes_archivos_nuevos'];
            $tot['duplicadas'] += $s['duplicadas'];
            $tot['ya_existentes'] += $s['ya_existentes'];
            $tot['distinto'] += $s['mime_declarado_distinto'];
            foreach ($s['tipos'] as $k => $n) {
                $tipos[$k] = ($tipos[$k] ?? 0) + $n;
            }
        }
        $this->newLine();
        if ($filas !== []) {
            $this->table(['Tabla.columna', 'Filas', 'Omitidas', 'Imágenes', 'MB base64', 'Archivos nuevos (MB)', 'Duplicadas'], $filas);
        }
        ksort($tipos);
        $this->line("$verbo: {$tot['filas']} fila(s), {$tot['imagenes']} imagen(es), {$this->mb($tot['bytes_base64'])} MB de base64"
            .($modo === 'revertir' ? ' incrustados.' : ' quitados de la base.'));
        if ($modo !== 'revertir') {
            $this->line("Archivos a crear/creados: {$tot['archivos_nuevos']} ({$this->mb($tot['bytes_archivos_nuevos'])} MB); duplicadas dentro de la ejecución: {$tot['duplicadas']}; ya existentes en disco: {$tot['ya_existentes']}.");
            $this->line('Tipos reales: '.json_encode($tipos).'; con MIME declarado distinto del real: '.$tot['distinto'].'.');
        }
        $this->line("Filas omitidas por anomalía: {$tot['filas_omitidas']}. Tiempo: ".number_format($segundos, 1).' s; memoria pico: '.$this->mb(memory_get_peak_usage(true)).' MB.');

        if ($this->anomalias !== []) {
            $porMotivo = [];
            foreach ($this->anomalias as $a) {
                $porMotivo[$a['motivo']] = ($porMotivo[$a['motivo']] ?? 0) + 1;
            }
            $this->warn('Anomalías ('.count($this->anomalias).'): '.json_encode($porMotivo));
            foreach (array_slice($this->anomalias, 0, 15) as $a) {
                $this->line(" - {$a['tabla']}.{$a['columna']} ".json_encode($a['clave']).": {$a['motivo']}");
            }
            if (count($this->anomalias) > 15) {
                $this->line('   … el resto está en el informe JSON (o repita con --limite).');
            }
        }
        if ($this->errores > 0) {
            $this->error("{$this->errores} fila(s) con error: quedaron sin cambios.");
        }
    }

    /**
     * Escribe el informe JSON en `storage/logs/externalizar-imagenes-<fecha>.json`: inventario para verificar y revertir
     * (tabla, columna, clave, nº de imágenes, hashes y bytes). No contiene el contenido de las imágenes.
     *
     * @return string|null Ruta del informe, o null si no se pudo escribir.
     */
    private function escribirInforme(string $modo, string $base, float $segundos): ?string
    {
        $resumen = array_values($this->stats);
        $informe = [
            'comando' => 'explora:externalizar-imagenes',
            'modo' => $modo,
            'fecha' => date('c'),
            'base_datos' => $base,
            'contenido_url' => $this->urlBase,
            'destino' => $this->rutaRelativa($this->raiz),
            'segundos' => round($segundos, 2),
            'memoria_pico_bytes' => memory_get_peak_usage(true),
            'totales' => [
                'filas' => count($this->filasInforme),
                'imagenes' => array_sum(array_column($this->filasInforme, 'imagenes')),
                'bytes_base64' => array_sum(array_column($this->filasInforme, 'bytes_base64')),
                'errores' => $this->errores,
                'anomalias' => count($this->anomalias),
            ],
            'columnas' => $resumen,
            'filas' => $this->filasInforme,
            'anomalias' => $this->anomalias,
        ];
        $json = json_encode($informe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $dir = storage_path('logs');
        $ruta = $dir.'/externalizar-imagenes-'.date('Ymd-His').'.json';
        for ($n = 2; file_exists($ruta); $n++) {
            $ruta = $dir.'/externalizar-imagenes-'.date('Ymd-His')."-$n.json";
        }
        if ($json === false || ! is_dir($dir) || file_put_contents($ruta, $json) === false) {
            $this->error('No se pudo escribir el informe JSON.');
            $this->errores++;

            return null;
        }

        return $ruta;
    }

    /**
     * Compuerta de verificación: para cada fila del informe de un `--aplicar` comprueba que los archivos existen con el
     * sha256 correcto y que la celda de la base contiene la URL correspondiente. No modifica nada.
     *
     * @param  string  $ruta  Ruta del informe JSON.
     */
    private function verificar(string $ruta): int
    {
        $informe = is_file($ruta) ? json_decode((string) file_get_contents($ruta), true) : null;
        if (! is_array($informe) || ($informe['modo'] ?? null) !== 'aplicar' || ! isset($informe['filas'])) {
            return $this->fallo('El informe no existe o no es el de una ejecución --aplicar.');
        }
        try {
            $this->urlBase = $this->validarUrl((string) ($informe['contenido_url'] ?? $this->urlBase));
        } catch (RuntimeException $e) {
            return $this->fallo($e->getMessage());
        }
        $mal = 0;
        $hashes = [];
        $comprobadas = 0;
        foreach ($informe['filas'] as $f) {
            // El informe es un archivo local de confianza, pero aun así los identificadores deben tener forma de nombre.
            if (! is_string($f['tabla'] ?? null) || ! is_string($f['columna'] ?? null) || ! is_array($f['clave'] ?? null) || ! is_array($f['archivos'] ?? null)
                || preg_match('/^\w+$/', $f['tabla']) !== 1 || preg_match('/^\w+$/', $f['columna']) !== 1) {
                $mal++;

                continue;
            }
            $valor = $this->conn->table($f['tabla'])->where($f['clave'])->value($f['columna']);
            foreach ($f['archivos'] as $a) {
                $url = $this->urlBase.'/'.substr($a['sha256'], 0, 2).'/'.$a['sha256'].'.'.$a['ext'];
                if (preg_match('/^[0-9a-f]{64}$/', $a['sha256']) !== 1 || ! isset(ImagenesEmbebidas::TIPOS[$a['ext']])) {
                    $mal++;

                    continue;
                }
                if (! is_string($valor) || ! str_contains($valor, $url)) {
                    $mal++;
                    $this->line("Falta la URL {$a['sha256']} en {$f['tabla']}.{$f['columna']} ".json_encode($f['clave']));
                }
                if (! isset($hashes[$a['sha256']])) {
                    $archivo = $this->rutaArchivo($a['sha256'], $a['ext']);
                    $hashes[$a['sha256']] = is_file($archivo) && hash_file('sha256', $archivo) === $a['sha256'];
                    if (! $hashes[$a['sha256']]) {
                        $mal++;
                        $this->line("Archivo ausente o alterado: {$a['sha256']}.{$a['ext']}");
                    }
                }
                $comprobadas++;
            }
        }
        $this->line(count($informe['filas']).' fila(s), '.$comprobadas.' referencia(s), '.count($hashes).' archivo(s) distintos comprobados; problemas: '.$mal.'.');

        return $mal === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Formatea bytes como MB con un decimal.
     */
    private function mb(int|float $bytes): string
    {
        return number_format($bytes / 1048576, 1, ',', '.');
    }

    /**
     * Muestra rutas relativas a la raíz del proyecto (el informe y la salida no revelan la ruta del sistema).
     */
    private function rutaRelativa(string $ruta): string
    {
        $base = rtrim(realpath(base_path()) ?: base_path(), '/').'/';

        return str_starts_with($ruta, $base) ? substr($ruta, strlen($base)) : $ruta;
    }

    /**
     * Imprime un error y devuelve el código de salida de fallo.
     */
    private function fallo(string $mensaje): int
    {
        $this->error($mensaje);

        return Command::FAILURE;
    }
}
