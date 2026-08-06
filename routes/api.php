<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RastreadorApiController;
use App\Http\Controllers\Api\PosicaoApiController;
use App\Http\Controllers\Api\TelemetryApiController;
use App\Http\Controllers\Api\Esp32TelemetryController;
use App\Http\Controllers\Api\Esp32DispositivoController;
use App\Http\Controllers\Api\TelemetriaController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Regra de acesso (Fase 1 - segurança):
|   - Autenticação de usuário: POST /v1/login emite token Sanctum (Bearer).
|   - Ingestão máquina-a-máquina (ESP32/dispositivos): middleware 'api_key'
|     (header X-API-KEY).
|   - Leitura e CRUD (frota, posições, dispositivos): 'auth:sanctum'.
|   Nenhuma rota fica sem autenticação.
*/

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------------------
    // Autenticação
    // -------------------------------------------------------------------------
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    // -------------------------------------------------------------------------
    // Ingestão máquina-a-máquina (dispositivos enviam dados) — X-API-KEY
    // -------------------------------------------------------------------------
    Route::middleware(['api_key', 'throttle:ingest'])->group(function () {
        Route::post('/telemetria',      [TelemetriaController::class, 'store']);
        Route::post('/esp32/telemetry', [Esp32TelemetryController::class, 'store']);
    });

    // -------------------------------------------------------------------------
    // Rotas autenticadas (token Bearer / Sanctum)
    // -------------------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);

        // --- Rastreadores TRX-16 ---
        Route::get('/rastreadores',       [RastreadorApiController::class, 'index']);
        Route::post('/rastreadores',      [RastreadorApiController::class, 'store']);
        Route::get('/rastreadores/{id}',  [RastreadorApiController::class, 'show']);
        Route::put('/rastreadores/{id}',  [RastreadorApiController::class, 'update']);

        // --- Posições TRX-16 ---
        Route::get('/posicoes',                         [PosicaoApiController::class, 'index']);
        Route::get('/rastreadores/{id}/posicoes',       [PosicaoApiController::class, 'porRastreador']);
        Route::get('/rastreadores/{id}/ultima-posicao', [PosicaoApiController::class, 'ultimaPosicao']);

        // --- Telemetria TRX-16 ---
        Route::get('/telemetria/{imei}/historico', [TelemetryApiController::class, 'historico']);
        Route::get('/telemetria/{imei}/ultimos',   [TelemetryApiController::class, 'ultimos']);

        // --- Sub-sistema ESP32 (leitura + CRUD) ---
        Route::prefix('esp32')->group(function () {
            Route::get('/fleet', [Esp32TelemetryController::class, 'fleet']);

            Route::get('/{identificador}/historico', [Esp32TelemetryController::class, 'historico']);
            Route::get('/{identificador}/ultima',    [Esp32TelemetryController::class, 'ultima']);

            Route::get('/dispositivos',                    [Esp32DispositivoController::class, 'index']);
            Route::post('/dispositivos',                   [Esp32DispositivoController::class, 'store']);
            Route::get('/dispositivos/{identificador}',    [Esp32DispositivoController::class, 'show']);
            Route::put('/dispositivos/{identificador}',    [Esp32DispositivoController::class, 'update']);
            Route::delete('/dispositivos/{identificador}', [Esp32DispositivoController::class, 'destroy']);
        });
    });
});
