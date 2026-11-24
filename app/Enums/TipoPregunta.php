<?php

namespace App\Enums;

/**
 * Valores de `preguntas.tipo_pregunta`. Los VALORES son los textos exactos que guarda la base y que lee `quiz.js`
 * (incluido «verfal», con la errata del legado): cambiarlos rompería el contrato de la API. Solo el nombre del caso es legible.
 */
enum TipoPregunta: string
{
    /** Opción múltiple: sus opciones están en `opciones_multiple`, cada una con su indicador `correcta`. */
    case Multiple = 'multiple';

    /** Verdadero/falso: una sola fila en `opciones_verfal` (sic) con `respuesta`. */
    case VerdaderoFalso = 'verfal';
}
