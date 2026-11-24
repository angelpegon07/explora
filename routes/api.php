<?php

use App\Http\Controllers\Api\FichaDepartamentoController;
use App\Http\Controllers\Api\HistorialController;
use App\Http\Controllers\Api\PerfilAvatarController;
use App\Http\Controllers\Api\PreguntasDepartamentoController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\ResultadoJuegoController;
use App\Http\Controllers\Api\SesionController;
use App\Http\Middleware\AvisoDeprecacionApi;
use App\Http\Middleware\CacheLecturaPublica;
use App\Http\Middleware\ErroresProblemJson;
use App\Http\Middleware\MemoriaParaFichas;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de Explora (prefijo /api, aplicado por RouteServiceProvider)
|--------------------------------------------------------------------------
| Todas las rutas responden JSON (incluidos 404 y errores: ver App\Exceptions\Handler).
*/

/*
| Versionado (Fase 17E). Las rutas se definen UNA sola vez en $rutas y se registran dos veces, así ambas versiones
| tienen exactamente los mismos middleware, patrones y controladores (lo garantiza tests/Feature/ApiVersionadaTest):
|  - /api/v1/...  (nombres `v1.*`): errores en RFC 9457 (ErroresProblemJson, el middleware más externo de la ruta).
|  - /api/...     (nombres sin prefijo): legado intacto, solo con cabeceras Deprecation/Link (AvisoDeprecacionApi).
*/
$rutas = function () {

    /*
    | Lectura pública (Fase 3). Sustituyen a mapplic/php/consultarInfo.php y buscarPreguntas.php.
    | Solo ids numéricos; límite de peticiones del grupo `api` (60/min).
    */
    Route::middleware('throttle:api')->prefix('departamentos')->whereNumber('id')->group(function () {
        Route::get('{id}/ficha', FichaDepartamentoController::class)
            ->middleware([CacheLecturaPublica::class, MemoriaParaFichas::class])
            ->where('id', '[0-9]{1,9}')
            ->name('departamentos.ficha');
        Route::get('{id}/preguntas', PreguntasDepartamentoController::class)
            ->middleware(CacheLecturaPublica::class)
            ->where('id', '[0-9]{1,9}')->name('departamentos.preguntas');
    });

    /*
    | Ranking de los juegos (Fase 5): público, solo lectura. Sustituye a juegos/php/consultar_ranking.php.
    */
    Route::middleware('throttle:api')->get('ranking', RankingController::class)->name('ranking');

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

        /*
        | Juegos (Fase 5). Usan la sesión si existe: sin sesión rige el MODO INVITADO (FUN-09).
        | POST /api/resultados  guarda una partida (límite `resultados`; revalida la cuenta si hay sesión).
        | GET  /api/historial   partidas del usuario con sesión (nunca de otro).
        */
        Route::post('resultados', [ResultadoJuegoController::class, 'store'])
            ->middleware(['throttle:resultados', 'cuenta.activa'])->name('resultados.store');
        Route::get('historial', HistorialController::class)->middleware(['auth:web', 'cuenta.activa'])->name('historial');
    });

};

Route::prefix('v1')->name('v1.')->middleware(ErroresProblemJson::class)->group($rutas);
Route::middleware(AvisoDeprecacionApi::class)->group($rutas);
