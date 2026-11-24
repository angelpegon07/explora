<?php

namespace App\Http\Controllers\Api;

use App\Enums\SeccionFicha;
use App\Http\Controllers\Controller;
use App\Http\Resources\FichaDepartamentoResource;
use App\Models\Explora\DepartamentoDescripcion;
use Illuminate\Http\JsonResponse;

/** Reemplazo de mapplic/php/consultarInfo.php (misma forma de respuesta, sin columnas internas). */
class FichaDepartamentoController extends Controller
{
    /**
     * Devuelve la ficha completa de un departamento.
     *
     * Busca la descripción por `departamento = $id` (id de list_departamentos), carga todas sus secciones con
     * eager loading (sin consultas en bucle) y responde con {@see FichaDepartamentoResource}.
     *
     * @param  int  $id  Id del departamento (list_departamentos.id); la ruta solo admite números.
     * @return JsonResponse 200 con la ficha; 404 `{error}` si el departamento no existe (el legado respondía 200).
     */
    public function __invoke(int $id): JsonResponse
    {
        $descripcion = DepartamentoDescripcion::where('departamento', $id)->orderBy('id')->first();

        if (! $descripcion) {
            return response()->json(
                ['error' => "No se encontraron registros para el departamento con ID: $id"],
                404,
                [],
                JSON_UNESCAPED_UNICODE
            );
        }

        $descripcion->load(SeccionFicha::relacionesACargar());

        return (new FichaDepartamentoResource($descripcion))->response()->setEncodingOptions(JSON_UNESCAPED_UNICODE);
    }
}
