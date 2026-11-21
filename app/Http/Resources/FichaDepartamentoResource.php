<?php

namespace App\Http\Resources;

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

    private const LISTA = ['id', 'departamento', 'nombre', 'destacada', 'contenido'];

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
     * Construye la ficha con las 16 claves que lee mapplic/verMas.js.
     *
     * Cada sección sale como un arreglo con SOLO sus columnas permitidas, o null si la fila no existe (igual que el
     * legado). `etnografia` y `faunaFlora` llevan siempre sus listas, aunque no exista la fila general.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $d = $this->resource;

        // En el legado, etnografia y faunaFlora siempre llevan sus listas aunque no exista la fila (autovivificación).
        $etnografia = ($this->fila($d->etnografia, ['id', 'departamento', 'contenido']) ?? [])
            + ['lista' => $this->lista($d->etnias, ['id', 'departamento', 'nombre', 'nombre_alterno', 'lengua', 'contenido'])];
        $faunaFlora = ($this->fila($d->faunaFlora, ['id', 'departamento', 'fauna', 'flora']) ?? [])
            + ['lista_flora' => $this->lista($d->flora, self::LISTA), 'lista_fauna' => $this->lista($d->fauna, self::LISTA)];

        return [
            'descripcion' => $this->fila($d, ['id', 'departamento', 'poblacion', 'clima', 'temperatura', 'extension',
                'contenido', 'geografia', 'actividad_economica', 'bandera', 'escudo', 'estado', 'estado_registro']),
            'nombreDepartamento' => $this->fila($d->listaDepartamento, ['id', 'nombre', 'capital']),
            'historia' => $this->fila($d->historia, ['id', 'departamento', 'historia']),
            'geografia' => $this->fila($d->geografiaDetalle, ['id', 'departamento', 'ubicacion', 'coordenadas', 'limites', 'relieve']),
            'hidrografia' => $this->fila($d->hidrografia, ['id', 'departamento', 'hidrografia']),
            'clima' => $this->fila($d->climaDetalle, ['id', 'departamento', 'clima']),
            'demografia' => $this->fila($d->demografia, ['id', 'departamento', 'poblacion', 'densidad', 'subregiones']),
            'etnografia' => $etnografia,
            'faunaFlora' => $faunaFlora,
            'economia' => $this->fila($d->economia, ['id', 'departamento', 'economia']),
            'cultura' => $this->fila($d->cultura, ['id', 'departamento', 'descripcion', 'arte', 'danza', 'musica']),
            'gastronomia' => $this->fila($d->gastronomia, ['id', 'departamento', 'gastronomia']),
            'festividades' => $this->lista($d->festividades, self::LISTA),
            'sitios' => $this->lista($d->sitios, self::LISTA),
            'personajes' => $this->lista($d->personajes, self::LISTA),
            'capital' => $this->fila($d->capital, ['id', 'departamento', 'capital']),
        ];
    }
}
