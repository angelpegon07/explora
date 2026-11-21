<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PreguntasDepartamentoResource;
use App\Models\Explora\DepartamentoDescripcion;
use Illuminate\Http\JsonResponse;

/** Reemplazo de mapplic/php/buscarPreguntas.php. */
class PreguntasDepartamentoController extends Controller
{
    /**
     * Devuelve el quiz de un departamento (preguntas con sus opciones, todo ordenado por id).
     *
     * @param  int  $id  Id del departamento (list_departamentos.id); la ruta solo admite números.
     * @return JsonResponse 200 con `{preguntas, departamento}`; 404 `{error}` si el departamento no existe.
     */
    public function __invoke(int $id): JsonResponse
    {
        $descripcion = DepartamentoDescripcion::where('departamento', $id)->orderBy('id')->first();

        if (! $descripcion) {
            // El legado respondía con cuerpo vacío (FUN-02); aquí es un 404 con JSON.
            return response()->json(
                ['error' => "No se encontraron preguntas para el departamento con ID: $id"],
                404,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $descripcion->load([
            'listaDepartamento',
            'preguntas' => fn ($q) => $q->orderBy('id'),
            'preguntas.opcionesMultiple' => fn ($q) => $q->orderBy('id'),
            'preguntas.opcionesVerdaderoFalso' => fn ($q) => $q->orderBy('id'),
        ]);

        return (new PreguntasDepartamentoResource($descripcion))->response()->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }
}
