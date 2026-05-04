<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RastreadorApiController;
use App\Http\Controllers\Api\PosicaoApiController;
use App\Http\Controllers\Api\TelemetryApiController;
use App\Http\Controllers\Api\Esp32TelemetryController;
use App\Http\Controllers\Api\Esp32DispositivoController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------------------
    // Rastreadores TRX-16
    // -------------------------------------------------------------------------
    Route::get('/rastreadores',       [RastreadorApiController::class, 'index']);
    Route::post('/rastreadores',      [RastreadorApiController::class, 'store']);
    Route::get('/rastreadores/{id}',  [RastreadorApiController::class, 'show']);
    Route::put('/rastreadores/{id}',  [RastreadorApiController::class, 'update']);

    // -------------------------------------------------------------------------
    // Posições TRX-16
    // -------------------------------------------------------------------------
    Route::get('/posicoes',                           [PosicaoApiController::class, 'index']);
    Route::get('/rastreadores/{id}/posicoes',         [PosicaoApiController::class, 'porRastreador']);
    Route::get('/rastreadores/{id}/ultima-posicao',   [PosicaoApiController::class, 'ultimaPosicao']);

    // -------------------------------------------------------------------------
    // Telemetria TRX-16 (protegida por Sanctum)
    // -------------------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/telemetria/{imei}/historico', [TelemetryApiController::class, 'historico']);
        Route::get('/telemetria/{imei}/ultimos',   [TelemetryApiController::class, 'ultimos']);
    });

    // -------------------------------------------------------------------------
    // Sub-sistema ESP32
    // -------------------------------------------------------------------------
    Route::prefix('esp32')->group(function () {

        // Ingestão de dados (dispositivo envia via HTTP POST)
        Route::post('/telemetry', [Esp32TelemetryController::class, 'store']);

        // Leitura da frota completa (snapshot para mapa)
        Route::get('/fleet', [Esp32TelemetryController::class, 'fleet']);

        // Histórico e última leitura por dispositivo
        Route::get('/{identificador}/historico', [Esp32TelemetryController::class, 'historico']);
        Route::get('/{identificador}/ultima',    [Esp32TelemetryController::class, 'ultima']);

        // CRUD de dispositivos
        Route::get('/dispositivos',                       [Esp32DispositivoController::class, 'index']);
        Route::post('/dispositivos',                      [Esp32DispositivoController::class, 'store']);
        Route::get('/dispositivos/{identificador}',       [Esp32DispositivoController::class, 'show']);
        Route::put('/dispositivos/{identificador}',       [Esp32DispositivoController::class, 'update']);
        Route::delete('/dispositivos/{identificador}',    [Esp32DispositivoController::class, 'destroy']);
    });
});
