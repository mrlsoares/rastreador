<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RastreadorController;
use App\Http\Controllers\PosicaoController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [RastreadorController::class, 'dashboard'])->name('dashboard');
Route::get('/rastreadores', [RastreadorController::class, 'index'])->name('rastreadores.index');
Route::get('/rastreadores/{rastreador}/historico', [PosicaoController::class, 'historico'])->name('rastreadores.historico');
Route::get('/mapa', [PosicaoController::class, 'mapa'])->name('mapa');
Route::get('/mapa-esp32', [PosicaoController::class, 'mapaEsp32'])->name('mapa.esp32');
