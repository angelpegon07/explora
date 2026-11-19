<?php

use App\Http\Controllers\Api\FichaDepartamentoController;
use App\Http\Controllers\Api\PerfilAvatarController;
use App\Http\Controllers\Api\PreguntasDepartamentoController;
use App\Http\Controllers\Api\SesionController;
use App\Http\Middleware\MemoriaParaFichas;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de Explora (prefijo /api, aplicado por RouteServiceProvider)
|--------------------------------------------------------------------------
| Todas las rutas responden JSON (incluidos 404 y errores: ver App\Exceptions\Handler).
*/

/*
| Lectura pública (Fase 3). Sustituyen a mapplic/php/consultarInfo.php y buscarPreguntas.php.
| Solo ids numéricos; límite de peticiones del grupo `api` (60/min).
*/
Route::middleware('throttle:api')->prefix('departamentos')->whereNumber('id')->group(function () {
    Route::get('{id}/ficha', FichaDepartamentoController::class)
        ->middleware(MemoriaParaFichas::class)
        ->name('departamentos.ficha');
    Route::get('{id}/preguntas', PreguntasDepartamentoController::class)->name('departamentos.preguntas');
});

/*
| Sesión y perfil (Fase 4). Usan el grupo `web` además del `api`: cookie de sesión cifrada y HttpOnly, y
| protección CSRF (el cliente debe enviar la cookie XSRF-TOKEN en la cabecera X-XSRF-TOKEN; se obtiene con
| GET /api/sesion). El inicio de sesión tiene su propio límite de intentos (`throttle:login`).
*/
Route::middleware('web')->group(function () {
    Route::post('sesion', [SesionController::class, 'store'])->middleware('throttle:login')->name('sesion.store');
    Route::get('sesion', [SesionController::class, 'show'])->middleware('cuenta.activa')->name('sesion.show');
    Route::delete('sesion', [SesionController::class, 'destroy'])->name('sesion.destroy');

    Route::patch('perfil/avatar', PerfilAvatarController::class)->middleware(['auth:web', 'cuenta.activa'])->name('perfil.avatar');
});
