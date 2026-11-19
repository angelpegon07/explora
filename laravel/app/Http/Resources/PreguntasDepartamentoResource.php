<?php

namespace App\Http\Resources;

use App\Models\Explora\DepartamentoDescripcion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * Quiz de un departamento con la forma que consume mapplic/js/quiz.js, por lista blanca.
 * `correcta`/`respuesta` se incluyen solo mientras el cliente corrija en el navegador (config explora.quiz_incluir_respuestas, SEC-06).
 *
 * @property DepartamentoDescripcion $resource
 */
class PreguntasDepartamentoResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Construye `{preguntas, departamento}` como lo espera mapplic/js/quiz.js.
     *
     * Cada pregunta lleva `id`, `tipo_pregunta`, `pregunta`, `origen_pregunta` y `opciones` (multiple: `id`, `opcion`,
     * `correcta`; verfal: `id`, `respuesta`). Los indicadores de respuesta solo salen si
     * `config('explora.quiz_incluir_respuestas')` es true.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $respuestas = (bool) config('explora.quiz_incluir_respuestas');
        $dep = $this->resource->listaDepartamento;

        return [
            'preguntas' => $this->resource->preguntas->map(function ($p) use ($respuestas) {
                $multiple = $p->tipo_pregunta === 'multiple';
                $opciones = ($multiple ? $p->opcionesMultiple : $p->opcionesVerdaderoFalso)
                    ->map(fn ($o) => Arr::only(
                        $o->getAttributes(),
                        $multiple
                            ? ['id', 'opcion', ...($respuestas ? ['correcta'] : [])]
                            : ['id', ...($respuestas ? ['respuesta'] : [])]
                    ))->values()->all();

                return Arr::only($p->getAttributes(), ['id', 'tipo_pregunta', 'pregunta', 'origen_pregunta']) + ['opciones' => $opciones];
            })->values()->all(),
            'departamento' => $dep ? Arr::only($dep->getAttributes(), ['id', 'nombre', 'capital']) : null,
        ];
    }
}
