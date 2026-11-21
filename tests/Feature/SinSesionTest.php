<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Las páginas que no pintan datos del usuario NO arrancan sesión: sin archivo en `storage/framework/sessions` y sin
 * `Set-Cookie` (`explora_session`/`XSRF-TOKEN`), de modo que un bot no llena el disco y los proxies pueden cachear.
 * Siguen CON sesión `/perfil`, `/juegos` y la API (`GET /api/sesion` entrega la cookie CSRF al JS del login).
 */
class SinSesionTest extends TestCase
{
    /**
     * GET que conserva la barra final (las URL canónicas de juegos y páginas estáticas la llevan).
     *
     * @param  string  $uri  Ruta con su barra final y consulta si procede.
     */
    private function pedir(string $uri): TestResponse
    {
        return $this->createTestResponse($this->app->make(Kernel::class)->handle(Request::create('http://localhost'.$uri, 'GET')));
    }

    /**
     * Rutas SIN sesión: portada, mapas, juegos, páginas estáticas y todas las redirecciones 301.
     *
     * @return array<string, array{0:string, 1:int}> URL y estado esperado.
     */
    public static function rutasSinSesion(): array
    {
        $rutas = ['/' => 200, '/index.html' => 301, '/miPerfil.html' => 301, '/juegos/index.html' => 301];
        foreach (array_keys(require dirname(__DIR__, 2).'/config/mapas.php') as $mapa) {
            $rutas["/$mapa"] = 200;
            $rutas["/$mapa.html"] = 301;
        }
        foreach (require dirname(__DIR__, 2).'/config/juegos.php' as $juego) {
            $rutas["/juegos/juegos/$juego/"] = 200;
            $rutas["/juegos/juegos/$juego"] = 301;           // sin barra -> con barra
            $rutas["/juegos/juegos/$juego/index.html"] = 301;
        }
        foreach (require dirname(__DIR__, 2).'/config/paginas_estaticas.php' as $pagina) {
            $rutas["/$pagina/"] = 200;
            $rutas["/$pagina"] = 301;
            $rutas["/$pagina/index.html"] = 301;
        }

        $datos = [];
        foreach ($rutas as $ruta => $estado) {
            $datos[$ruta] = [$ruta, $estado];
        }

        return $datos;
    }

    /**
     * @dataProvider rutasSinSesion
     */
    public function test_sin_cabecera_set_cookie(string $ruta, int $estado): void
    {
        $r = $this->pedir($ruta);

        $r->assertStatus($estado);
        $this->assertFalse($r->headers->has('Set-Cookie'), "$ruta no debe emitir Set-Cookie");
        $this->assertSame([], $r->headers->getCookies(), "$ruta no debe encolar cookies");
    }

    /**
     * Las páginas que pintan datos del usuario y el endpoint que entrega la cookie CSRF SÍ emiten cookies.
     */
    public function test_con_sesion_en_perfil_juegos_y_api_sesion(): void
    {
        foreach (['/perfil', '/juegos'] as $ruta) {
            $r = $this->pedir($ruta)->assertOk();
            $this->assertNotEmpty($r->headers->getCookies(), "$ruta debe seguir con sesión");
        }

        $r = $this->getJson('/api/sesion')->assertStatus(401);
        $nombres = array_map(static fn ($c): string => $c->getName(), $r->headers->getCookies());
        $this->assertContains('XSRF-TOKEN', $nombres, '/api/sesion debe entregar la cookie CSRF');
        $this->assertContains(config('session.cookie'), $nombres, '/api/sesion debe emitir la cookie de sesión');
    }

    /**
     * Con el driver `file`, varias visitas anónimas a páginas públicas NO crean archivos de sesión. Como control positivo,
     * `/api/sesion` sí los crea (así se sabe que la prueba mide algo).
     */
    public function test_las_paginas_publicas_no_crean_archivos_de_sesion(): void
    {
        $carpeta = sys_get_temp_dir().'/explora_sesiones_'.bin2hex(random_bytes(4));
        mkdir($carpeta);
        config(['session.driver' => 'file', 'session.files' => $carpeta]);

        try {
            $contar = static fn (): int => count(glob($carpeta.'/*') ?: []);
            $antes = $contar();

            foreach (['/', '/climatico', '/vertientes', '/juegos/juegos/puzzle/', '/lineaTiempo/', '/ViajaPED/', '/climatico.html', '/index.html', '/juegos/juegos/puzzle', '/lineaTiempo'] as $ruta) {
                $this->pedir($ruta);
            }
            $this->assertSame($antes, $contar(), 'Las páginas públicas no deben crear archivos de sesión');

            // Control positivo: la API de sesión sí guarda una.
            $this->getJson('/api/sesion');
            $this->assertGreaterThan($antes, $contar(), '/api/sesion debería crear un archivo de sesión (control de la prueba)');
        } finally {
            (new Filesystem)->deleteDirectory($carpeta);
        }
    }
}
