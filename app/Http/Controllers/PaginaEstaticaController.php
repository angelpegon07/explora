<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Páginas propias con carpeta de assets (`GET /lineaTiempo/` y `GET /ViajaPED/`), migradas a Blade en la Fase 15.
 *
 * Reemplaza a `public/lineaTiempo/index.html` y `public/ViajaPED/index.html`. Las páginas no reciben ningún dato del
 * servidor: la vista es estática (HTML + asset()) y la lógica sigue en el `script.js` de cada carpeta, que carga
 * `eventos.json` / `data.json` y sus imágenes con rutas relativas a la URL de la página.
 *
 * La lista de páginas válidas es `config/paginas_estaticas.php` y la restringe la propia ruta con `where()`, de modo que
 * este controlador solo recibe slugs conocidos (y el nombre de la vista nunca sale de datos libres de la petición).
 */
class PaginaEstaticaController extends Controller
{
    /**
     * Muestra la página, o redirige a la URL canónica CON barra final.
     *
     * Con `/lineaTiempo` (sin barra) el navegador resolvería `eventos.json` contra `/` y todo daría 404, así que esa forma
     * redirige (301) a la que lleva barra, conservando la cadena de consulta. Laravel casa la ruta con y sin barra final,
     * por eso la distinción se hace aquí.
     *
     * @param  Request  $request  Petición (se usa su ruta y su cadena de consulta).
     * @param  string  $pagina  Página (slug ya validado por `where()` contra `config('paginas_estaticas')`).
     */
    public function __invoke(Request $request, string $pagina): View|RedirectResponse
    {
        if (! str_ends_with($request->getPathInfo(), '/')) {
            return $this->redirigirACanonica($request, $pagina);
        }

        return view('paginas.'.$pagina);
    }

    /**
     * Redirección 301 a `/{pagina}/` conservando la cadena de consulta.
     *
     * Sirve tanto para la forma sin barra como para la antigua `/{pagina}/index.html` (enlaces guardados). El slug procede de
     * la ruta (lista cerrada): no se refleja texto libre del usuario.
     *
     * @param  Request  $request  Petición original.
     * @param  string  $pagina  Página (slug validado).
     */
    public function redirigirACanonica(Request $request, string $pagina): RedirectResponse
    {
        $consulta = $request->getQueryString();

        // OJO: `redirect()->to()` y `url()` RECORTAN la barra final; con `away()` la URL se usa tal cual. Si se perdiera la
        // barra, la forma sin barra y la canónica se redirigirían entre sí (bucle).
        return redirect()->away(url('/'.$pagina).'/'.($consulta !== null && $consulta !== '' ? '?'.$consulta : ''), 301);
    }
}
