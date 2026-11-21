<?php

namespace App\Http\Resources;

use App\Models\Explora\PerfilExplora;
use App\Models\Pedigital\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Datos del usuario que se entregan al navegador tras iniciar sesión o al consultar la sesión.
 *
 * LISTA BLANCA: solo los seis campos que necesita el frontend (los que guardaba `iniciarSesion.js` en
 * localStorage más `id` y `tipo_usuario`). El legado devolvía la fila completa de `users`, incluido el hash de la
 * contraseña, el correo y el token (SEC-04); aquí nada de eso sale aunque el modelo cambie.
 *
 * @property Usuario $resource
 */
class UsuarioSesionResource extends JsonResource
{
    /** Sin envoltura `data`: la forma de la respuesta es la del contrato con el frontend. */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $u = $this->resource;

        return [
            'id' => $u->id,
            'nombre_usuario' => $u->nombre_usuario,
            'login_usuario' => $u->login_usuario,
            'grado_usuario' => $u->grado_usuario,
            // Avatar vigente: el perfil propio de Explora o, si no existe, el valor heredado de pedigital.
            'avatar_explora' => PerfilExplora::avatarDe($u->id),
            'tipo_usuario' => $u->tipo_usuario,
        ];
    }
}
