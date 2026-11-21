<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normaliza un nombre de usuario para usarlo como CLAVE de un limitador de intentos.
 *
 * Por qué hace falta: la base de pedigital compara `login_usuario` sin distinguir mayúsculas ni acentos
 * (collation spanish_ci), de modo que «juan», «JUAN» y «júan» son la misma cuenta. Si el limitador usara el texto
 * tal cual, un atacante podría repartir los intentos entre esas variantes y esquivar el límite por cuenta.
 */
final class NormalizaLogin
{
    /**
     * @param  string  $login  Usuario tal como lo escribió la persona.
     * @return string Minúsculas, sin acentos y sin espacios en los extremos (p. ej. «  JÚAN » → «juan»).
     */
    public static function clave(string $login): string
    {
        return mb_strtolower(Str::ascii(trim($login)));
    }
}
