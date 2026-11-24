<?php

namespace App\Console\Commands;

use App\Http\Middleware\CacheLecturaPublica;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Invalida la caché de lectura pública (ficha y preguntas) subiendo su versión: las entradas viejas quedan huérfanas y
 * caducan solas por su TTL. Ejecutar tras cambiar el contenido de la base o aplicar `explora:externalizar-imagenes`.
 * No toca la base de datos ni el resto de la caché de la aplicación.
 */
class LimpiarCacheLectura extends Command
{
    protected $signature = 'explora:limpiar-cache-lectura';

    protected $description = 'Invalida la caché de lectura pública de la ficha y las preguntas de los departamentos';

    /**
     * @return int Siempre 0.
     */
    public function handle(): int
    {
        $version = (int) Cache::get(CacheLecturaPublica::CLAVE_VERSION, 1) + 1;
        Cache::forever(CacheLecturaPublica::CLAVE_VERSION, $version);
        $this->info("Caché de lectura invalidada (versión $version).");

        return self::SUCCESS;
    }
}
