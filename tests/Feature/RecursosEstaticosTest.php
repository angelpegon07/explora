<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Fase 17F: coherencia de los recursos estáticos (JS, CSS, fuentes) que cargan las vistas Blade.
 *
 * Son pruebas de ARCHIVOS: no arrancan Laravel ni tocan ninguna base de datos. Vigilan dos cosas:
 *  - que ninguna vista apunte a un archivo de `public/` que ya no existe (un `<script>` o `<link>` roto no falla en
 *    el servidor, solo rompe la página en el navegador); y
 *  - que cada librería compartida (jQuery, SweetAlert2) viva en UNA sola copia, bajo `public/vendor/`, para que nadie
 *    vuelva a copiarla dentro de un juego o de una página (antes había tres versiones de jQuery y dos de SweetAlert2).
 */
class RecursosEstaticosTest extends TestCase
{
    /** Raíz del repositorio. */
    private function raiz(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Lista los archivos de un directorio de forma recursiva (rutas absolutas), omitiendo las carpetas indicadas.
     *
     * @param  list<string>  $omitir  Rutas ABSOLUTAS de carpetas que no se recorren.
     * @return list<string>
     */
    private function archivos(string $dir, array $omitir = []): array
    {
        $resultado = [];
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
        /** @var SplFileInfo $archivo */
        foreach ($iterador as $archivo) {
            $ruta = $archivo->getPathname();
            foreach ($omitir as $o) {
                if (str_starts_with($ruta, $o.DIRECTORY_SEPARATOR)) {
                    continue 2;
                }
            }
            if ($archivo->isFile()) {
                $resultado[] = $ruta;
            }
        }
        sort($resultado);

        return $resultado;
    }

    /**
     * Toda ruta LITERAL de `asset('...')` o `asset_v('...')` (helper de versionado, ver App\Support\Assets) en resources/views apunta a un archivo existente de public/.
     *
     * Solo se comprueban las rutas escritas enteras entre comillas, cerradas con el paréntesis: `asset('a/'.$x)`,
     * `asset("a/{$x}")` y similares (rutas dinámicas) no coinciden con el patrón y se ignoran. Se descarta la cadena
     * de consulta/ancla (`?v=3`, `#x`). Los comentarios Blade `{{-- --}}` se quitan antes para no exigir archivos que
     * solo se mencionan como ejemplo en la documentación de una vista. Una carpeta existente también vale (enlaces a
     * `/lineaTiempo`, `/ViajaPED`, a las carpetas de los juegos o a `multimedia/imagenes`, que atiende Laravel o Apache).
     *
     * EXCLUIDA (defecto PREEXISTENTE, ajeno a la Fase 17F): `juegos/juegos/laberinto/css/perdio_gano.png`. La vista del
     * laberinto la pide pero el archivo nunca estuvo en el repositorio; no se ve porque la imagen arranca oculta y el JS
     * del juego le cambia el `src` a `gano.png`/`perdio.png` antes de mostrarla. Se reporta; al corregirlo, quitar esta excepción.
     */
    public function test_cada_asset_literal_de_las_vistas_existe_en_public(): void
    {
        $vistas = $this->raiz().'/resources/views';
        $publico = $this->raiz().'/public';
        $rotas = [];
        $comprobadas = 0;
        $conocidasRotas = ['juegos/juegos/laberinto/css/perdio_gano.png'];

        foreach ($this->archivos($vistas) as $vista) {
            $codigo = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($vista));
            if (preg_match_all('/\basset(?:_v)?\(\s*([\'"])([^\'"$\{\}]+)\1\s*\)/', $codigo, $coincidencias) === 0) {
                continue;
            }
            foreach ($coincidencias[2] as $ruta) {
                $limpia = ltrim((string) preg_replace('/[?#].*$/', '', $ruta), '/');
                $comprobadas++;
                if (! is_file($publico.'/'.$limpia) && ! is_dir($publico.'/'.$limpia) && ! in_array($limpia, $conocidasRotas, true)) {
                    $rotas[] = substr($vista, strlen($vistas) + 1).' -> '.$ruta;
                }
            }
        }

        $this->assertGreaterThan(50, $comprobadas, 'El patrón no está encontrando las rutas asset() de las vistas');
        $this->assertSame([], $rotas, "asset() que apuntan a archivos inexistentes en public/:\n".implode("\n", $rotas));
    }

    /**
     * Las copias unificadas existen donde la documentación dice (public/vendor) y FontAwesome trae sus fuentes web junto al CSS
     * (el CSS las pide con `url(../webfonts/...)`, relativas a su propia carpeta).
     */
    public function test_las_librerias_unificadas_existen_en_vendor(): void
    {
        $vendor = $this->raiz().'/public/vendor';
        foreach (['jquery/jquery-3.7.1.min.js', 'sweetalert2/sweetalert2.all.min.js', 'sweetalert2/sweetalert2.min.css',
            'popper/popper.min.js', 'fontawesome/css/all.min.css', 'fontawesome/webfonts/fa-solid-900.woff2',
            'fontawesome/webfonts/fa-regular-400.woff2', 'fontawesome/webfonts/fa-brands-400.woff2'] as $archivo) {
            $this->assertFileExists("$vendor/$archivo", $archivo);
        }
        $this->assertStringContainsString('v3.7.1', (string) file_get_contents("$vendor/jquery/jquery-3.7.1.min.js", false, null, 0, 200));
        $this->assertStringContainsString('v11.17.2', (string) file_get_contents("$vendor/sweetalert2/sweetalert2.all.min.js", false, null, 0, 200));
        $this->assertStringContainsString('Font Awesome Free 6.7.2', (string) file_get_contents("$vendor/fontawesome/css/all.min.css", false, null, 0, 200));
    }

    /**
     * Existe UNA sola copia de jQuery, SweetAlert2, Popper y Font Awesome en public/: la de public/vendor.
     *
     * Se buscan por NOMBRE de archivo (`jquery*.min.js`, `sweetalert*`, `popper*`, `all.min.css` de Font Awesome por su
     * cabecera) en todo public/, salvo:
     *  - `public/vendor` (la copia buena);
     *  - `public/ViajaPED/node_modules` (dependencias de desarrollo locales, sin versionar y bloqueadas por el servidor);
     *  - `jquery.easing.js`, `jquery.mousewheel.js` y `jquery-ui.min.js`: son PLUGINS distintos, no copias de jQuery.
     * Bootstrap (4.5/4.6/5.3) NO se vigila aquí: conviven a propósito (ver README, «Frontend: librerías»).
     */
    public function test_no_hay_copias_duplicadas_de_las_librerias_compartidas(): void
    {
        $publico = $this->raiz().'/public';
        $copias = [];
        foreach ($this->archivos($publico, ["$publico/vendor", "$publico/ViajaPED/node_modules"]) as $ruta) {
            $nombre = strtolower(basename($ruta));
            $relativa = substr($ruta, strlen($publico) + 1);
            $esJquery = preg_match('/^jquery[-.\d]*(\.min)?\.js$/', $nombre) === 1;
            $esSweetalert = str_starts_with($nombre, 'sweetalert');
            $esPopper = str_starts_with($nombre, 'popper');
            $esFontAwesome = $nombre === 'fontawesome.min.css' || $nombre === 'all.min.css' || str_starts_with($nombre, 'fa-solid-900');
            if ($esJquery || $esSweetalert || $esPopper || $esFontAwesome) {
                $copias[] = $relativa;
            }
        }

        $this->assertSame([], $copias, "Librerías duplicadas fuera de public/vendor (usa la copia de public/vendor):\n".implode("\n", $copias));
    }
}
