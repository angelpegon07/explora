<?php

namespace Tests\Unit;

use App\Enums\SeccionFicha;
use App\Enums\TipoPregunta;
use App\Models\Explora\DepartamentoDescripcion;
use App\Models\Explora\OpcionVerdaderoFalso;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas de los enums del dominio (Fase 17C). No usan base de datos: fijan los valores que forman el contrato de la API.
 */
class EnumsTest extends TestCase
{
    /** Los valores de TipoPregunta son los textos de la base y de quiz.js, con la errata «verfal» incluida. */
    public function test_tipo_pregunta_conserva_los_valores_del_contrato(): void
    {
        $this->assertSame('multiple', TipoPregunta::Multiple->value);
        $this->assertSame('verfal', TipoPregunta::VerdaderoFalso->value);
        $this->assertNull(TipoPregunta::tryFrom('otro'));
    }

    /** Las claves de la ficha y su orden son los que lee verMas.js (16 secciones). */
    public function test_las_secciones_de_la_ficha_conservan_clave_y_orden(): void
    {
        $this->assertSame(
            ['descripcion', 'nombreDepartamento', 'historia', 'geografia', 'hidrografia', 'clima', 'demografia', 'etnografia',
                'faunaFlora', 'economia', 'cultura', 'gastronomia', 'festividades', 'sitios', 'personajes', 'capital'],
            array_map(fn (SeccionFicha $s) => $s->value, SeccionFicha::cases())
        );
    }

    /** Toda sección tiene columnas en lista blanca, con `id` y sin columnas internas. */
    public function test_cada_seccion_tiene_lista_blanca_sin_columnas_internas(): void
    {
        foreach (SeccionFicha::cases() as $s) {
            $this->assertContains('id', $s->columnas(), $s->name);
            $this->assertEmpty(array_intersect($s->columnas(), ['remember_token', 'created_at', 'updated_at']), $s->name);
        }
    }

    /** Cada relación que nombra el enum existe en DepartamentoDescripcion (evita erratas al renombrar relaciones). */
    public function test_las_relaciones_del_enum_existen_en_el_modelo(): void
    {
        $modelo = new DepartamentoDescripcion;
        foreach (SeccionFicha::cases() as $s) {
            if ($s->relacion() !== null) {
                $this->assertTrue(method_exists($modelo, $s->relacion()), $s->name.' → '.$s->relacion());
            }
        }
        foreach (['etnias', 'flora', 'fauna'] as $lista) {
            $this->assertArrayHasKey($lista, SeccionFicha::relacionesACargar());
        }
    }

    /** La clase renombrada sigue apuntando a la tabla con la errata. */
    public function test_la_opcion_verdadero_falso_conserva_su_tabla(): void
    {
        $this->assertSame('opciones_verfal', (new OpcionVerdaderoFalso)->getTable());
    }
}
