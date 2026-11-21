<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Páginas de los mapas temáticos (climatico, coordilleras, etnias, parques_naturales, vertientes).
 *
 * Las cinco comparten UNA vista (`mapas/tematico.blade.php`); lo que cambia de una a otra son los datos de
 * `config/mapas.php`, indexados por «slug» (el segmento de la URL). Las rutas (`routes/web.php`) solo aceptan los
 * slugs existentes en esa configuración, de modo que cualquier otro valor ya da 404 antes de llegar aquí; la
 * comprobación de este método es una segunda barrera por si la ruta cambiara.
 */
class MapaTematicoController extends Controller
{
    /**
     * Muestra el mapa pedido.
     *
     * @param  string  $slug  Clave del mapa en config/mapas.php (p. ej. `climatico`).
     * @return View La vista con el mapa; 404 si el slug no está configurado.
     */
    public function __invoke(string $slug): View
    {
        $mapa = config('mapas.'.$slug);

        // `config()` también resuelve claves con puntos, así que se exige que el slug sea una entrada real y tipo arreglo.
        abort_unless(is_array($mapa) && array_key_exists($slug, config('mapas')), 404);

        return view('mapas.tematico', ['slug' => $slug, 'mapa' => $mapa]);
    }
}
