<?php

namespace App\Http\Controllers;

use App\Models\Rastreador;
use App\Models\Posicao;
use Illuminate\Http\Request;

class RastreadorController extends Controller
{
    public function dashboard()
    {
        $rastreadores = Rastreador::ativos()
            ->withCount('posicoes')
            ->with(['posicoes' => fn($q) => $q->latest('data_hora')->limit(1)])
            ->get();

        $totalPosicoes = Posicao::count();
        $totalAtivos   = $rastreadores->count();

        return view('rastreadores.dashboard', compact('rastreadores', 'totalPosicoes', 'totalAtivos'));
    }

    public function index()
    {
        $rastreadores = Rastreador::withCount('posicoes')
            ->orderBy('nome')
            ->paginate(20);

        return view('rastreadores.index', compact('rastreadores'));
    }
}
