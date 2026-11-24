<?php

namespace App\Providers;

use App\Contracts\ProveedorIdentidad;
use App\Support\Identidad\PedigitalProveedorIdentidad;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Origen de las identidades: hoy pedigital (solo lectura). Para cambiarlo, enlazar aquí otro adaptador.
        $this->app->singleton(ProveedorIdentidad::class, PedigitalProveedorIdentidad::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
