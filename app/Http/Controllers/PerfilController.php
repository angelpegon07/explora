<?php

namespace App\Http\Controllers;

use App\Models\Explora\PerfilExplora;
use App\Support\SesionServidor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Página «Mi perfil» (`GET /perfil`), renderizada en el servidor con la sesión de Laravel (Fase 12).
 *
 * Reemplaza al antiguo `public/miPerfil.html`, que se rellenaba desde JavaScript con `localStorage`/la API. Ahora:
 *  - Con sesión válida y cuenta activa se pintan en servidor el nombre, el usuario (login), el grado y el avatar del
 *    usuario AUTENTICADO, y la lista de avatares (tantos como `config('explora.avatares_max')`).
 *  - Sin sesión (o con la cuenta inactiva) se pinta solo el aviso «Debe iniciar sesión…»: no se emite el formulario,
 *    ni el historial, ni dato alguno.
 *
 * Seguridad: los datos salen SIEMPRE de la sesión; la ruta no recibe ningún id, así que no hay forma de pedir el
 * perfil de otra persona. A la vista solo llegan los cuatro datos que se muestran (nunca el modelo, que contiene el
 * hash de la contraseña y el correo). El nombre y el login vienen de una base externa y pueden contener HTML: la
 * vista los imprime con `{{ }}` (escapados).
 */
class PerfilController extends Controller
{
    /**
     * Cabecera de caché de esta respuesta: contiene datos personales, por lo que no debe guardarse en ninguna caché
     * (ni compartida ni del navegador: así no se vería con el botón «atrás» después de cerrar sesión).
     */
    private const CACHE_CONTROL = 'private, no-store';

    /**
     * Muestra el perfil del usuario con sesión, o el aviso de «iniciar sesión» si no la hay.
     *
     * @param  Request  $request  Petición (la sesión la inicia el grupo de middleware `web`).
     * @return Response La vista `perfil.index` con `Cache-Control: private, no-store`.
     */
    public function __invoke(Request $request): Response
    {
        $usuario = SesionServidor::usuarioActivo($request);
        $datos = null;

        if ($usuario !== null) {
            $max = (int) config('explora.avatares_max');
            $avatar = PerfilExplora::avatarDe($usuario->id);

            $datos = [
                'nombre' => (string) $usuario->nombre_usuario,
                'usuario' => (string) $usuario->login_usuario,
                // Se compara como texto con las opciones 1..11 del <select>; un valor ajeno deja el aviso «Selecciona tu grado».
                'grado' => (string) $usuario->grado_usuario,
                // Un avatar fuera de 1..max (dato heredado inválido) se sustituye por el 1 en vez de pedir una imagen inexistente.
                'avatar' => $avatar <= $max ? $avatar : 1,
                'avatares' => $max,
            ];
        }

        return response()
            ->view('perfil.index', ['datos' => $datos])
            ->header('Cache-Control', self::CACHE_CONTROL);
    }
}
