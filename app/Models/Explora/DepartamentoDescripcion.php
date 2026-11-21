<?php

namespace App\Models\Explora;

use App\Models\Concerns\SoloLectura;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tabla `departamento_descripcion`: eje de todas las secciones. Su `departamento` apunta a list_departamentos.id
 * (sin FK en BD, DAT-04) y su propio `id` es la clave que usan las tablas hijas y `preguntas`.
 *
 * OJO: esta tabla tiene columnas `clima` y `geografia`; Eloquent da prioridad al atributo sobre la relación, por eso
 * las relaciones a departamento_clima / departamento_geografia se llaman climaDetalle() y geografiaDetalle().
 * Por la misma razón la relación a list_departamentos es listaDepartamento() (la columna `departamento` es un entero).
 * Hay una prueba que impide cualquier otro choque entre relaciones y columnas.
 */
class DepartamentoDescripcion extends Model
{
    use SoloLectura;

    protected $table = 'departamento_descripcion';

    protected $hidden = ['remember_token'];

    protected $guarded = [];

    /**
     * Fila de `list_departamentos` a la que pertenece esta descripción (nombre y capital). Se usa la columna `departamento` como clave foránea.
     *
     * @return BelongsTo Relación N:1 hacia {@see Departamento}.
     */
    public function listaDepartamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento');
    }

    /**
     * Sección «capital» (1:1, tabla departamento_capital).
     *
     * @return HasOne Relación 1:1 hacia {@see DepartamentoCapital}.
     */
    public function capital(): HasOne
    {
        return $this->hasOne(DepartamentoCapital::class, 'departamento');
    }

    /**
     * Sección «clima» (1:1, tabla departamento_clima). Se llama así porque esta tabla ya tiene una columna `clima`.
     *
     * @return HasOne Relación 1:1 hacia {@see DepartamentoClima}.
     */
    public function climaDetalle(): HasOne
    {
        return $this->hasOne(DepartamentoClima::class, 'departamento');
    }

    /**
     * Sección «cultura» (1:1, tabla departamento_cultura).
     *
     * @return HasOne Relación 1:1 hacia {@see DepartamentoCultura}.
     */
    public function cultura(): HasOne
    {
        return $this->hasOne(DepartamentoCultura::class, 'departamento');
    }

    /**
     * Sección «demografía» (1:1, tabla departamento_demografia).
     *
     * @return HasOne Relación 1:1 hacia {@see DepartamentoDemografia}.
     */
    public function demografia(): HasOne
    {
        return $this->hasOne(DepartamentoDemografia::class, 'departamento');
    }

    /**
     * Sección «economía» (1:1, tabla departamento_economia).
     *
     * @return HasOne Relación 1:1 hacia {@see DepartamentoEconomia}.
     */
    public function economia(): HasOne
    {
        return $this->hasOne(DepartamentoEconomia::class, 'departamento');
    }

    /**
     * Texto general de etnografía (1:1, tabla departamento_etnografia).
     *
     * @return HasOne Relación 1:1 hacia {@see DepartamentoEtnografia}.
     */
    public function etnografia(): HasOne
    {
        return $this->hasOne(DepartamentoEtnografia::class, 'departamento');
    }

    /**
     * Textos generales de fauna y flora (1:1, tabla departamento_fauna_flora).
     *
     * @return HasOne Relación 1:1 hacia {@see DepartamentoFaunaFlora}.
     */
    public function faunaFlora(): HasOne
    {
        return $this->hasOne(DepartamentoFaunaFlora::class, 'departamento');
    }

    /**
     * Sección «gastronomía» (1:1, tabla departamento_gastronomia).
     *
     * @return HasOne Relación 1:1 hacia {@see DepartamentoGastronomia}.
     */
    public function gastronomia(): HasOne
    {
        return $this->hasOne(DepartamentoGastronomia::class, 'departamento');
    }

    /**
     * Sección «geografía» (1:1, tabla departamento_geografia). Se llama así porque esta tabla ya tiene una columna `geografia`.
     *
     * @return HasOne Relación 1:1 hacia {@see DepartamentoGeografia}.
     */
    public function geografiaDetalle(): HasOne
    {
        return $this->hasOne(DepartamentoGeografia::class, 'departamento');
    }

    /**
     * Sección «hidrografía» (1:1, tabla departamento_hidrografia).
     *
     * @return HasOne Relación 1:1 hacia {@see DepartamentoHidrografia}.
     */
    public function hidrografia(): HasOne
    {
        return $this->hasOne(DepartamentoHidrografia::class, 'departamento');
    }

    /**
     * Sección «historia» (1:1, tabla departamento_historia).
     *
     * @return HasOne Relación 1:1 hacia {@see DepartamentoHistoria}.
     */
    public function historia(): HasOne
    {
        return $this->hasOne(DepartamentoHistoria::class, 'departamento');
    }

    /**
     * Referencias bibliográficas (1:1, tabla departamento_referencia).
     *
     * @return HasOne Relación 1:1 hacia {@see DepartamentoReferencia}.
     */
    public function referencia(): HasOne
    {
        return $this->hasOne(DepartamentoReferencia::class, 'departamento');
    }

    /**
     * Lista de etnias (1:N, tabla departamento_etnografia_list).
     *
     * @return HasMany Relación 1:N hacia {@see EtnografiaItem}.
     */
    public function etnias(): HasMany
    {
        return $this->hasMany(EtnografiaItem::class, 'departamento');
    }

    /**
     * Lista de animales destacados (1:N, tabla departamento_fauna_list).
     *
     * @return HasMany Relación 1:N hacia {@see FaunaItem}.
     */
    public function fauna(): HasMany
    {
        return $this->hasMany(FaunaItem::class, 'departamento');
    }

    /**
     * Lista de plantas destacadas (1:N, tabla departamento_flora_list).
     *
     * @return HasMany Relación 1:N hacia {@see FloraItem}.
     */
    public function flora(): HasMany
    {
        return $this->hasMany(FloraItem::class, 'departamento');
    }

    /**
     * Lista de festividades (1:N, tabla departamento_festividad_list).
     *
     * @return HasMany Relación 1:N hacia {@see FestividadItem}.
     */
    public function festividades(): HasMany
    {
        return $this->hasMany(FestividadItem::class, 'departamento');
    }

    /**
     * Lista de personajes (1:N, tabla departamento_personaje_list).
     *
     * @return HasMany Relación 1:N hacia {@see PersonajeItem}.
     */
    public function personajes(): HasMany
    {
        return $this->hasMany(PersonajeItem::class, 'departamento');
    }

    /**
     * Lista de sitios de interés (1:N, tabla departamento_sitios_list).
     *
     * @return HasMany Relación 1:N hacia {@see SitioItem}.
     */
    public function sitios(): HasMany
    {
        return $this->hasMany(SitioItem::class, 'departamento');
    }

    /**
     * Preguntas del quiz de este departamento (1:N). En `preguntas`, la columna `departamento` guarda el id de ESTA descripción, no el de list_departamentos.
     *
     * @return HasMany Relación 1:N hacia {@see Pregunta}.
     */
    public function preguntas(): HasMany
    {
        return $this->hasMany(Pregunta::class, 'departamento');
    }
}
