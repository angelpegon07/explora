<?php

namespace Tests\Feature;

use App\Models\Explora\Departamento;
use App\Models\Explora\DepartamentoDescripcion;
use App\Models\Explora\Pregunta;
use App\Models\Explora\ResultadoJuego;
use App\Models\Pedigital\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

/**
 * Fase 2: modelos Eloquent sobre las copias de prueba (explora_test / pedigital_test). No migra ni borra datos.
 */
class ModelosExploraTest extends TestCase
{
    /**
     * Integridad de datos: 33 divisiones y ninguna sin fila en departamento_descripcion.
     */
    public function test_hay_33_departamentos_y_todos_tienen_descripcion(): void
    {
        $this->assertSame(33, Departamento::count());
        $this->assertSame(0, Departamento::doesntHave('descripcion')->count());
    }

    /**
     * TEC-01/DAT-04: las secciones cuelgan de `descripcion.id`, distinto del id del departamento; y la relación
     * `clima` no se confunde con la columna `clima`.
     */
    public function test_la_relacion_usa_el_id_de_descripcion_como_clave_de_las_secciones(): void
    {
        $antioquia = Departamento::with('descripcion')->where('nombre', 'Antioquia')->firstOrFail();
        $this->assertSame('Medellín', $antioquia->capital);
        $d = $antioquia->descripcion;
        $this->assertSame($antioquia->id, (int) $d->departamento);
        $this->assertNotSame((int) $d->id, (int) $d->departamento, 'TEC-01: descripcion.id != list_departamentos.id');
        $this->assertNotNull($d->climaDetalle);
        $this->assertSame($d->id, $d->climaDetalle->departamento);
        $this->assertIsString($d->clima, 'el atributo clima de la tabla no debe confundirse con la relación');
        $this->assertNotNull($d->historia);
        $this->assertGreaterThan(0, $d->sitios()->count());
        $this->assertSame($d->id, $d->sitios->first()->departamento);
        $this->assertTrue($d->sitios->first()->ficha->is($d));
    }

    /**
     * Humo: ejecuta las 19 relaciones de DepartamentoDescripcion contra la base de prueba.
     */
    public function test_todas_las_relaciones_de_la_ficha_se_resuelven_sin_error(): void
    {
        $d = DepartamentoDescripcion::firstOrFail();
        foreach (['capital', 'climaDetalle', 'cultura', 'demografia', 'economia', 'etnografia', 'faunaFlora', 'gastronomia',
            'geografiaDetalle', 'hidrografia', 'historia', 'referencia', 'etnias', 'fauna', 'flora', 'festividades',
            'personajes', 'sitios', 'preguntas'] as $relacion) {
            $this->assertNotNull($d->{$relacion}()->getRelated(), $relacion);
            $this->assertNotNull($d->{$relacion}()->get()); // ejecuta la consulta
        }
    }

    /**
     * Los modelos devuelven lo mismo que una consulta directa a la tabla (valor y conteo).
     */
    public function test_los_valores_del_modelo_coinciden_con_la_tabla(): void
    {
        $d = DepartamentoDescripcion::where('departamento', 2)->firstOrFail();
        $crudo = DB::table('departamento_clima')->where('departamento', $d->id)->value('clima');
        $this->assertSame($crudo, $d->climaDetalle->clima);
        $this->assertSame(
            DB::table('departamento_sitios_list')->where('departamento', $d->id)->count(),
            $d->sitios()->count()
        );
    }

    /**
     * Un departamento tiene 30 preguntas; las de «multiple» tienen ≥ 2 opciones y las verdadero/falso una respuesta.
     */
    public function test_preguntas_y_opciones(): void
    {
        $d = DepartamentoDescripcion::where('departamento', 2)->firstOrFail();
        $this->assertSame(30, $d->preguntas()->count());
        $multiple = Pregunta::where('departamento', $d->id)->where('tipo_pregunta', 'multiple')->firstOrFail();
        $this->assertGreaterThanOrEqual(2, $multiple->opcionesMultiple()->count());
        $this->assertTrue($multiple->opcionesMultiple->first()->preguntaPadre->is($multiple));
        $vf = Pregunta::where('tipo_pregunta', '<>', 'multiple')->firstOrFail();
        $this->assertSame(1, $vf->opcionesVerdaderoFalso()->count());
    }

    /**
     * SoloLectura: guardar un modelo de contenido lanza LogicException.
     */
    public function test_modelos_de_contenido_son_de_solo_lectura(): void
    {
        $dep = Departamento::firstOrFail();
        $dep->nombre = 'cambiado';
        $this->expectException(LogicException::class);
        $dep->save();
    }

    /**
     * Tras el intento bloqueado el dato sigue igual en la base.
     */
    public function test_el_contenido_no_se_pudo_modificar_tras_el_intento(): void
    {
        try {
            $d = Departamento::firstOrFail();
            $d->nombre = 'x';
            $d->save();
        } catch (LogicException) {
        }
        $this->assertNotSame('x', Departamento::firstOrFail()->nombre);
    }

    /**
     * ResultadoJuego sí escribe (lo necesitan los juegos): crea, lee con casts y limpia.
     */
    public function test_resultado_juego_se_puede_crear_y_borrar(): void
    {
        $marca = 'zz_modelo_'.uniqid();
        try {
            $r = ResultadoJuego::create([
                'nombre_juego' => $marca, 'nivel' => 'n', 'tiempo' => 5, 'jugador' => 'Prueba', 'avatar' => '1.png',
                'grado' => '5', 'puntaje' => 3, 'categoria' => 'c', 'id_usuario' => 0, 'fecha' => '01-01-2026 00:00:00',
            ]);
            $this->assertSame(3, $r->fresh()->puntaje);
            $this->assertSame(0, $r->fresh()->id_usuario);
        } finally {
            ResultadoJuego::where('nombre_juego', $marca)->delete();
        }
        $this->assertSame(0, ResultadoJuego::where('nombre_juego', $marca)->count());
    }

    /**
     * SEC-04: el modelo Usuario oculta hash, token y correo al serializar y su hash valida con bcrypt.
     */
    public function test_usuario_pedigital_se_lee_sin_exponer_el_hash(): void
    {
        $u = Usuario::findOrFail(1);
        $this->assertSame(1672, Usuario::count());
        $this->assertTrue($u->esActivo());
        $this->assertSame('Estudiante', $u->tipo_usuario);
        $array = $u->toArray();
        foreach (['pasword_usuario', 'remember_token', 'email_usuario'] as $oculto) {
            $this->assertArrayNotHasKey($oculto, $array);
        }
        $this->assertTrue(Hash::check('password', $u->getAuthPassword()));
        $this->assertFalse(Hash::check('otra', $u->getAuthPassword()));
    }

    /**
     * Doble barrera: el modelo bloquea escribir y, aunque se esquive, el usuario MySQL (solo SELECT) lo rechaza.
     */
    public function test_usuario_pedigital_no_se_puede_escribir(): void
    {
        $u = Usuario::findOrFail(1);
        $u->avatar_explora = 9;
        try {
            $u->save();
            $this->fail('Se permitió escribir en pedigital');
        } catch (LogicException) {
            $this->assertSame(1, (int) Usuario::findOrFail(1)->avatar_explora);
        }
        // Defensa en profundidad: aunque se esquive el modelo, el usuario MySQL solo tiene SELECT.
        $this->expectException(QueryException::class);
        DB::connection('pedigital')->table('users')->where('id', 1)->update(['avatar_explora' => 9]);
    }

    /**
     * Los modelos devuelven lo mismo que consultarInfo.php en los departamentos 1, 2 y 7; necesita el servidor legado.
     */
    public function test_equivalencia_con_el_endpoint_legado_en_tres_departamentos(): void
    {
        $base = rtrim(env('LEGACY_BASE_URL', 'http://127.0.0.1:8081'), '/');
        try {
            Http::timeout(3)->get($base.'/mapplic/php/chat.php');
        } catch (\Throwable) {
            $this->markTestSkipped('Servidor legado no disponible: ejecute tests/Legacy/serve.sh');
        }
        foreach ([1, 2, 7] as $id) {
            $legado = Http::timeout(60)->get($base.'/mapplic/php/consultarInfo.php', ['id' => $id])->json();
            $dep = Departamento::with('descripcion')->findOrFail($id);
            $d = $dep->descripcion;
            $this->assertSame($legado['nombreDepartamento']['nombre'], $dep->nombre);
            $this->assertSame($legado['descripcion']['id'], (string) $d->id);
            $this->assertSame($legado['clima']['clima'], $d->climaDetalle?->clima);
            $this->assertSame($legado['historia']['historia'], $d->historia?->historia);
            $this->assertSame($legado['capital']['capital'], $d->capital?->capital);
            $this->assertCount(count($legado['sitios']), $d->sitios);
            $this->assertCount(count($legado['festividades']), $d->festividades);
            $this->assertCount(count($legado['personajes']), $d->personajes);
            $this->assertCount(count($legado['faunaFlora']['lista_fauna']), $d->fauna);
            $this->assertCount(count($legado['faunaFlora']['lista_flora']), $d->flora);
            $this->assertCount(count($legado['etnografia']['lista']), $d->etnias);
        }
    }
}
