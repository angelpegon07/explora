<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 16C: la carpeta `deploy/` (plantillas y scripts de despliegue) y su coherencia con `GUIA_DESPLIEGUE.md`.
 *
 * Son pruebas de ARCHIVOS: no tocan ninguna base de datos ni ningún servidor. Vigilan lo que el ensayo de despliegue
 * enseñó a no romper: que los scripts existan, sean ejecutables y tengan sintaxis válida; que no se cuelen secretos ni rutas
 * de un equipo; que la plantilla del `.env` defina todas las variables que la guía nombra; que lo que la guía cita
 * (opciones de los scripts, variables de entorno) exista de verdad en ellos; y que las plantillas de Apache/PHP-FPM no
 * vuelvan a los dos defectos que se encontraron (TLS activado en el puerto 80, `memory_limit` fijado por el administrador).
 * Los scripts solo se EJECUTAN en sus rutas de rechazo (sin credenciales, argumentos inválidos, servidor caído), que no
 * hacen nada: los pasos que cambian una base se ejercitaron a mano en el ensayo, nunca desde la suite.
 */
class DespliegueArchivosTest extends TestCase
{
    private const SCRIPTS = ['respaldar.sh', 'verificar.sh', 'desplegar.sh'];

    private const PLANTILLAS = [
        'env.produccion.ejemplo', 'apache-vhost.conf.ejemplo', 'php-fpm-pool.conf.ejemplo', 'php.ini.ejemplo',
    ];

    /** Ruta absoluta de un archivo de `deploy/`. */
    private function deploy(string $nombre): string
    {
        return base_path('deploy/'.$nombre);
    }

    /** Contenido de un archivo de `deploy/` (falla la prueba si no existe). */
    private function leer(string $nombre): string
    {
        $this->assertFileExists($this->deploy($nombre));

        return (string) file_get_contents($this->deploy($nombre));
    }

    /** Contenido de la guía de despliegue. */
    private function guia(): string
    {
        $ruta = base_path('DOCUMENTACION_MEJORAMIENTO/GUIA_DESPLIEGUE.md');
        $this->assertFileExists($ruta);

        return (string) file_get_contents($ruta);
    }

    /**
     * Ejecuta un script con un entorno MÍNIMO (sin las variables del proceso de pruebas, para que no hereden credenciales).
     * `MYSQLDUMP` apunta a un binario cualquiera: respaldar.sh lo busca antes de mirar las credenciales y en el equipo de pruebas
     * puede no haber cliente de MySQL en el PATH; las pruebas solo recorren rutas de rechazo, donde nunca se ejecuta.
     *
     * @param  list<string>  $argumentos  Script y sus argumentos.
     * @return array{0:int,1:string} Código de salida y salida combinada (stdout + stderr).
     */
    private function ejecutar(array $argumentos, ?string $cwd = null): array
    {
        $p = new Process(['bash', ...$argumentos], $cwd ?? base_path(), ['PATH' => (string) getenv('PATH'), 'HOME' => sys_get_temp_dir(), 'MYSQLDUMP' => PHP_BINARY]);
        $p->setTimeout(60);
        $p->run();

        return [(int) $p->getExitCode(), $p->getOutput().$p->getErrorOutput()];
    }

    public function test_los_scripts_y_las_plantillas_existen(): void
    {
        foreach ([...self::SCRIPTS, ...self::PLANTILLAS] as $archivo) {
            $this->assertFileExists($this->deploy($archivo), "falta deploy/$archivo");
        }
    }

    public function test_los_scripts_son_ejecutables_y_pasan_bash_n(): void
    {
        foreach (self::SCRIPTS as $script) {
            $ruta = $this->deploy($script);
            $this->assertTrue(is_executable($ruta), "deploy/$script no es ejecutable (chmod +x)");
            $this->assertStringStartsWith('#!/usr/bin/env bash', $this->leer($script), "deploy/$script: falta el shebang");
            $this->assertStringContainsString("set -euo pipefail\n", $this->leer($script), "deploy/$script: debe usar set -euo pipefail");

            [$codigo, $salida] = $this->ejecutar(['-n', $ruta]);
            $this->assertSame(0, $codigo, "bash -n deploy/$script: $salida");
        }
    }

    public function test_no_hay_secretos_ni_rutas_de_un_equipo_en_deploy(): void
    {
        foreach ([...self::SCRIPTS, ...self::PLANTILLAS] as $archivo) {
            $texto = $this->leer($archivo);

            // Rutas del equipo de quien lo escribió (macOS/Linux de usuario) y directorios de trabajo temporales del ensayo.
            $this->assertDoesNotMatchRegularExpression('#/Users/|/home/[a-z]|scratchpad|/private/tmp|/var/folders#i', $texto, "deploy/$archivo contiene una ruta de un equipo concreto");
            // Claves de aplicación, tokens y contraseñas en la línea de comandos (se verían con `ps`).
            $this->assertDoesNotMatchRegularExpression('#base64:[A-Za-z0-9+/=]{20,}#', $texto, "deploy/$archivo contiene una APP_KEY");
            $this->assertDoesNotMatchRegularExpression('#--password=|mysql(?:dump)?\s[^\n]*\s-p\S+#i', $texto, "deploy/$archivo pasa una contraseña por la línea de comandos");
            $this->assertDoesNotMatchRegularExpression('#(AKIA[0-9A-Z]{16}|ghp_[A-Za-z0-9]{20,}|-----BEGIN [A-Z ]*PRIVATE KEY-----)#', $texto, "deploy/$archivo parece contener un secreto");
        }
    }

    public function test_el_env_de_ejemplo_no_trae_valores_reales(): void
    {
        $env = $this->leer('env.produccion.ejemplo');
        $this->assertMatchesRegularExpression('/^APP_KEY=$/m', $env, 'APP_KEY debe ir vacía (se genera con key:generate en el servidor)');
        $this->assertMatchesRegularExpression('/^APP_ENV=production$/m', $env);
        $this->assertMatchesRegularExpression('/^APP_DEBUG=false$/m', $env);
        $this->assertMatchesRegularExpression('/^SESSION_SECURE_COOKIE=true$/m', $env, 'producción es con HTTPS: la cookie debe ser Secure');

        // Toda credencial es un marcador «CAMBIAR…» o está vacía.
        preg_match_all('/^([A-Z_]*(?:PASSWORD|USERNAME|PASS|USER))=(.*)$/m', $env, $m, PREG_SET_ORDER);
        $this->assertNotEmpty($m, 'la plantilla debe traer las variables de credenciales');
        foreach ($m as [, $nombre, $valor]) {
            $this->assertTrue($valor === '' || str_starts_with($valor, 'CAMBIAR'), "$nombre debe estar vacía o ser un marcador CAMBIAR…, no un valor");
        }
    }

    public function test_el_env_de_ejemplo_define_las_variables_que_nombra_la_guia(): void
    {
        $env = $this->leer('env.produccion.ejemplo');
        $definidas = [];
        preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', $env, $d);
        foreach ($d[1] as $nombre) {
            $definidas[$nombre] = true;
        }

        // La sección «.env de producción, comentado» de la guía: la primera celda de cada fila de la tabla nombra variables.
        $guia = $this->guia();
        $this->assertSame(1, preg_match('/### `\.env` de producción, comentado(.*?)> \*\*Regla de oro/s', $guia, $seccion), 'no encuentro la sección del .env en la guía');
        $citadas = [];
        foreach (explode("\n", $seccion[1]) as $linea) {
            if (! str_starts_with($linea, '| `')) {
                continue;
            }
            $primeraCelda = explode('|', $linea)[1];
            preg_match_all('/`([A-Z][A-Z0-9_]*\*?)`/', $primeraCelda, $c);
            array_push($citadas, ...$c[1]);
        }
        $this->assertNotEmpty($citadas, 'la guía debe nombrar variables en su tabla del .env');

        foreach ($citadas as $nombre) {
            if (str_ends_with($nombre, '*')) {
                // Comodín (`DB_*`): basta con que exista alguna variable con ese prefijo.
                $prefijo = rtrim($nombre, '*');
                $this->assertNotEmpty(array_filter(array_keys($definidas), fn ($v) => str_starts_with($v, $prefijo)), "la plantilla no define ninguna $nombre");

                continue;
            }
            $this->assertArrayHasKey($nombre, $definidas, "la guía menciona $nombre pero deploy/env.produccion.ejemplo no lo define");
        }

        // Y cada ajuste propio de Explora que lee config/explora.php debe estar en la plantilla.
        preg_match_all("/env\('([A-Z_]+)'/", (string) file_get_contents(config_path('explora.php')), $propias);
        foreach ($propias[1] as $nombre) {
            $this->assertArrayHasKey($nombre, $definidas, "config/explora.php lee $nombre y la plantilla no lo define");
        }
    }

    public function test_lo_que_la_guia_cita_de_los_scripts_existe_en_ellos(): void
    {
        $guia = $this->guia();

        // Los archivos de deploy/ que la guía nombra existen.
        preg_match_all('#deploy/([A-Za-z0-9._-]+)#', $guia, $archivos);
        foreach (array_unique($archivos[1]) as $archivo) {
            $this->assertFileExists($this->deploy($archivo), "la guía cita deploy/$archivo y no existe");
        }

        // Las opciones `--xxx` de las líneas que nombran un script están definidas en ese script.
        foreach (self::SCRIPTS as $script) {
            $fuente = $this->leer($script);
            foreach (explode("\n", $guia) as $linea) {
                if (! str_contains($linea, $script)) {
                    continue;
                }
                preg_match_all('/(?<![\w-])--([a-z][a-z-]+)/', $linea, $opciones);
                foreach ($opciones[1] as $opcion) {
                    // Opciones de otros programas que pueden aparecer en la misma línea de la guía.
                    if (in_array($opcion, ['insecure', 'single-transaction', 'path', 'force', 'no-ansi', 'step', 'json'], true) && ! str_contains($fuente, "--$opcion")) {
                        continue;
                    }
                    $this->assertStringContainsString("--$opcion", $fuente, "la guía cita «--{$opcion}» con {$script} y el script no la define");
                }
            }
        }

        // Variables de entorno que la guía enseña a usar con los scripts.
        $this->assertStringContainsString('MYSQL_DEFAULTS_FILE', $this->leer('respaldar.sh'));
        $this->assertStringContainsString('DDL_DB_USERNAME', $this->leer('desplegar.sh'));
        $this->assertStringContainsString('DDL_DB_PASSWORD', $this->leer('desplegar.sh'));
        $this->assertStringContainsString('MYSQL_DEFAULTS_FILE', $guia);
        $this->assertStringContainsString('DDL_DB_USERNAME', $guia);
    }

    public function test_respaldar_rechaza_la_ejecucion_sin_credenciales_o_con_argumentos_peligrosos(): void
    {
        $dir = sys_get_temp_dir().'/explora-respaldo-'.bin2hex(random_bytes(4));
        try {
            [$codigo, $salida] = $this->ejecutar([$this->deploy('respaldar.sh'), '--base', 'explora', '--destino', $dir]);
            $this->assertSame(1, $codigo, $salida);
            $this->assertStringContainsString('No se aceptan contraseñas por argumento', $salida);

            [$codigo, $salida] = $this->ejecutar([$this->deploy('respaldar.sh'), '--base', 'x;ls', '--destino', $dir]);
            $this->assertSame(1, $codigo, $salida);
            $this->assertStringContainsString('letras, números y guion bajo', $salida);

            [$codigo, $salida] = $this->ejecutar([$this->deploy('respaldar.sh'), '--base', 'explora', '--password', 'x']);
            $this->assertSame(1, $codigo, $salida);
            $this->assertStringContainsString('opción desconocida', $salida);
        } finally {
            @rmdir($dir);
        }
    }

    public function test_verificar_sale_con_2_si_no_hay_servidor(): void
    {
        // Puerto 9 («discard»): nadie escucha en él; la comprobación inicial debe fallar sin hacer más peticiones.
        [$codigo, $salida] = $this->ejecutar([$this->deploy('verificar.sh'), 'http://127.0.0.1:9', '--sin-color']);
        $this->assertSame(2, $codigo, $salida);
        $this->assertStringContainsString('no puedo conectar', $salida);

        [$codigo, $salida] = $this->ejecutar([$this->deploy('verificar.sh'), '--no-existe']);
        $this->assertSame(2, $codigo, $salida);
    }

    public function test_desplegar_en_simulacion_no_toca_una_carpeta_que_no_es_explora(): void
    {
        $dir = sys_get_temp_dir().'/explora-despliegue-'.bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            [$codigo, $salida] = $this->ejecutar([$this->deploy('desplegar.sh'), '--raiz', $dir]);
            $this->assertSame(1, $codigo, $salida);
            $this->assertStringContainsString('Modo SIMULAR', $salida);
            $this->assertStringContainsString('no parece la raíz de Explora', $salida);
            $this->assertSame([], array_values(array_diff(scandir($dir) ?: [], ['.', '..'])), 'la simulación no debe crear nada');

            // Las pasos destructivos sin --aplicar solo se muestran, y sin respaldo se niegan incluso con --aplicar.
            [$codigo, $salida] = $this->ejecutar([$this->deploy('desplegar.sh'), '--raiz', $dir, '--aplicar', '--migrar']);
            $this->assertSame(1, $codigo, $salida);
            $this->assertSame([], array_values(array_diff(scandir($dir) ?: [], ['.', '..'])));

            [$codigo, $salida] = $this->ejecutar([$this->deploy('desplegar.sh'), '--opcion-inventada']);
            $this->assertSame(1, $codigo, $salida);
            $this->assertStringContainsString('opción desconocida', $salida);
        } finally {
            @rmdir($dir);
        }
    }

    public function test_la_ayuda_de_los_scripts_documenta_las_banderas_destructivas(): void
    {
        [$codigo, $ayuda] = $this->ejecutar([$this->deploy('desplegar.sh'), '--help']);
        $this->assertSame(0, $codigo, $ayuda);
        foreach (['--simular', '--aplicar', '--migrar', '--externalizar', '--respaldo', '--grupo-web'] as $opcion) {
            $this->assertStringContainsString($opcion, $ayuda, "la ayuda de desplegar.sh no explica $opcion");
        }

        [$codigo, $ayuda] = $this->ejecutar([$this->deploy('respaldar.sh'), '--help']);
        $this->assertSame(0, $codigo, $ayuda);
        $this->assertStringContainsString('--con-contenido', $ayuda);
    }

    public function test_el_vhost_de_ejemplo_no_activa_tls_en_el_puerto_80_ni_sirve_ht(): void
    {
        $vhost = $this->leer('apache-vhost.conf.ejemplo');

        // Defecto del ensayo: un vhost «*:80 *:443» con SSLEngine on activaba TLS también en el 80.
        $this->assertDoesNotMatchRegularExpression('/^\s*<VirtualHost[^>]*:80[^>]*:443|<VirtualHost[^>]*:443[^>]*:80/m', $vhost, 'no mezcle los puertos 80 y 443 en un mismo VirtualHost');
        preg_match_all('#<VirtualHost \*:80>(.*?)</VirtualHost>#s', $vhost, $http);
        $this->assertGreaterThanOrEqual(2, count($http[1]), 'debe haber un vhost por defecto y uno de redirección en el 80');
        foreach ($http[1] as $bloque) {
            $this->assertDoesNotMatchRegularExpression('/^\s*SSLEngine\s+on/mi', $bloque, 'SSLEngine on dentro de un vhost del puerto 80');
        }

        $this->assertMatchesRegularExpression('/<FilesMatch "\^\\\\\.ht">\s*Require all denied/', $vhost, 'debe negar .htaccess/.htpasswd');
        $this->assertStringContainsString('<If "-f %{REQUEST_FILENAME}">', $vhost, 'el manejador de PHP debe aplicarse solo a archivos .php que existen');
        $this->assertStringContainsString('AllowOverride All', $vhost);
        $this->assertStringContainsString('ServerTokens Prod', $vhost);
        $this->assertStringContainsString('TraceEnable off', $vhost);
        // El DocumentRoot es public/.
        $this->assertMatchesRegularExpression('#DocumentRoot "[^"]+/public"#', $vhost);
    }

    public function test_el_pool_de_fpm_y_el_php_ini_respetan_lo_que_el_ensayo_comprobo(): void
    {
        $pool = $this->leer('php-fpm-pool.conf.ejemplo');
        // Sin comentar: `memory_limit` fijado por el administrador impediría el ini_set de FICHAS_MEMORIA.
        $this->assertDoesNotMatchRegularExpression('/^\s*php_admin_value\[memory_limit\]/m', $pool);
        $this->assertMatchesRegularExpression('/^listen = 127\.0\.0\.1:\d+$/m', $pool, 'FPM solo debe escuchar en la máquina local');
        $this->assertMatchesRegularExpression('/^php_admin_flag\[display_errors\] = off$/m', $pool);
        $this->assertMatchesRegularExpression('/^clear_env = yes$/m', $pool);

        $ini = $this->leer('php.ini.ejemplo');
        $this->assertMatchesRegularExpression('/^expose_php = Off$/m', $ini);
        $this->assertMatchesRegularExpression('/^display_errors = Off$/m', $ini);
        $this->assertMatchesRegularExpression('/^cgi\.fix_pathinfo = 0$/m', $ini);
        $this->assertMatchesRegularExpression('/^memory_limit = 128M$/m', $ini);
    }

    public function test_el_htaccess_de_public_niega_ht_y_el_router_de_desarrollo_tambien(): void
    {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));
        $this->assertMatchesRegularExpression('/<FilesMatch "\^\\\\\.ht">\s*Require all denied\s*<\/FilesMatch>/', $htaccess, 'public/.htaccess debe negar .htaccess y .htpasswd');

        // Paridad con el router de desarrollo (ServidorDesarrolloTest ya comprueba `/.htaccess`; aquí `.htpasswd`).
        $this->get('/.htpasswd')->assertNotFound();
    }

    public function test_la_guia_marca_lo_verificado_y_lo_no_verificado(): void
    {
        $guia = $this->guia();
        $this->assertStringContainsString('Qué se verificó y qué NO', $guia);
        foreach (['2026-11-23', 'httpd 2.4.68', 'PHP 8.2.31', 'MySQL 8.0.41'] as $dato) {
            $this->assertStringContainsString($dato, $guia, "la guía debe fechar/versionar el ensayo: falta «{$dato}»");
        }
        foreach (['Linux', 'nginx', 'mod_php', 'Certificado real'] as $pendiente) {
            $this->assertStringContainsString($pendiente, $guia, "la guía debe declarar «{$pendiente}» como NO verificado");
        }
        // El orden crítico y la advertencia sobre Sanctum figuran en la guía.
        $this->assertStringContainsString('respaldo → auditoría → mantenimiento → migrar', $guia);
        $this->assertStringContainsString('NUNCA `php artisan migrate` a secas', $guia);
    }
}
