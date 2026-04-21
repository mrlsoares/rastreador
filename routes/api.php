<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RastreadorApiController;
use App\Http\Controllers\Api\PosicaoApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Rastreadores
    Route::get('/rastreadores', [RastreadorApiController::class, 'index']);
    Route::post('/rastreadores', [RastreadorApiController::class, 'store']);
    Route::get('/rastreadores/{id}', [RastreadorApiController::class, 'show']);
    Route::put('/rastreadores/{id}', [RastreadorApiController::class, 'update']);

    // Posições com filtros
    Route::get('/posicoes', [PosicaoApiController::class, 'index']);
    Route::get('/rastreadores/{id}/posicoes', [PosicaoApiController::class, 'porRastreador']);
    Route::get('/rastreadores/{id}/ultima-posicao', [PosicaoApiController::class, 'ultimaPosicao']);

    // Telemetria Externa (Protegida)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/telemetria/{imei}/historico', [\App\Http\Controllers\Api\TelemetryApiController::class, 'historico']);
        Route::get('/telemetria/{imei}/ultimos', [\App\Http\Controllers\Api\TelemetryApiController::class, 'ultimos']);
    });
});
