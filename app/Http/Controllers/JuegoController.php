<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Páginas de los siete juegos (`GET /juegos/juegos/{slug}/`), migradas a Blade en la Fase 13.
 *
 * Reemplaza a los `public/juegos/juegos/<slug>/index.html`. Los juegos no reciben ningún dato del servidor: la vista es
 * estática (HTML + asset()) y toda la lógica sigue en los JS de cada carpeta.
 *
 * La lista de juegos válidos es `config/juegos.php` y la restringe la propia ruta con `where()`, de modo que este
 * controlador solo recibe slugs conocidos (y el nombre de vista nunca sale de datos libres de la petición).
 */
class JuegoController extends Controller
{
    /**
     * Muestra el juego, o redirige a la URL canónica CON barra final.
     *
     * Casi todo lo que el juego carga en ejecución (`fetch('js/preguntas.json')`, `$.getJSON('datos.json')`, `../sounds/…`,
     * `url()` de CSS) es RELATIVO a la URL de la página. Con `/juegos/juegos/puzzle` (sin barra) el navegador resolvería
     * contra `/juegos/juegos/` y todo daría 404, así que esa forma redirige (301) a la que lleva barra, conservando la
     * cadena de consulta. Laravel casa la ruta con y sin barra final, por eso la distinción se hace aquí.
     *
     * @param  Request  $request  Petición (se usa su ruta y su cadena de consulta).
     * @param  string  $slug  Juego (ya validado por `where()` contra `config('juegos')`).
     */
    public function __invoke(Request $request, string $slug): View|RedirectResponse
    {
        if (! str_ends_with($request->getPathInfo(), '/')) {
            return $this->redirigirACanonica($request, $slug);
        }

        return view('juegos.juego.'.$slug);
    }

    /**
     * Redirección 301 a `/juegos/juegos/{slug}/` conservando la cadena de consulta.
     *
     * Sirve tanto para la forma sin barra como para la antigua `/juegos/juegos/{slug}/index.html` (enlaces guardados).
     * El slug procede de la ruta (lista cerrada), no se refleja texto libre del usuario.
     *
     * @param  Request  $request  Petición original.
     * @param  string  $slug  Juego (validado).
     */
    public function redirigirACanonica(Request $request, string $slug): RedirectResponse
    {
        $consulta = $request->getQueryString();

        // OJO: `redirect()->to()` y `url()` RECORTAN la barra final; con `away()` la URL se usa tal cual. Si se perdiera la
        // barra, la forma sin barra y la canónica se redirigirían entre sí (bucle).
        return redirect()->away(url('/juegos/juegos/'.$slug).'/'.($consulta !== null && $consulta !== '' ? '?'.$consulta : ''), 301);
    }
}
