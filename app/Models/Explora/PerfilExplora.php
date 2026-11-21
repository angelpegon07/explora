<?php

namespace App\Models\Explora;

use App\Models\Pedigital\Usuario;
use Illuminate\Database\Eloquent\Model;

/**
 * Perfil de Explora de un usuario de pednia/ambientes (`pedigital.users.id` = `usuario_id`).
 * Guarda el avatar elegido, que antes se escribía en pedigital.users.avatar_explora (SEC-01/SEC-03).
 */
class PerfilExplora extends Model
{
    protected $table = 'perfiles_explora';

    protected $fillable = ['usuario_id', 'avatar'];

    protected $casts = ['usuario_id' => 'integer', 'avatar' => 'integer'];

    /**
     * Avatar vigente: el del perfil de Explora o, si no existe, el valor (de solo lectura) de pedigital.
     *
     * @param  int  $usuarioId  Id del usuario en `pedigital.users`.
     * @return int Número de avatar (1–35); 1 si no hay dato o el dato no es válido.
     */
    public static function avatarDe(int $usuarioId): int
    {
        $propio = static::where('usuario_id', $usuarioId)->value('avatar');

        $avatar = (int) ($propio ?? Usuario::whereKey($usuarioId)->value('avatar_explora') ?? 1);

        // Un valor vacío o 0 en pedigital pediría la imagen `0.png`, que no existe: se usa el avatar por defecto.
        return $avatar >= 1 ? $avatar : 1;
    }
}
