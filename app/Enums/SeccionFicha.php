<?php

namespace App\Enums;

use App\Models\Explora\DepartamentoDescripcion;

/**
 * Las 16 secciones (claves de primer nivel) de la ficha de un departamento, en el ORDEN en que salen en el JSON.
 *
 * Es la única fuente de tres datos que antes estaban repartidos entre el controlador y el recurso: la clave JSON (el valor del
 * caso, la que lee `mapplic/verMas.js`), la relación Eloquent de {@see DepartamentoDescripcion} que la
 * carga y la lista blanca de columnas que se exponen. Una columna nueva en la base no sale a la API hasta añadirla aquí.
 *
 * `Etnografia` y `FaunaFlora` son 1:1 pero además llevan listas (`etnias`, `flora`, `fauna`); las arma el recurso.
 */
enum SeccionFicha: string
{
    case Descripcion = 'descripcion';
    case NombreDepartamento = 'nombreDepartamento';
    case Historia = 'historia';
    case Geografia = 'geografia';
    case Hidrografia = 'hidrografia';
    case Clima = 'clima';
    case Demografia = 'demografia';
    case Etnografia = 'etnografia';
    case FaunaFlora = 'faunaFlora';
    case Economia = 'economia';
    case Cultura = 'cultura';
    case Gastronomia = 'gastronomia';
    case Festividades = 'festividades';
    case Sitios = 'sitios';
    case Personajes = 'personajes';
    case Capital = 'capital';

    /** Columnas de las listas 1:N de la ficha que no son secciones por sí mismas. */
    public const COLUMNAS_LISTA_ETNIAS = ['id', 'departamento', 'nombre', 'nombre_alterno', 'lengua', 'contenido'];

    /** Columnas de las listas de flora, fauna, festividades, sitios y personajes. */
    public const COLUMNAS_LISTA = ['id', 'departamento', 'nombre', 'destacada', 'contenido'];

    /**
     * Nombre de la relación de `DepartamentoDescripcion` que carga la sección, o null si es la propia descripción.
     */
    public function relacion(): ?string
    {
        return match ($this) {
            self::Descripcion => null,
            self::NombreDepartamento => 'listaDepartamento',
            self::Historia => 'historia',
            self::Geografia => 'geografiaDetalle',
            self::Hidrografia => 'hidrografia',
            self::Clima => 'climaDetalle',
            self::Demografia => 'demografia',
            self::Etnografia => 'etnografia',
            self::FaunaFlora => 'faunaFlora',
            self::Economia => 'economia',
            self::Cultura => 'cultura',
            self::Gastronomia => 'gastronomia',
            self::Festividades => 'festividades',
            self::Sitios => 'sitios',
            self::Personajes => 'personajes',
            self::Capital => 'capital',
        };
    }

    /**
     * Si la sección es una lista (1:N) de filas con {@see self::COLUMNAS_LISTA}, en vez de una sola fila.
     */
    public function esLista(): bool
    {
        return in_array($this, [self::Festividades, self::Sitios, self::Personajes], true);
    }

    /**
     * Lista blanca de columnas de la fila de la sección (para las listas, de cada elemento).
     *
     * @return array<int, string>
     */
    public function columnas(): array
    {
        return match ($this) {
            self::Descripcion => ['id', 'departamento', 'poblacion', 'clima', 'temperatura', 'extension',
                'contenido', 'geografia', 'actividad_economica', 'bandera', 'escudo', 'estado', 'estado_registro'],
            self::NombreDepartamento => ['id', 'nombre', 'capital'],
            self::Historia => ['id', 'departamento', 'historia'],
            self::Geografia => ['id', 'departamento', 'ubicacion', 'coordenadas', 'limites', 'relieve'],
            self::Hidrografia => ['id', 'departamento', 'hidrografia'],
            self::Clima => ['id', 'departamento', 'clima'],
            self::Demografia => ['id', 'departamento', 'poblacion', 'densidad', 'subregiones'],
            self::Etnografia => ['id', 'departamento', 'contenido'],
            self::FaunaFlora => ['id', 'departamento', 'fauna', 'flora'],
            self::Economia => ['id', 'departamento', 'economia'],
            self::Cultura => ['id', 'departamento', 'descripcion', 'arte', 'danza', 'musica'],
            self::Gastronomia => ['id', 'departamento', 'gastronomia'],
            self::Capital => ['id', 'departamento', 'capital'],
            self::Festividades, self::Sitios, self::Personajes => self::COLUMNAS_LISTA,
        };
    }

    /**
     * Relaciones que debe cargar el controlador de la ficha (una consulta por tabla). Las listas salen ordenadas por `id`.
     *
     * @return array<int|string, mixed> Argumento de `Model::load()`.
     */
    public static function relacionesACargar(): array
    {
        $ordenar = fn ($q) => $q->orderBy('id');
        $cargas = [];
        foreach (self::cases() as $seccion) {
            $relacion = $seccion->relacion();
            if ($relacion !== null) {
                $seccion->esLista() ? $cargas[$relacion] = $ordenar : $cargas[] = $relacion;
            }
        }

        // Listas que cuelgan de Etnografia y FaunaFlora (el recurso las inserta dentro de esas secciones).
        foreach (['etnias', 'flora', 'fauna'] as $lista) {
            $cargas[$lista] = $ordenar;
        }

        return $cargas;
    }
}
