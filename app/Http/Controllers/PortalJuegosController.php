<?php

namespace App\Http\Controllers;

use App\Support\SesionServidor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Portal de juegos (`GET /juegos`), renderizado en el servidor (Fase 12).
 *
 * Reemplaza al antiguo `public/juegos/index.html`. La única parte dinámica es la barra superior: el servidor decide
 * qué botones se ven según la SESIÓN DE SERVIDOR (con sesión y cuenta activa: «Mi Perfil» y «Salir»; sin ella:
 * «Iniciar sesión»), de modo que no hay parpadeo al cargar. `mapplic/js/iniciarSesion.js` sigue actualizando esos
 * botones después de iniciar o cerrar sesión desde la propia página.
 *
 * No muestra datos del usuario (ni nombre ni avatar): solo un booleano.
 */
class PortalJuegosController extends Controller
{
    /**
     * Cabecera de caché: la página varía según la sesión, así que ninguna caché compartida puede reutilizarla; el
     * navegador debe revalidarla (`no-cache`) para no mostrar la barra de otro estado al volver con «atrás».
     */
    private const CACHE_CONTROL = 'private, no-cache';

    /**
     * Muestra el portal con la barra según la sesión del servidor.
     *
     * @param  Request  $request  Petición (la sesión la inicia el grupo de middleware `web`).
     * @return Response La vista `juegos.portal` con `Cache-Control: private, no-cache`.
     */
    public function __invoke(Request $request): Response
    {
        return response()
            ->view('juegos.portal', ['haySesion' => SesionServidor::usuarioActivo($request) !== null])
            ->header('Cache-Control', self::CACHE_CONTROL);
    }
}
