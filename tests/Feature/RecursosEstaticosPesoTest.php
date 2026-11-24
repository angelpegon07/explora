<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Fase 17F: peso de las imágenes de `public/` y existencia de las que citan las vistas Blade.
 *
 * Son pruebas de ARCHIVOS: no tocan base de datos ni servidor. Dos guardas:
 *
 *  1. Ninguna imagen (webp/png/jpg/jpeg) de `public/` supera {@see self::TOPE_BYTES} salvo las excepciones explícitas de
 *     {@see self::EXCEPCIONES}. Impide que vuelva a colarse una imagen de varios MB sin optimizarla antes con
 *     `scripts/optimizar-imagenes.sh`. Se excluyen las carpetas de terceros que retira otra fase.
 *  2. Toda ruta LITERAL `asset('....png|webp|jpg|jpeg|gif')` escrita en `resources/views` apunta a un archivo que existe
 *     (un `.png` convertido a `.webp` y borrado sin actualizar la vista rompería la imagen en silencio).
 */
class RecursosEstaticosPesoTest extends TestCase
{
    /** Tope por imagen: 1,5 MB. */
    private const TOPE_BYTES = 1572864;

    /**
     * Imágenes legítimas que superan el tope, con su tamaño máximo permitido (bytes, redondeado hacia arriba: si el
     * archivo crece, la prueba falla). Ninguna se puede reducir con `cwebp` sin perder resolución o animación:
     *  - Webp ANIMADOS (mascota y celebraciones de los juegos): cwebp no re-codifica animaciones.
     *  - Webp estáticos ya comprimidos con pérdida a calidad 80 (mapas grandes y fondos de «arrastra»): recomprimirlos no
     *    ahorra ni el 25 %; solo bajaría su peso reduciendo dimensiones, decisión de diseño pendiente.
     *  - `ViajaPED/epocas/media.png` y `puzzle/etnias/7.png`: PNG citados por `data.json` y por un nombre armado en JS
     *    (`id + "/" + n + ".png"`), por lo que no se pueden cambiar a webp solo con editar vistas.
     *
     * @var array<string,int>
     */
    private const EXCEPCIONES = [
        'img/pensando.webp' => 10303396,              // animado, 426 fotogramas
        'images/victoria.webp' => 5071276,            // animado
        'juegos/juegos/assets/images/incorrecto.webp' => 6186476, // animado
        'juegos/juegos/assets/images/correcto.webp' => 6123974,   // animado
        'juegos/juegos/assets/images/victoria.webp' => 4379968,   // animado
        'juegos/juegos/assets/images/pensando.webp' => 2045340,   // animado
        'juegos/juegos/laberinto/css/nave2.webp' => 2089762,      // animado
        'juegos/juegos/arrastra/img/etnias.webp' => 3217540,      // estático, ya lossy q80
        'juegos/juegos/arrastra/img/colombia.webp' => 3009526,    // estático, ya lossy q80
        'juegos/juegos/arrastra/img/parques.webp' => 2831120,     // estático, ya lossy q80
        'maps_etnias/etnias.webp' => 1873288,                     // estático, ya lossy q80
        'maps/colombia.webp' => 1771920,                          // estático, ya lossy q80
        'maps/colombia_gris.webp' => 1787558,                     // estático, ya lossy q80
        'ViajaPED/epocas/media.png' => 1960475,                   // referenciado desde data.json
        'juegos/juegos/puzzle/etnias/7.png' => 1624529,           // nombre armado en script.js
    ];

    /**
     * Imágenes citadas por una vista que NO existen: defecto heredado ya registrado (REGISTRO_CAMBIOS.md, «Defectos
     * heredados solo reportados»), no una regresión. Si se repone o se retira la referencia, quítela de aquí.
     *
     * @var list<string>
     */
    private const FALTANTES_CONOCIDAS = ['juegos/juegos/laberinto/css/perdio_gano.png'];

    /** Carpetas de `public/` ajenas a esta guarda (librerías de terceros). */
    private const EXCLUIDAS = ['ViajaPED/node_modules/', 'juegos/juegos/sopa_letras/wordfind-master/', 'vendor/'];

    /**
     * Imágenes de `public/` como [ruta relativa => bytes].
     *
     * @return array<string,int>
     */
    private function imagenesPublicas(): array
    {
        $raiz = base_path('public');
        $imagenes = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $archivo) {
            if (! $archivo->isFile() || ! preg_match('/\.(webp|png|jpe?g)$/i', $archivo->getFilename())) {
                continue;
            }
            $relativa = str_replace('\\', '/', substr($archivo->getPathname(), strlen($raiz) + 1));
            foreach (self::EXCLUIDAS as $prefijo) {
                if (str_starts_with($relativa, $prefijo)) {
                    continue 2;
                }
            }
            $imagenes[$relativa] = $archivo->getSize();
        }

        return $imagenes;
    }

    public function test_ninguna_imagen_supera_el_tope_salvo_excepciones_explicitas(): void
    {
        $excesos = [];
        foreach ($this->imagenesPublicas() as $ruta => $bytes) {
            $permitido = self::EXCEPCIONES[$ruta] ?? self::TOPE_BYTES;
            if ($bytes > $permitido) {
                $excesos[] = sprintf('%s pesa %d bytes (máximo %d)', $ruta, $bytes, $permitido);
            }
        }

        $this->assertSame([], $excesos, "Imágenes demasiado pesadas; optimícelas con scripts/optimizar-imagenes.sh:\n".implode("\n", $excesos));
    }

    public function test_las_excepciones_siguen_existiendo_y_superan_el_tope(): void
    {
        // Evita que la lista se pudra: una excepción borrada u optimizada debe salir de ella.
        $imagenes = $this->imagenesPublicas();
        foreach (self::EXCEPCIONES as $ruta => $maximo) {
            $this->assertArrayHasKey($ruta, $imagenes, "La excepción $ruta ya no existe: quítela de EXCEPCIONES.");
            $this->assertGreaterThan(self::TOPE_BYTES, $imagenes[$ruta], "$ruta ya cabe en el tope: quítela de EXCEPCIONES.");
        }
    }

    public function test_las_imagenes_literales_de_las_vistas_existen(): void
    {
        $faltan = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS));
        foreach ($it as $vista) {
            if (! $vista->isFile() || ! str_ends_with($vista->getFilename(), '.blade.php')) {
                continue;
            }
            $contenido = (string) file_get_contents($vista->getPathname());
            // Solo literales completos: asset('ruta/imagen.ext') o asset("ruta/imagen.ext"); las rutas armadas se ignoran.
            preg_match_all('/asset\(\s*([\'"])([^\'"\$]+\.(?:png|webp|jpe?g|gif))\1\s*\)/i', $contenido, $m);
            foreach ($m[2] as $ruta) {
                if (! in_array($ruta, self::FALTANTES_CONOCIDAS, true) && ! is_file(base_path('public/'.ltrim($ruta, '/')))) {
                    $faltan[] = $ruta.' (en '.basename($vista->getPathname()).')';
                }
            }
        }

        $this->assertSame([], $faltan, "Imágenes citadas en vistas que no existen:\n".implode("\n", $faltan));
    }
}
