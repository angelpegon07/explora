<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Portada del sitio (`GET /`): el mapa general de departamentos con su ficha y el test (Fase 11).
 *
 * Reemplaza al antiguo `public/index.html`. No necesita datos del servidor: la vista `inicio` es estática y la ficha de
 * cada departamento y las preguntas del quiz las pide el navegador a la API (`/api/departamentos/{id}/ficha` y
 * `/api/departamentos/{id}/preguntas`). La URL `/index.html` (marcadores antiguos) redirige con 301 a `/`
 * (ver `routes/web.php`).
 */
class InicioController extends Controller
{
    /**
     * Muestra la portada.
     *
     * @return View La vista `inicio` (HTML completo de la página).
     */
    public function __invoke(): View
    {
        return view('inicio');
    }
}
