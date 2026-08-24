<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RastreadorController;
use App\Http\Controllers\PosicaoController;
use App\Http\Controllers\Web\EmpresaController;
use App\Http\Controllers\Web\UsuarioController;
use Illuminate\Support\Facades\Route;

// Index = login: visitante vai pro login, autenticado pro dashboard.
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    // Painel: dashboard real da aplicação.
    Route::get('/dashboard', [RastreadorController::class, 'dashboard'])->name('dashboard');

    // Consulta de histórico/última telemetria ESP32 (por empresa/dispositivo/período).
    Route::get('/esp32/historico', [\App\Http\Controllers\Web\Esp32MonitorController::class, 'historico'])->name('esp32.historico');
    Route::get('/esp32/ultima',    [\App\Http\Controllers\Web\Esp32MonitorController::class, 'ultima'])->name('esp32.ultima');

    // Rastreadores / mapa (antes públicos — agora exigem login).
    Route::get('/rastreadores', [RastreadorController::class, 'index'])->name('rastreadores.index');
    Route::get('/rastreadores/{rastreador}/historico', [PosicaoController::class, 'historico'])->name('rastreadores.historico');
    Route::get('/mapa', [PosicaoController::class, 'mapa'])->name('mapa');
    Route::get('/mapa-esp32', [PosicaoController::class, 'mapaEsp32'])->name('mapa.esp32');

    // Perfil (Breeze).
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Cadastro de empresas — apenas super-admin.
    Route::middleware('role:super-admin')->group(function () {
        Route::resource('empresas', EmpresaController::class)->except(['show']);
    });

    // Cadastro de usuários — super-admin (qualquer empresa) ou admin-empresa (própria).
    Route::middleware('role:super-admin|admin-empresa')->group(function () {
        Route::resource('usuarios', UsuarioController::class)->except(['show']);
    });
});

require __DIR__.'/auth.php';
