<?php

namespace Tests\Feature;

use App\Models\Explora\PerfilExplora;
use App\Models\Pedigital\Usuario;
use Illuminate\Database\QueryException;
use Tests\Concerns\RespaldaPerfiles;
use Tests\TestCase;

/**
 * Pruebas del perfil propio de Explora (`perfiles_explora`): avatar de respaldo desde pedigital, prioridad del perfil
 * propio, que pedigital no se modifica y que un usuario no puede tener dos perfiles.
 */
class PerfilExploraTest extends TestCase
{
    use RespaldaPerfiles;

    /**
     * Respalda los perfiles de los usuarios de prueba antes de cada prueba (ver {@see RespaldaPerfiles}).
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->respaldarPerfiles();
    }

    /**
     * Borra el perfil del usuario 1 que pudo crear la prueba.
     */
    protected function tearDown(): void
    {
        $this->restaurarPerfiles();
        parent::tearDown();
    }

    /**
     * Sin fila propia, avatarDe() devuelve el valor de pedigital (y 1 si el usuario no existe).
     */
    public function test_sin_perfil_el_avatar_es_el_de_pedigital(): void
    {
        $this->assertNull(PerfilExplora::where('usuario_id', 1)->first());
        $this->assertSame((int) Usuario::findOrFail(1)->avatar_explora, PerfilExplora::avatarDe(1));
        $this->assertSame(1, PerfilExplora::avatarDe(987654321), 'usuario inexistente: avatar por defecto');
    }

    /**
     * updateOrCreate no duplica, el perfil propio manda y pedigital queda intacto.
     */
    public function test_el_perfil_propio_tiene_prioridad_y_no_toca_pedigital(): void
    {
        $antes = (int) Usuario::findOrFail(1)->avatar_explora;
        PerfilExplora::updateOrCreate(['usuario_id' => 1], ['avatar' => 5]);
        PerfilExplora::updateOrCreate(['usuario_id' => 1], ['avatar' => 6]);

        $this->assertSame(1, PerfilExplora::where('usuario_id', 1)->count(), 'updateOrCreate no duplica');
        $this->assertSame(6, PerfilExplora::avatarDe(1));
        $this->assertSame($antes, (int) Usuario::findOrFail(1)->avatar_explora, 'pedigital queda intacto');
    }

    /**
     * La clave única `usuario_id` impide perfiles duplicados.
     */
    public function test_un_usuario_no_puede_tener_dos_perfiles(): void
    {
        PerfilExplora::create(['usuario_id' => 1, 'avatar' => 2]);
        $this->expectException(QueryException::class);
        PerfilExplora::create(['usuario_id' => 1, 'avatar' => 3]);
    }
}
