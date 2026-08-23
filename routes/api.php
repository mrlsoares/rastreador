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
    // Legado TRX (ESP32 antigo): chave global compartilhada.
    Route::middleware(['api_key', 'throttle:ingest'])->group(function () {
        Route::post('/telemetria', [TelemetriaController::class, 'store']);
    });

    // Ingestão ESP32: token por dispositivo (X-DEVICE-TOKEN).
    Route::middleware(['device_token', 'throttle:ingest'])->group(function () {
        Route::post('/esp32/telemetry', [Esp32TelemetryController::class, 'store']);
    });

    // Auto-provisionamento ESP32: o dispositivo obtém seu token de ingestão
    // usando a chave de bootstrap (X-API-KEY). Deve estar pré-cadastrado.
    Route::middleware(['api_key', 'throttle:ingest'])->group(function () {
        Route::post('/esp32/provision', [Esp32DispositivoController::class, 'provision']);
    });

    // -------------------------------------------------------------------------
    // Rotas autenticadas (token Bearer / Sanctum)
    // -------------------------------------------------------------------------
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

        // Qualquer usuário autenticado (revoga o próprio token).
        Route::post('/logout', [AuthController::class, 'logout']);

        // ---------------------------------------------------------------------
        // Leitura ESP32 liberada ao 'leitor' (app Windows / monitor de tanques).
        // Somente estas duas rotas — histórico e última telemetria.
        // O escopo de empresa é aplicado no controller (leitor vê a frota toda).
        // ---------------------------------------------------------------------
        Route::prefix('esp32')->group(function () {
            // Listagem enxuta agrupada por empresa (antes dos wildcards).
            Route::get('/monitor/dispositivos', [Esp32TelemetryController::class, 'dispositivos']);

            Route::get('/{identificador}/historico', [Esp32TelemetryController::class, 'historico']);
            Route::get('/{identificador}/ultima',    [Esp32TelemetryController::class, 'ultima']);
        });

        // ---------------------------------------------------------------------
        // Rotas privilegiadas: 'leitor' fica de fora (403). Só operador/admins.
        // ---------------------------------------------------------------------
        Route::middleware('role:super-admin|admin-empresa|operador')->group(function () {

            // --- Empresas (seleção no cadastro de dispositivos) ---
            Route::get('/empresas', [\App\Http\Controllers\Api\EmpresaController::class, 'index']);

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

            // --- Sub-sistema ESP32 (frota + CRUD) ---
            Route::prefix('esp32')->group(function () {
                Route::get('/fleet', [Esp32TelemetryController::class, 'fleet']);

                Route::get('/dispositivos',                    [Esp32DispositivoController::class, 'index']);
                Route::post('/dispositivos',                   [Esp32DispositivoController::class, 'store']);
                Route::get('/dispositivos/{identificador}',    [Esp32DispositivoController::class, 'show']);
                Route::put('/dispositivos/{identificador}',    [Esp32DispositivoController::class, 'update']);
                Route::delete('/dispositivos/{identificador}', [Esp32DispositivoController::class, 'destroy']);
                Route::post('/dispositivos/{identificador}/regenerar-token', [Esp32DispositivoController::class, 'regenerarToken']);
            });
        });
    });
});
