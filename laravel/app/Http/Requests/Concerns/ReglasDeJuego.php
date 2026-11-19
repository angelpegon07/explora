<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Reglas de validación compartidas por todo lo que identifica un resultado de juego (guardar y consultar ranking):
 * juego, nivel y categoría.
 *
 * Son LISTAS BLANCAS (juego y nivel) y un patrón estricto (categoría): ninguna admite comillas ni signos `<` `>`, por
 * lo que no pueden contener HTML ni romper una consulta (SEC-01 y SEC-05). El legado aceptaba cualquier texto.
 */
trait ReglasDeJuego
{
    /**
     * Patrón de una categoría: empieza por letra o número y admite letras (con tildes), números, espacios y los signos
     * `. , : ; ( ) / ¿ ? ¡ ! _ -`. Ejemplos válidos: «Mapa Político de Colombia», «Evolución de la bandera (1810-1819)».
     */
    private const PATRON_CATEGORIA = '/^[\p{L}\p{N}][\p{L}\p{N} .,:;()\/¿?¡!_-]{0,99}$/u';

    /**
     * Reglas de `juego`, `nivel` y `categoria`.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function reglasDeJuego(): array
    {
        return [
            'juego' => ['required', 'string', Rule::in(config('explora.juegos'))],
            'nivel' => ['required', 'string', Rule::in(config('explora.niveles'))],
            'categoria' => ['required', 'string', 'max:100', 'regex:'.self::PATRON_CATEGORIA],
        ];
    }
}
