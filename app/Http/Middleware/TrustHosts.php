<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Restringe los valores aceptados de la cabecera `Host` (middleware GLOBAL, justo después de CabecerasSeguridad).
 *
 * POR QUÉ: Laravel construye las URL absolutas (`redirect()`, `url()`, `asset()`) con el host de la petición. Sin esta
 * restricción, `Host: evil.example` producía `Location: http://evil.example/...` en las redirecciones 301 y
 * `<script src="http://evil.example/...">` en las páginas Blade: open redirect y envenenamiento de caché si hay un proxy
 * o CDN delante. Con la lista cerrada se responde 400 «Bad Request» genérico (sin trazas ni el host recibido, con APP_DEBUG=false).
 *
 * HOSTS ACEPTADOS:
 *  - El host de APP_URL (coincidencia exacta, sin subdominios; el puerto de la petición se ignora).
 *  - Los de la variable TRUSTED_HOSTS (config `explora.hosts_confiables`), p. ej. el alias `www.`.
 *  - SOLO si el entorno es `local` o `testing`: `localhost`, `127.0.0.1` y `[::1]` con cualquier puerto, para que
 *    `php -S`, `artisan serve`, Playwright (127.0.0.1:8090) y PHPUnit sigan funcionando.
 *
 * Si APP_URL no tiene un host válido y no hay TRUSTED_HOSTS, la lista queda vacía y Laravel/Symfony NO restringe nada
 * (confiar en todos): es preferible a bloquear todo el sitio por un error de configuración. Por eso en producción hay que
 * fijar APP_URL (ver DESPLIEGUE_APACHE.md, «Lista de comprobación de despliegue seguro»).
 *
 * Pruebas: tests/Feature/HostConfiableTest.php
 */
class TrustHosts extends Middleware
{
    /**
     * Patrones (expresiones regulares, sin delimitadores) de los hosts aceptados.
     *
     * @return array<int, string> Patrones anclados; cada host se escapa con preg_quote, de modo que un `.` es un punto literal.
     */
    public function hosts(): array
    {
        $hosts = [];

        // Host de APP_URL (parse_url devuelve null/false si la URL no tiene host válido: entonces se ignora).
        $host = parse_url((string) $this->app['config']->get('app.url'), PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $hosts[] = $host;
        }

        // Hosts adicionales de TRUSTED_HOSTS (literales, no expresiones regulares).
        foreach ((array) $this->app['config']->get('explora.hosts_confiables', []) as $extra) {
            if (is_string($extra) && $extra !== '') {
                $hosts[] = $extra;
            }
        }

        // Solo en desarrollo y pruebas: bucle local con cualquier puerto (Symfony quita el puerto antes de comparar).
        if ($this->app->environment('local', 'testing')) {
            array_push($hosts, 'localhost', '127.0.0.1', '[::1]');
        }

        return array_values(array_unique(array_map(
            static fn (string $h): string => '^'.preg_quote(strtolower($h), '#').'$',
            $hosts,
        )));
    }

    /**
     * Fija los hosts de confianza para esta petición, SIEMPRE (también en `local` y en las pruebas).
     *
     * El middleware base se salta la restricción en `local` y en `runningUnitTests()`; aquí `hosts()` ya añade los hosts de
     * desarrollo cuando corresponde, y omitirla del todo dejaría sin protección (y sin pruebas) a quien use APP_ENV=local
     * con un nombre de host real. `Request::setTrustedHosts()` guarda la lista en una propiedad ESTÁTICA (persiste entre
     * peticiones en el mismo proceso: workers de `php -S`, PHPUnit), por eso se vuelve a fijar en cada petición, incluso
     * vacía, para que una restricción anterior no sobreviva a un cambio de configuración.
     *
     * @param  Request  $request  Petición entrante.
     * @param  \Closure  $next  Siguiente middleware.
     * @return mixed Respuesta del resto de la pila.
     */
    public function handle(Request $request, $next)
    {
        Request::setTrustedHosts($this->hosts());

        // Se valida AQUÍ el host para responder 400: si se dejara a Symfony, la primera lectura (en el enrutador) lanza
        // SuspiciousOperationException y el manejador de Laravel la convierte en un 404 («Bad hostname provided»).
        try {
            $request->getHost();
        } catch (SuspiciousOperationException) {
            // Mensaje fijo: no se refleja el host recibido.
            throw new HttpException(400, 'Host no permitido.');
        }

        return $next($request);
    }
}
