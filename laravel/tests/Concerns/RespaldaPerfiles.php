<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Respalda y restaura las filas de `perfiles_explora` de los usuarios que usan las pruebas, en lugar de borrarlas a
 * ciegas: si algún día existiera un perfil real de esos ids, la prueba lo conservaría.
 *
 * Uso: llamar a {@see self::respaldarPerfiles()} en `setUp()` y a {@see self::restaurarPerfiles()} en `tearDown()`.
 */
trait RespaldaPerfiles
{
    /** @var array<int, array<string, mixed>> Filas originales de los usuarios de prueba. */
    private array $perfilesRespaldados = [];

    /** Ids de usuario (pedigital.users.id) cuyos perfiles tocan las pruebas. */
    private const USUARIOS_DE_PRUEBA = [1, 2, 3];

    /**
     * Guarda en memoria las filas actuales de `perfiles_explora` de los usuarios de prueba.
     */
    protected function respaldarPerfiles(): void
    {
        $this->perfilesRespaldados = DB::table('perfiles_explora')
            ->whereIn('usuario_id', self::USUARIOS_DE_PRUEBA)->get()
            ->map(fn ($fila) => (array) $fila)->all();
    }

    /**
     * Elimina lo que crearon las pruebas y vuelve a insertar las filas originales (normalmente ninguna).
     */
    protected function restaurarPerfiles(): void
    {
        DB::table('perfiles_explora')->whereIn('usuario_id', self::USUARIOS_DE_PRUEBA)->delete();
        if ($this->perfilesRespaldados !== []) {
            DB::table('perfiles_explora')->insert($this->perfilesRespaldados);
        }
    }
}
