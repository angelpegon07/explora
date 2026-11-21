<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Host de confianza (`App\Http\Middleware\TrustHosts`, middleware global): la cabecera `Host` controla las URL absolutas
 * (redirecciones y `asset()`), así que un host ajeno debe dar 400 y nunca llegar a reflejarse en una URL.
 *
 * Se prueba con URL absolutas (`http://host/ruta`) porque `Request::create()` toma el `Host` de la URL.
 */
class HostConfiableTest extends TestCase
{
    /** Rutas representativas: una página Blade, un mapa y la redirección 301 de un juego (usa URL absoluta). */
    private const RUTAS = ['/', '/climatico', '/juegos/juegos/puzzle/index.html'];

    /**
     * Fija el entorno y APP_URL de la petición simulada (por defecto los de las pruebas: `testing` y `http://localhost`).
     *
     * @param  string  $entorno  Entorno de la aplicación.
     * @param  string  $appUrl  Valor de APP_URL.
     * @param  list<string>  $extra  Valor de TRUSTED_HOSTS ya separado.
     */
    private function configurar(string $entorno, string $appUrl, array $extra = []): void
    {
        $this->app->detectEnvironment(static fn (): string => $entorno);
        config(['app.url' => $appUrl, 'explora.hosts_confiables' => $extra]);
    }

    /**
     * `Request::setTrustedHosts()` es ESTÁTICO: se limpia al terminar para no condicionar a otras pruebas.
     */
    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        parent::tearDown();
    }

    /**
     * Un host ajeno da 400 en la portada, en un mapa y en la redirección de un juego, sin reflejarlo ni filtrar internos.
     */
    public function test_un_host_ajeno_da_400_y_no_se_refleja(): void
    {
        foreach (self::RUTAS as $ruta) {
            $r = $this->get('http://evil.example'.$ruta);
            $r->assertStatus(400);
            $this->assertFalse($r->headers->has('Location'), "$ruta no debe redirigir a un host ajeno");

            $cuerpo = $r->getContent();
            $this->assertStringNotContainsString('evil.example', $cuerpo, "$ruta refleja el host ajeno");
            foreach (['Symfony', 'Illuminate', 'Trusted', 'Stack trace', base_path()] as $interno) {
                $this->assertStringNotContainsString($interno, $cuerpo, "$ruta filtra «{$interno}»");
            }
        }
        // También en la API (JSON) y con puerto.
        $this->getJson('http://evil.example:8080/api/sesion')->assertStatus(400);
    }

    /**
     * Parecidos al host de APP_URL (subdominio, sufijo, prefijo) tampoco pasan: la coincidencia es exacta y anclada.
     */
    public function test_los_hosts_parecidos_al_de_app_url_no_pasan(): void
    {
        $this->configurar('production', 'https://explora.example');

        foreach (['evil.explora.example', 'explora.example.evil.example', 'xexplora.example', 'explora-example'] as $host) {
            $this->get("https://$host/")->assertStatus(400);
        }
    }

    /**
     * El host de APP_URL responde 200 (con y sin puerto) y las redirecciones salen hacia ese mismo host.
     */
    public function test_el_host_de_app_url_responde_200(): void
    {
        $this->configurar('production', 'https://explora.example');

        foreach (['/', '/climatico', '/juegos/juegos/puzzle/'] as $ruta) {
            // Request::create directo: `$this->get()` recortaría la barra final de la URL canónica del juego.
            $r = $this->createTestResponse($this->app->make(Kernel::class)->handle(Request::create('https://explora.example'.$ruta, 'GET')));
            $r->assertOk();
        }
        $this->get('https://explora.example:8443/')->assertOk();
        $this->get('https://explora.example/juegos/juegos/puzzle/index.html')->assertStatus(301)->assertRedirect('https://explora.example/juegos/juegos/puzzle/');
        // La coincidencia no distingue mayúsculas (los hosts no las distinguen).
        $this->get('https://EXPLORA.example/')->assertOk();
    }

    /**
     * En `testing` (y `local`) también valen localhost, 127.0.0.1 y [::1], con cualquier puerto: así siguen funcionando
     * `php -S`, `artisan serve`, Playwright (127.0.0.1:8090) y PHPUnit.
     */
    public function test_los_hosts_locales_valen_en_testing_y_local(): void
    {
        foreach (['testing', 'local'] as $entorno) {
            $this->configurar($entorno, 'https://explora.example');

            foreach (['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8090', '[::1]', '[::1]:8110'] as $host) {
                foreach (self::RUTAS as $ruta) {
                    $this->assertContains(
                        $this->get("http://$host$ruta")->getStatusCode(),
                        [200, 301],
                        "$host$ruta debería aceptarse en $entorno",
                    );
                }
            }
        }
    }

    /**
     * En producción (u otro entorno) los hosts locales NO se aceptan: solo el de APP_URL y los de TRUSTED_HOSTS.
     */
    public function test_los_hosts_locales_no_valen_en_produccion(): void
    {
        $this->configurar('production', 'https://explora.example');

        foreach (['localhost', '127.0.0.1:8090', '[::1]'] as $host) {
            $this->get("http://$host/")->assertStatus(400);
        }
    }

    /**
     * TRUSTED_HOSTS añade hosts (p. ej. el alias `www.`) y se toman como texto literal, no como expresión regular.
     */
    public function test_trusted_hosts_se_respeta(): void
    {
        $this->configurar('production', 'https://explora.example', ['www.explora.example', 'otro.example']);

        $this->get('https://www.explora.example/')->assertOk();
        $this->get('https://otro.example/climatico')->assertOk();
        $this->get('https://explora.example/')->assertOk();
        $this->get('https://wwwxexplora.example/')->assertStatus(400);   // el punto es literal
        $this->get('https://evil.example/')->assertStatus(400);
    }

    /**
     * La variable TRUSTED_HOSTS (texto separado por comas) se convierte en lista en config/explora.php, ignorando espacios y vacíos.
     */
    public function test_la_config_separa_trusted_hosts_por_comas(): void
    {
        putenv('TRUSTED_HOSTS= www.a.example ,, b.example ,');
        try {
            $cfg = require config_path('explora.php');
        } finally {
            putenv('TRUSTED_HOSTS');
        }
        $this->assertSame(['www.a.example', 'b.example'], $cfg['hosts_confiables']);
    }

    /**
     * Sin un host válido en APP_URL (vacío o basura) y sin TRUSTED_HOSTS NO se restringe nada: es preferible a bloquear
     * todo el sitio por un error de configuración (documentado en el middleware y en la lista de despliegue seguro).
     */
    public function test_sin_app_url_valido_no_se_rompe_el_sitio(): void
    {
        foreach (['', 'esto no es una url', '/solo/ruta'] as $appUrl) {
            $this->configurar('production', $appUrl);
            $this->get('http://cualquier.example/')->assertOk();
        }

        // Pero si hay TRUSTED_HOSTS, esa lista sí manda aunque APP_URL sea inválido.
        $this->configurar('production', '', ['explora.example']);
        $this->get('http://explora.example/')->assertOk();
        $this->get('http://evil.example/')->assertStatus(400);
    }
}
