<?php

namespace Tests\Feature;

use App\Contracts\IdentidadUsuario;
use App\Contracts\ProveedorIdentidad;
use App\Models\Pedigital\Usuario;
use App\Support\Identidad\PedigitalProveedorIdentidad;
use Tests\TestCase;

/**
 * Pruebas del contrato de identidad (Fase 17D): el dominio depende de {@see ProveedorIdentidad} e {@see IdentidadUsuario},
 * no del modelo ni de las columnas de `pedigital`. El comportamiento del inicio de sesión lo cubre AutenticacionTest.
 */
class IdentidadContratoTest extends TestCase
{
    /** El contenedor entrega el adaptador de pedigital y el modelo cumple el contrato. */
    public function test_el_contenedor_entrega_el_adaptador_de_pedigital(): void
    {
        $this->assertInstanceOf(PedigitalProveedorIdentidad::class, app(ProveedorIdentidad::class));
        $this->assertInstanceOf(IdentidadUsuario::class, new Usuario);
    }

    /** Los accesores traducen las columnas con errores del legado y devuelven siempre texto. */
    public function test_los_accesores_traducen_las_columnas_del_legado(): void
    {
        $u = (new Usuario)->forceFill([
            'nombre_usuario' => 'Ana Pérez', 'login_usuario' => 'ana', 'grado_usuario' => null,
            'tipo_usuario' => 'Estudiante', 'estado_usuario' => 'activo',
        ]);

        $this->assertSame('Ana Pérez', $u->nombreCompleto());
        $this->assertSame('ana', $u->login());
        $this->assertSame('', $u->grado());
        $this->assertSame('Estudiante', $u->rol());
        $this->assertTrue($u->esActivo());
    }

    /** Con otro proveedor enlazado (aquí uno vacío), el inicio de sesión lo usa y no toca pedigital. */
    public function test_el_inicio_de_sesion_usa_el_proveedor_enlazado(): void
    {
        $this->app->instance(ProveedorIdentidad::class, new class implements ProveedorIdentidad
        {
            public function buscarPorLogin(string $login): ?IdentidadUsuario
            {
                return null;
            }

            public function avatarHeredado(int $id): ?int
            {
                return null;
            }
        });

        $this->getJson('/api/sesion'); // inicia sesión/CSRF
        $this->postJson('/api/sesion', ['usuario' => 'cualquiera', 'contrasena' => 'x'])
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    /**
     * Guarda de arquitectura: fuera del modelo, del adaptador y de la configuración de autenticación, ningún archivo de
     * `app/` nombra el modelo `Pedigital\Usuario` ni lee sus columnas del legado.
     */
    public function test_solo_el_adaptador_conoce_pedigital(): void
    {
        // El recurso de sesión conserva las CLAVES del JSON del legado (contrato con el frontend), pero no lee columnas.
        $permitidos = ['Models/Pedigital/Usuario.php', 'Support/Identidad/PedigitalProveedorIdentidad.php', 'Http/Resources/UsuarioSesionResource.php'];
        $prohibido = '/Pedigital\\\\Usuario|nombre_usuario|login_usuario|grado_usuario|tipo_usuario|estado_usuario|pasword_usuario|avatar_explora/';

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $archivo) {
            $relativa = str_replace(app_path().'/', '', $archivo->getPathname());
            if ($archivo->getExtension() !== 'php' || in_array($relativa, $permitidos, true)) {
                continue;
            }
            // Los comentarios pueden explicar el legado; solo importa el código.
            $codigo = implode('', array_map(
                fn ($t) => is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : (is_array($t) ? $t[1] : $t),
                token_get_all((string) file_get_contents($archivo->getPathname()))
            ));
            $this->assertDoesNotMatchRegularExpression($prohibido, $codigo, "$relativa conoce el legado de pedigital");
        }
    }
}
