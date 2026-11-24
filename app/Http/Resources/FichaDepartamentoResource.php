<?php

namespace App\Http\Resources;

use App\Enums\SeccionFicha;
use App\Models\Explora\DepartamentoDescripcion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Ficha de un departamento con la MISMA forma que consultarInfo.php (claves que lee mapplic/verMas.js),
 * por LISTA BLANCA de columnas: no se filtran remember_token ni marcas de tiempo, y una columna nueva en la
 * BD no sale a la API hasta que se añada aquí.
 *
 * @property DepartamentoDescripcion $resource
 */
class FichaDepartamentoResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Convierte un modelo en un arreglo con solo las columnas indicadas (lista blanca).
     *
     * @param  Model|null  $m  Fila de una sección (null si no existe).
     * @param  array<int, string>  $columnas  Columnas permitidas.
     * @return array<string, mixed>|null
     */
    private function fila(?Model $m, array $columnas): ?array
    {
        // getAttributes(): valores crudos de la BD (sin casts ni fechas ISO), igual que el legado.
        return $m ? Arr::only($m->getAttributes(), $columnas) : null;
    }

    /**
     * Convierte una colección de modelos en una lista de arreglos con solo las columnas indicadas.
     *
     * @param  Collection  $items  Filas de una lista 1:N.
     * @param  array<int, string>  $columnas  Columnas permitidas.
     * @return array<int, array<string, mixed>>
     */
    private function lista($items, array $columnas): array
    {
        return $items->map(fn ($m) => Arr::only($m->getAttributes(), $columnas))->values()->all();
    }

    /**
     * Construye la ficha con las 16 claves de {@see SeccionFicha} que lee mapplic/verMas.js.
     *
     * Cada sección sale como un arreglo con SOLO sus columnas permitidas, o null si la fila no existe (igual que el
     * legado). `etnografia` y `faunaFlora` llevan siempre sus listas, aunque no exista la fila general.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $d = $this->resource;
        $ficha = [];

        // El orden de las claves lo fija SeccionFicha; así el JSON es idéntico al del legado.
        foreach (SeccionFicha::cases() as $seccion) {
            $columnas = $seccion->columnas();
            $ficha[$seccion->value] = match ($seccion) {
                SeccionFicha::Descripcion => $this->fila($d, $columnas),
                // En el legado, etnografia y faunaFlora siempre llevan sus listas aunque no exista la fila (autovivificación).
                SeccionFicha::Etnografia => ($this->fila($d->etnografia, $columnas) ?? [])
                    + ['lista' => $this->lista($d->etnias, SeccionFicha::COLUMNAS_LISTA_ETNIAS)],
                SeccionFicha::FaunaFlora => ($this->fila($d->faunaFlora, $columnas) ?? [])
                    + ['lista_flora' => $this->lista($d->flora, SeccionFicha::COLUMNAS_LISTA),
                        'lista_fauna' => $this->lista($d->fauna, SeccionFicha::COLUMNAS_LISTA)],
                default => $seccion->esLista()
                    ? $this->lista($d->{$seccion->relacion()}, $columnas)
                    : $this->fila($d->{$seccion->relacion()}, $columnas),
            };
        }

        return $ficha;
    }
}
