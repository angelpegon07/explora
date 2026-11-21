<?php

namespace Tests\Unit;

use App\Support\ImagenesEmbebidas;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas de las funciones puras que localizan y analizan imágenes embebidas (no usan base de datos ni disco).
 */
class ImagenesEmbebidasTest extends TestCase
{
    public function test_el_tipo_se_detecta_por_magic_bytes(): void
    {
        $this->assertSame('png', ImagenesEmbebidas::tipoReal("\x89PNG\r\n\x1a\nxxxx"));
        $this->assertSame('jpg', ImagenesEmbebidas::tipoReal("\xFF\xD8\xFF\xE0xxxx"));
        $this->assertSame('gif', ImagenesEmbebidas::tipoReal('GIF89a....'));
        $this->assertSame('gif', ImagenesEmbebidas::tipoReal('GIF87a....'));
        $this->assertSame('webp', ImagenesEmbebidas::tipoReal('RIFF'."\x1a\0\0\0".'WEBPVP8L'));
        $this->assertNull(ImagenesEmbebidas::tipoReal('RIFF'."\x1a\0\0\0".'WAVEfmt '), 'RIFF que no es WEBP');
        $this->assertNull(ImagenesEmbebidas::tipoReal('<svg xmlns="http://www.w3.org/2000/svg"/>'));
        $this->assertNull(ImagenesEmbebidas::tipoReal('BM'.str_repeat("\0", 20)), 'BMP no se admite');
        $this->assertNull(ImagenesEmbebidas::tipoReal(''));
    }

    public function test_la_decodificacion_es_estricta(): void
    {
        $this->assertSame('hola', ImagenesEmbebidas::decodificarEstricto(base64_encode('hola')));
        $this->assertSame('ab', ImagenesEmbebidas::decodificarEstricto(base64_encode('ab')), 'relleno final válido');
        foreach (['', 'abc', 'AAAA!AAA', 'AA=AAAAA', 'A===', 'AAAA AAAA', "AAAA\nAAAA", 'AAAA====', 'ab-_'] as $malo) {
            $this->assertNull(ImagenesEmbebidas::decodificarEstricto($malo), 'Debe rechazar: '.json_encode($malo));
        }
    }

    public function test_solo_se_reconoce_el_src_de_un_img_entre_comillas(): void
    {
        $uri = 'data:image/png;base64,AAAA';
        $texto = '<p>'.$uri.'</p>'                       // texto suelto
            .'<img data-src="'.$uri.'">'                  // data-src no es src
            .'<div style="background:url('.$uri.')">'    // estilo
            .'<img alt="x" src="'.$uri.'" class="y">'     // válido, comillas dobles
            ."<img\nsrc = '".$uri."'>"                    // válido, comillas simples y espacios
            .'<IMG SRC="DATA:IMAGE/GIF;BASE64,AAAA">';    // válido, mayúsculas
        $r = ImagenesEmbebidas::localizarDataUris($texto);

        $this->assertCount(3, $r['uris']);
        $this->assertCount(3, $r['desconocidas']);
        $this->assertSame(['image/png', 'image/png', 'image/gif'], array_column($r['uris'], 'mime'));
        foreach ($r['uris'] as $u) {
            $this->assertSame('AAAA', substr($texto, $u['b64_inicio'], $u['fin'] - $u['b64_inicio']));
        }
    }

    public function test_sin_comilla_de_cierre_o_sin_base64_se_deja_como_desconocida(): void
    {
        $r = ImagenesEmbebidas::localizarDataUris('<img src="data:image/png;base64,AAAA');
        $this->assertSame([], $r['uris']);
        $this->assertSame(['comilla de cierre ausente'], $r['desconocidas']);

        $r = ImagenesEmbebidas::localizarDataUris('<img src="data:image/svg+xml;utf8,<svg/>">');
        $this->assertSame([], $r['uris']);
        $this->assertSame(['no es base64'], $r['desconocidas']);
    }

    public function test_las_url_externalizadas_se_localizan_solo_si_tienen_la_forma_exacta(): void
    {
        $h = str_repeat('ab', 32);
        $ok = '/contenido/fichas/ab/'.$h.'.png';
        $texto = '<img src="'.$ok.'"><img src="/contenido/fichas/cd/'.$h.'.png">'   // carpeta no coincide con el hash
            .'<img src="/contenido/fichas/ab/'.$h.'.svg">'                              // extensión no admitida
            .'<img src="/contenido/fichas/ab/'.$h.'.png?x=1">'                          // con consulta
            .'<a href="'.$ok.'">enlace</a>';                                            // no es un <img src>
        $r = ImagenesEmbebidas::localizarUrls($texto, '/contenido/fichas');

        $this->assertCount(1, $r);
        $this->assertSame([$h, 'png'], [$r[0]['hash'], $r[0]['ext']]);
    }

    public function test_reemplazar_tramos_deja_el_resto_intacto(): void
    {
        $this->assertSame('a[X]b[Y]c', ImagenesEmbebidas::reemplazarTramos('a123b45c', [
            ['inicio' => 1, 'fin' => 4, 'nuevo' => '[X]'],
            ['inicio' => 5, 'fin' => 7, 'nuevo' => '[Y]'],
        ]));
        $this->assertSame('igual', ImagenesEmbebidas::reemplazarTramos('igual', []));
    }
}
