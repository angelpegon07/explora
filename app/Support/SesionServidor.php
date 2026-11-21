<?php

namespace App\Support;

use App\Models\Pedigital\Usuario;
use Illuminate\Http\Request;

/**
 * Consulta de la sesión del SERVIDOR para las páginas HTML (Fase 12).
 *
 * Desde la Fase 12 el servidor es la fuente de verdad de la sesión: las vistas del perfil y del portal de juegos se
 * pintan con el usuario autenticado del guard `web` (la misma cookie de sesión que usa `/api/sesion`), y
 * `localStorage` queda solo como caché cosmética para el JS de los juegos.
 */
final class SesionServidor
{
    /**
     * Usuario de la sesión actual SOLO si su cuenta está activa.
     *
     * Una cuenta inactiva (DAT-01) se trata como «sin sesión», igual que hace el middleware `cuenta.activa` de la API,
     * pero SIN cerrar la sesión ni escribir en ninguna base: esto es una página HTML de lectura. La limpieza real de la
     * sesión la hará la primera llamada a `/api/sesion` que haga el navegador (ExploraApi.sesionLista).
     *
     * @param  Request  $request  Petición actual (ya pasó por el grupo `web`, que inicia la sesión).
     * @return Usuario|null El usuario con sesión válida y cuenta activa, o null en cualquier otro caso.
     */
    public static function usuarioActivo(Request $request): ?Usuario
    {
        $usuario = $request->user('web');

        return $usuario instanceof Usuario && $usuario->esActivo() ? $usuario : null;
    }
}
