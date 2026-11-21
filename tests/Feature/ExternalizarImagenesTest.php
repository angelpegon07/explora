<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fase 16A: pruebas del comando `explora:externalizar-imagenes` (imágenes base64 de las fichas -> archivos).
 *
 * SEGURIDAD DE LAS PRUEBAS (por qué no tocan datos de nadie):
 *  - Usan una conexión propia `migtest` hacia la base de sandbox `explora_migtest` (el usuario de la app tiene permisos
 *    sobre ella y es la única, además de `explora_test`, que la guarda del comando admite). NO se usa `explora_test`.
 *  - Crean tablas con nombre propio (`tmp_ext_img_*`) y las borran en `tearDown`; todas las ejecuciones del comando se
 *    limitan con `--tabla=` a ellas, así que aunque el sandbox contenga la copia clonada de `explora_test`, estas pruebas
 *    nunca la leen ni la modifican.
 *  - Los archivos se escriben en una carpeta propia `public/contenido-prueba-<id>/` (ignorada por git) que se borra al
 *    terminar, igual que los informes JSON que el comando deja en `storage/logs`.
 *  - La guarda de «base real» se prueba con una conexión hacia `information_schema` (solo lectura, sin tablas base): su
 *    nombre no acaba en `_test`/`_migtest`, así que la guarda actúa, y aunque se autorice no hay nada que escribir.
 *
 * Si la base `explora_migtest` no existe, las pruebas se omiten (créela con `CREATE DATABASE explora_migtest`; ver README).
 */
class ExternalizarImagenesTest extends TestCase
{
    private const TABLAS = ['tmp_ext_img_html', 'tmp_ext_img_blob', 'tmp_ext_img_comp'];

    /** Carpeta de destino de esta prueba (ruta absoluta dentro de public/). */
    private string $destino;

    /** @var array<int, string> Informes JSON existentes antes de la prueba (los nuevos se borran al final). */
    private array $informesPrevios = [];

    /** @var array<string, string> Binarios de imágenes diminutas válidas por extensión. */
    private array $bin = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.migtest' => array_merge(config('database.connections.mysql'), ['database' => 'explora_migtest'])]);
        // Para la guarda: una base cuyo nombre NO acaba en _test/_migtest (information_schema: solo lectura, sin tablas base).
        config(['database.connections.simulada_real' => array_merge(config('database.connections.mysql'), ['database' => 'information_schema'])]);

        try {
            DB::connection('migtest')->getPdo();
        } catch (\Throwable) {
            $this->markTestSkipped('No existe la base de sandbox explora_migtest (CREATE DATABASE explora_migtest).');
        }
        if (! function_exists('imagejpeg')) {
            $this->markTestSkipped('Se necesita la extensión GD para fabricar un JPEG válido.');
        }

        $this->informesPrevios = glob(storage_path('logs/externalizar-imagenes-*.json')) ?: [];
        $this->destino = public_path('contenido-prueba-'.bin2hex(random_bytes(4)));
        $this->bin = $this->imagenesDePrueba();
        $this->crearTablas();
    }

    protected function tearDown(): void
    {
        if (isset($this->destino)) {
            File::deleteDirectory($this->destino);
            foreach (glob(public_path('contenido-prueba-*-enlace')) ?: [] as $enlace) {
                @unlink($enlace);
            }
            foreach (array_diff(glob(storage_path('logs/externalizar-imagenes-*.json')) ?: [], $this->informesPrevios) as $informe) {
                @unlink($informe);
            }
            $schema = Schema::connection('migtest');
            foreach (self::TABLAS as $t) {
                $schema->dropIfExists($t);
            }
        }
        parent::tearDown();
    }

    // ------------------------------------------------------------------------------------------------ utilidades

    /**
     * Imágenes diminutas y válidas de los 4 formatos admitidos (+ un SVG como tipo no admitido).
     *
     * @return array<string, string> png, gif, jpg, webp, svg => bytes.
     */
    private function imagenesDePrueba(): array
    {
        $im = imagecreatetruecolor(2, 2);
        ob_start();
        imagejpeg($im);
        $jpg = (string) ob_get_clean();

        return [
            'png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='),
            'gif' => base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),
            'jpg' => $jpg,
            'webp' => base64_decode('UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA=='),
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><script>alert(1)</script></svg>',
        ];
    }

    /** Crea las tablas temporales de prueba en la base de sandbox. */
    private function crearTablas(): void
    {
        $c = DB::connection('migtest');
        foreach (self::TABLAS as $t) {
            $c->statement("DROP TABLE IF EXISTS `$t`");
        }
        $c->statement('CREATE TABLE tmp_ext_img_html (id INT AUTO_INCREMENT PRIMARY KEY, contenido LONGTEXT) DEFAULT CHARSET=utf8mb4');
        $c->statement('CREATE TABLE tmp_ext_img_blob (id INT AUTO_INCREMENT PRIMARY KEY, contenido LONGBLOB)');
        $c->statement('CREATE TABLE tmp_ext_img_comp (a INT NOT NULL, b INT NOT NULL, contenido TEXT, PRIMARY KEY (a, b)) DEFAULT CHARSET=utf8mb4');
    }

    /**
     * Etiqueta `<img>` con una imagen embebida.
     *
     * @param  string  $mimeDeclarado  MIME que se escribe en el `data:` (puede mentir, como en los datos reales).
     * @param  string  $bytes  Contenido binario de la imagen.
     */
    private function img(string $mimeDeclarado, string $bytes, string $atributos = 'class="x" alt="á"'): string
    {
        return '<img '.$atributos.' src="data:'.$mimeDeclarado.';base64,'.base64_encode($bytes).'" width="10">';
    }

    /** Inserta una fila en la tabla y devuelve su id. */
    private function insertar(string $tabla, string $contenido): int
    {
        return (int) DB::connection('migtest')->table($tabla)->insertGetId(['contenido' => $contenido]);
    }

    /** Lee el contenido de la fila `$id` de la tabla. */
    private function leer(string $tabla, int $id): string
    {
        return (string) DB::connection('migtest')->table($tabla)->where('id', $id)->value('contenido');
    }

    /** URL pública que debe quedar para unos bytes y una extensión. */
    private function url(string $bytes, string $ext): string
    {
        $h = hash('sha256', $bytes);

        return '/contenido/fichas/'.substr($h, 0, 2).'/'.$h.'.'.$ext;
    }

    /** Ruta en disco del archivo de unos bytes. */
    private function archivo(string $bytes, string $ext): string
    {
        $h = hash('sha256', $bytes);

        return $this->destino.'/'.substr($h, 0, 2).'/'.$h.'.'.$ext;
    }

    /** Cuenta los archivos de imagen escritos en el destino. */
    private function archivosEscritos(): int
    {
        return is_dir($this->destino) ? count(File::allFiles($this->destino)) : 0;
    }

    /**
     * Ejecuta el comando contra el sandbox, limitado a las tablas de prueba.
     *
     * @param  array<string, mixed>  $opciones  Opciones adicionales o que sustituyen a las de defecto.
     * @return array{0: int, 1: string} Código de salida y salida de texto.
     */
    private function ejecutar(array $opciones = []): array
    {
        $opciones = array_merge([
            '--conexion' => 'migtest',
            '--tabla' => implode(',', self::TABLAS),
            '--destino' => $this->destino,
        ], $opciones);
        $codigo = Artisan::call('explora:externalizar-imagenes', $opciones);

        return [$codigo, Artisan::output()];
    }

    /** Extrae de la salida la línea de totales («N fila(s), M imagen(es), X MB…») sin el verbo inicial. */
    private function cifras(string $salida): string
    {
        preg_match('/^(?:Aplicado|Simulado \(nada escrito\)|Revertido): (.*)$/m', $salida, $a);
        preg_match('/^Archivos a crear\/creados: (.*)$/m', $salida, $b);

        return ($a[1] ?? '?').' | '.($b[1] ?? '?');
    }

    // ------------------------------------------------------------------------------------------------ aplicar

    public function test_aplicar_externaliza_los_cuatro_formatos_y_solo_cambia_el_src(): void
    {
        $partes = [
            'p' => '<p>Texto con tildes: ñandú &amp; “comillas”</p>',
            'png' => $this->img('image/png', $this->bin['png']),
            // Como en los datos reales: un GIF declarado como PNG debe acabar como .gif (manda el tipo real).
            'gif' => $this->img('image/png', $this->bin['gif'], 'style="a:b" class=\'q\''),
            'jpg' => $this->img('image/jpeg', $this->bin['jpg']),
            'webp' => $this->img('image/webp', $this->bin['webp']),
        ];
        $original = implode("\n", $partes);
        $id = $this->insertar('tmp_ext_img_html', $original);

        [$codigo, $salida] = $this->ejecutar(['--aplicar' => true]);

        $this->assertSame(0, $codigo, $salida);
        $esperado = $original;
        foreach (['png', 'gif', 'jpg', 'webp'] as $ext) {
            $esperado = str_replace(
                'data:'.($ext === 'gif' ? 'image/png' : ($ext === 'jpg' ? 'image/jpeg' : 'image/'.$ext)).';base64,'.base64_encode($this->bin[$ext]),
                $this->url($this->bin[$ext], $ext),
                $esperado
            );
        }
        $this->assertSame($esperado, $this->leer('tmp_ext_img_html', $id), 'Solo cambia el valor de src; el resto del HTML queda byte a byte igual');
        $this->assertStringNotContainsString('data:image', $this->leer('tmp_ext_img_html', $id));
        $this->assertStringContainsString($this->url($this->bin['gif'], 'gif'), $this->leer('tmp_ext_img_html', $id));
        foreach (['png', 'gif', 'jpg', 'webp'] as $ext) {
            $this->assertFileExists($this->archivo($this->bin[$ext], $ext));
            $this->assertSame($this->bin[$ext], file_get_contents($this->archivo($this->bin[$ext], $ext)), "El archivo .$ext tiene los mismos bytes que el original");
        }
        $this->assertSame(4, $this->archivosEscritos());
    }

    public function test_las_imagenes_repetidas_comparten_archivo(): void
    {
        $a = $this->insertar('tmp_ext_img_html', $this->img('image/png', $this->bin['png']).$this->img('image/png', $this->bin['png']));
        $b = $this->insertar('tmp_ext_img_html', '<p>'.$this->img('image/png', $this->bin['png']).'</p>');

        [$codigo, $salida] = $this->ejecutar(['--aplicar' => true]);

        $this->assertSame(0, $codigo, $salida);
        $this->assertSame(1, $this->archivosEscritos(), 'Un único archivo para tres apariciones');
        $this->assertSame(2, substr_count($this->leer('tmp_ext_img_html', $a), $this->url($this->bin['png'], 'png')));
        $this->assertStringContainsString($this->url($this->bin['png'], 'png'), $this->leer('tmp_ext_img_html', $b));
        $this->assertStringContainsString('duplicadas dentro de la ejecución: 2', $salida);
    }

    public function test_el_base64_invalido_o_truncado_deja_la_fila_intacta_y_se_reporta(): void
    {
        $valido = $this->img('image/png', $this->bin['png']);
        $truncado = '<img src="data:image/png;base64,'.substr(base64_encode($this->bin['png']), 0, 21).'">'; // largo no múltiplo de 4
        $caracteres = '<img src="data:image/png;base64,AAAA!!!!AAAA">';
        $relleno = '<img src="data:image/png;base64,AA=A">';       // relleno en medio
        $vacio = '<img src="data:image/png;base64,">';
        $ids = [];
        foreach ([$truncado, $caracteres, $relleno, $vacio] as $malo) {
            // Cada fila lleva además una imagen válida: tampoco debe cambiar (la fila se omite entera).
            $ids[] = [$this->insertar('tmp_ext_img_html', $valido.$malo), $valido.$malo];
        }

        [$codigo, $salida] = $this->ejecutar(['--aplicar' => true]);

        $this->assertSame(0, $codigo, $salida);
        foreach ($ids as [$id, $esperado]) {
            $this->assertSame($esperado, $this->leer('tmp_ext_img_html', $id), 'La fila con base64 inválido no se toca');
        }
        $this->assertSame(0, $this->archivosEscritos(), 'No se escribe ningún archivo de una fila omitida');
        $this->assertStringContainsString('base64_invalido', $salida);
        $this->assertStringContainsString('Filas omitidas por anomalía: 4', $salida);
    }

    public function test_svg_y_otros_tipos_se_dejan_intactos_y_se_reportan(): void
    {
        $svg = $this->img('image/svg+xml', $this->bin['svg']);
        $bmp = $this->img('image/bmp', 'BM'.str_repeat("\0", 30));
        $falsoPng = $this->img('image/png', 'esto no es una imagen');
        $solo = $this->insertar('tmp_ext_img_html', $svg.$bmp.$falsoPng);
        $mixta = $this->insertar('tmp_ext_img_html', $svg.$this->img('image/png', $this->bin['png']));

        [$codigo, $salida] = $this->ejecutar(['--aplicar' => true]);

        $this->assertSame(0, $codigo, $salida);
        $this->assertSame($svg.$bmp.$falsoPng, $this->leer('tmp_ext_img_html', $solo));
        $this->assertSame(
            $svg.str_replace('data:image/png;base64,'.base64_encode($this->bin['png']), $this->url($this->bin['png'], 'png'), $this->img('image/png', $this->bin['png'])),
            $this->leer('tmp_ext_img_html', $mixta),
            'En una fila mixta solo se convierte la imagen admitida'
        );
        $this->assertSame(1, $this->archivosEscritos(), 'Solo el PNG válido se escribe como archivo');
        $this->assertStringContainsString('tipo_no_soportado', $salida);
        $this->assertFileDoesNotExist($this->destino.'/svg');
    }

    public function test_varias_imagenes_por_fila_y_columna_longblob(): void
    {
        $varias = '<ul><li>'.$this->img('image/png', $this->bin['png']).'</li><li>'.$this->img('image/png', $this->bin['gif']).'</li><li>'.$this->img('image/jpeg', $this->bin['jpg']).'</li></ul>';
        $idHtml = $this->insertar('tmp_ext_img_html', $varias);
        $idBlob = $this->insertar('tmp_ext_img_blob', $varias);

        [$codigo, $salida] = $this->ejecutar(['--aplicar' => true]);

        $this->assertSame(0, $codigo, $salida);
        $this->assertSame(3, $this->archivosEscritos());
        foreach (['tmp_ext_img_html' => $idHtml, 'tmp_ext_img_blob' => $idBlob] as $tabla => $id) {
            $valor = $this->leer($tabla, $id);
            $this->assertStringNotContainsString('data:image', $valor, $tabla);
            $this->assertSame(3, substr_count($valor, '/contenido/fichas/'), $tabla);
        }
    }

    public function test_las_filas_sin_imagenes_no_se_tocan_y_la_clave_compuesta_funciona(): void
    {
        $sinImagen = '<p>Hola</p><img src="/images/logo.png"><img data-src="data:image/png;base64,AAAA"> data:image/png;base64,AAAA';
        $id = $this->insertar('tmp_ext_img_html', $sinImagen);
        DB::connection('migtest')->table('tmp_ext_img_comp')->insert([
            ['a' => 1, 'b' => 2, 'contenido' => $this->img('image/png', $this->bin['png'])],
            ['a' => 1, 'b' => 3, 'contenido' => 'sin imágenes'],
        ]);

        [$codigo, $salida] = $this->ejecutar(['--aplicar' => true]);

        $this->assertSame(0, $codigo, $salida);
        $this->assertSame($sinImagen, $this->leer('tmp_ext_img_html', $id), '`data-src`, texto suelto e imágenes normales no se tocan');
        $this->assertStringContainsString('contexto_no_reconocido', $salida);
        $this->assertSame($this->url($this->bin['png'], 'png'), preg_replace('/^.*src="([^"]+)".*$/', '$1', (string) DB::connection('migtest')->table('tmp_ext_img_comp')->where(['a' => 1, 'b' => 2])->value('contenido')));
        $this->assertSame('sin imágenes', DB::connection('migtest')->table('tmp_ext_img_comp')->where(['a' => 1, 'b' => 3])->value('contenido'));
    }

    // ------------------------------------------------------------------------------------------------ dry-run

    public function test_dry_run_no_escribe_nada_y_sus_cifras_coinciden_con_aplicar(): void
    {
        $original = $this->img('image/png', $this->bin['gif']).$this->img('image/png', $this->bin['png']).$this->img('image/png', $this->bin['png']).$this->img('image/svg+xml', $this->bin['svg']);
        $id = $this->insertar('tmp_ext_img_html', $original);
        $idBlob = $this->insertar('tmp_ext_img_blob', $this->img('image/webp', $this->bin['webp']));

        // Sin opciones el modo es dry-run; con --dry-run explícito es lo mismo.
        [$c1, $simulado] = $this->ejecutar();
        [$c2, $simulado2] = $this->ejecutar(['--dry-run' => true]);

        $this->assertSame(0, $c1);
        $this->assertSame(0, $c2);
        $this->assertDirectoryDoesNotExist($this->destino, 'dry-run no crea ni la carpeta de destino');
        $this->assertSame($original, $this->leer('tmp_ext_img_html', $id));
        $this->assertStringContainsString('data:image/webp', $this->leer('tmp_ext_img_blob', $idBlob));
        $this->assertSame(array_values(array_diff(glob(storage_path('logs/externalizar-imagenes-*.json')) ?: [], $this->informesPrevios)), [], 'dry-run tampoco escribe informe');

        [$c3, $aplicado] = $this->ejecutar(['--aplicar' => true]);
        $this->assertSame(0, $c3, $aplicado);
        $this->assertSame($this->cifras($simulado), $this->cifras($aplicado), 'Las cifras del dry-run son las de la aplicación real');
        $this->assertSame($this->cifras($simulado), $this->cifras($simulado2));
        $this->assertStringContainsString('4 imagen(es)', $aplicado);
        $this->assertSame(3, $this->archivosEscritos());
    }

    public function test_la_segunda_ejecucion_no_cambia_nada(): void
    {
        $id = $this->insertar('tmp_ext_img_html', $this->img('image/png', $this->bin['png']).$this->img('image/png', $this->bin['jpg']));
        $this->ejecutar(['--aplicar' => true]);
        $tras1 = $this->leer('tmp_ext_img_html', $id);
        $archivos = $this->archivosEscritos();
        $rutas = array_map(fn ($f) => $f->getPathname(), File::allFiles($this->destino));
        $tiempos = array_map('filemtime', $rutas);

        [$codigo, $salida] = $this->ejecutar(['--aplicar' => true]);

        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('0 fila(s), 0 imagen(es)', $salida);
        $this->assertSame($tras1, $this->leer('tmp_ext_img_html', $id));
        $this->assertSame($archivos, $this->archivosEscritos());
        $this->assertSame($tiempos, array_map('filemtime', $rutas), 'Los archivos existentes no se reescriben');
    }

    // ------------------------------------------------------------------------------------------------ revertir

    public function test_revertir_devuelve_imagenes_funcionalmente_equivalentes(): void
    {
        $pngIgual = $this->img('image/png', $this->bin['png']);   // MIME declarado = real: vuelve idéntica byte a byte
        $gifMiente = $this->img('image/png', $this->bin['gif']);  // declarado png, real gif: vuelve como image/gif
        $original = '<p>Antes</p>'.$pngIgual.'<b>medio</b>'.$gifMiente.'<i>fin</i>';
        $id = $this->insertar('tmp_ext_img_html', $original);
        $idBlob = $this->insertar('tmp_ext_img_blob', $original);
        $this->ejecutar(['--aplicar' => true]);
        $this->assertStringNotContainsString('data:image', $this->leer('tmp_ext_img_html', $id));

        [$codigo, $salida] = $this->ejecutar(['--revertir' => true]);

        $this->assertSame(0, $codigo, $salida);
        $revertido = $this->leer('tmp_ext_img_html', $id);
        $this->assertSame($this->leer('tmp_ext_img_blob', $idBlob), $revertido, 'Igual en columna de texto y LONGBLOB');
        $this->assertStringNotContainsString('/contenido/fichas/', $revertido);
        // Funcionalmente equivalente: mismo HTML salvo el MIME declarado, y los mismos bytes al decodificar.
        $this->assertSame(str_replace('data:image/png;base64,'.base64_encode($this->bin['gif']), 'data:image/gif;base64,'.base64_encode($this->bin['gif']), $original), $revertido);
        preg_match_all('/src="data:image\/[a-z]+;base64,([^"]+)"/', $revertido, $m);
        $this->assertSame([$this->bin['png'], $this->bin['gif']], array_map('base64_decode', $m[1]));
        $this->assertStringContainsString($pngIgual, $revertido);
    }

    public function test_revertir_no_toca_la_fila_si_falta_un_archivo(): void
    {
        $id = $this->insertar('tmp_ext_img_html', $this->img('image/png', $this->bin['png']).$this->img('image/png', $this->bin['jpg']));
        $this->ejecutar(['--aplicar' => true]);
        $aplicado = $this->leer('tmp_ext_img_html', $id);
        unlink($this->archivo($this->bin['jpg'], 'jpg'));

        [$codigo, $salida] = $this->ejecutar(['--revertir' => true]);

        $this->assertSame(0, $codigo);
        $this->assertSame($aplicado, $this->leer('tmp_ext_img_html', $id), 'Sin el archivo no se revierte nada de esa fila');
        $this->assertStringContainsString('archivo_faltante', $salida);
    }

    // ------------------------------------------------------------------------------------------------ guardas

    public function test_aplicar_y_revertir_se_niegan_en_una_base_que_no_es_de_prueba(): void
    {
        foreach (['--aplicar', '--revertir'] as $modo) {
            foreach ([[], ['--base-real' => true], ['--confirmo-respaldo' => true]] as $banderas) {
                [$codigo, $salida] = $this->ejecutar(['--conexion' => 'simulada_real', $modo => true, '--tabla' => 'x'] + $banderas);
                $this->assertSame(1, $codigo, "$modo con ".json_encode(array_keys($banderas)));
                $this->assertStringContainsString('no termina en _test ni _migtest', $salida);
            }
        }
        $this->assertDirectoryDoesNotExist($this->destino);
    }

    public function test_con_las_dos_banderas_se_permite_en_una_base_no_de_prueba(): void
    {
        [$codigo, $salida] = $this->ejecutar(['--conexion' => 'simulada_real', '--aplicar' => true, '--base-real' => true, '--confirmo-respaldo' => true, '--tabla' => 'x']);

        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('ATENCIÓN', $salida);
        $this->assertStringContainsString('0 fila(s)', $salida);
        // dry-run nunca exige banderas (solo lee).
        [$codigo2] = $this->ejecutar(['--conexion' => 'simulada_real', '--tabla' => 'x']);
        $this->assertSame(0, $codigo2);
    }

    public function test_los_modos_son_excluyentes(): void
    {
        [$codigo, $salida] = $this->ejecutar(['--aplicar' => true, '--revertir' => true]);
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('excluyentes', $salida);
        [$codigo] = $this->ejecutar(['--aplicar' => true, '--dry-run' => true]);
        $this->assertSame(1, $codigo);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function destinosInvalidos(): array
    {
        return [
            'con ..' => ['public/../fuera'],
            'con .. en medio' => ['public/contenido/../../x'],
            'absoluto fuera' => ['/tmp/explora-fuera'],
            'relativo fuera de public' => ['storage/app/imagenes'],
            'public entero' => ['public'],
            'public absoluto' => ['@PUBLIC@'],
            'vacío' => [''],
            'byte nulo' => ["public/x\0y"],
        ];
    }

    /**
     * @dataProvider destinosInvalidos
     */
    public function test_destinos_fuera_de_public_se_rechazan(string $destino): void
    {
        $destino = str_replace('@PUBLIC@', public_path(), $destino);

        [$codigo, $salida] = $this->ejecutar(['--aplicar' => true, '--destino' => $destino]);

        $this->assertSame(1, $codigo, $salida);
        $this->assertMatchesRegularExpression('/destino/i', $salida);
        $this->assertDirectoryDoesNotExist(base_path('fuera'));
        $this->assertDirectoryDoesNotExist('/tmp/explora-fuera');
    }

    public function test_un_enlace_simbolico_que_sale_de_public_se_rechaza(): void
    {
        $fuera = sys_get_temp_dir().'/explora-fuera-'.bin2hex(random_bytes(3));
        mkdir($fuera);
        $enlace = public_path('contenido-prueba-'.bin2hex(random_bytes(3)).'-enlace');
        symlink($fuera, $enlace);
        try {
            [$codigo, $salida] = $this->ejecutar(['--aplicar' => true, '--destino' => $enlace.'/sub']);
            $this->assertSame(1, $codigo, $salida);
            $this->assertSame([], glob($fuera.'/*') ?: [], 'No se escribió nada fuera de public/');
        } finally {
            @unlink($enlace);
            @rmdir($fuera);
        }
    }

    public function test_si_falla_la_escritura_del_archivo_la_fila_no_cambia(): void
    {
        $original = $this->img('image/png', $this->bin['png']);
        $id = $this->insertar('tmp_ext_img_html', $original);
        // Un ARCHIVO donde debería ir la subcarpeta `<2 hex>`: mkdir falla y la transacción de la fila se deshace.
        $h = hash('sha256', $this->bin['png']);
        mkdir($this->destino, 0755, true);
        file_put_contents($this->destino.'/'.substr($h, 0, 2), 'obstáculo');

        [$codigo, $salida] = $this->ejecutar(['--aplicar' => true]);

        $this->assertSame(1, $codigo, $salida);
        $this->assertSame($original, $this->leer('tmp_ext_img_html', $id));
        $this->assertStringContainsString('1 fila(s) con error', $salida);
    }

    public function test_las_columnas_excluidas_por_configuracion_no_se_recorren(): void
    {
        config(['explora.externalizar_excluir' => ['tmp_ext_img_html.*', 'tmp_ext_img_blob.contenido']]);
        $original = $this->img('image/png', $this->bin['png']);
        $a = $this->insertar('tmp_ext_img_html', $original);
        $b = $this->insertar('tmp_ext_img_blob', $original);
        $c = DB::connection('migtest')->table('tmp_ext_img_comp')->insert(['a' => 5, 'b' => 5, 'contenido' => $original]);

        [$codigo, $salida] = $this->ejecutar(['--aplicar' => true]);

        $this->assertSame(0, $codigo, $salida);
        $this->assertSame($original, $this->leer('tmp_ext_img_html', $a));
        $this->assertSame($original, $this->leer('tmp_ext_img_blob', $b));
        $this->assertStringNotContainsString('data:image', (string) DB::connection('migtest')->table('tmp_ext_img_comp')->value('contenido'));
        $this->assertTrue($c);
    }

    public function test_limite_y_filtros_de_columna(): void
    {
        $ids = [];
        foreach (range(1, 3) as $i) {
            $ids[] = $this->insertar('tmp_ext_img_html', $this->img('image/png', $this->bin['png']));
        }

        [$codigo, $salida] = $this->ejecutar(['--aplicar' => true, '--limite' => 2, '--columna' => 'contenido']);

        $this->assertSame(0, $codigo, $salida);
        $this->assertStringContainsString('2 fila(s)', $salida);
        $this->assertStringNotContainsString('data:image', $this->leer('tmp_ext_img_html', $ids[0]));
        $this->assertStringContainsString('data:image', $this->leer('tmp_ext_img_html', $ids[2]), 'La tercera fila queda fuera del límite');

        [, $salida] = $this->ejecutar(['--columna' => 'no_existe']);
        $this->assertStringContainsString('0 columna(s)', $salida);
    }

    // ------------------------------------------------------------------------------------------------ informe

    public function test_el_informe_json_sirve_de_inventario_y_la_verificacion_lo_comprueba(): void
    {
        $original = $this->img('image/png', $this->bin['png']).$this->img('image/png', $this->bin['gif']);
        $id = $this->insertar('tmp_ext_img_html', $original);
        [$codigo, $salida] = $this->ejecutar(['--aplicar' => true]);
        $this->assertSame(0, $codigo, $salida);

        $informes = array_values(array_diff(glob(storage_path('logs/externalizar-imagenes-*.json')) ?: [], $this->informesPrevios));
        $this->assertCount(1, $informes);
        $json = (string) file_get_contents($informes[0]);
        $datos = json_decode($json, true);
        $this->assertSame('aplicar', $datos['modo']);
        $this->assertSame('explora_migtest', $datos['base_datos']);
        $this->assertSame(1, $datos['totales']['filas']);
        $this->assertSame(2, $datos['totales']['imagenes']);
        $fila = $datos['filas'][0];
        $this->assertSame(['tabla' => 'tmp_ext_img_html', 'columna' => 'contenido', 'clave' => ['id' => $id], 'imagenes' => 2], array_intersect_key($fila, array_flip(['tabla', 'columna', 'clave', 'imagenes'])));
        $this->assertSame([hash('sha256', $this->bin['png']), hash('sha256', $this->bin['gif'])], array_column($fila['archivos'], 'sha256'));
        $this->assertSame(['png', 'gif'], array_column($fila['archivos'], 'ext'));
        $this->assertStringNotContainsString(base64_encode($this->bin['png']), $json, 'El informe no incluye el contenido');

        [$ok] = $this->ejecutar(['--verificar' => $informes[0]]);
        $this->assertSame(0, $ok);

        unlink($this->archivo($this->bin['gif'], 'gif'));
        [$mal, $salidaMal] = $this->ejecutar(['--verificar' => $informes[0]]);
        $this->assertSame(1, $mal);
        $this->assertStringContainsString('Archivo ausente o alterado', $salidaMal);
    }
}
