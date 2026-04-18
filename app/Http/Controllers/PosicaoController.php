<?php

namespace App\Http\Controllers;

use App\Models\Posicao;
use App\Models\Rastreador;
use Illuminate\Http\Request;

class PosicaoController extends Controller
{
    public function historico(Rastreador $rastreador, Request $request)
    {
        $request->validate([
            'data_inicio' => 'nullable|date',
            'data_fim'    => 'nullable|date|after_or_equal:data_inicio',
        ]);

        $dataInicio = $request->date('data_inicio') ?? now()->startOfDay();
        $dataFim    = $request->date('data_fim')    ?? now()->endOfDay();

        $posicoes = $rastreador->posicoes()
            ->validas()
            ->periodo($dataInicio, $dataFim)
            ->orderBy('data_hora')
            ->paginate(50)
            ->withQueryString();

        $rastreadores = Rastreador::ativos()->orderBy('nome')->get();

        return view('rastreadores.historico', compact(
            'rastreador', 'posicoes', 'rastreadores', 'dataInicio', 'dataFim'
        ));
    }

    public function mapa(Request $request)
    {
        $rastreadores = Rastreador::ativos()
            ->with(['posicoes' => fn($q) => $q->validas()->latest('data_hora')->limit(1)])
            ->orderBy('nome')
            ->get();

        // Última posição de cada rastreador para os marcadores do mapa
        $ultimasPosicoes = $rastreadores->map(fn($r) => [
            'id'         => $r->id,
            'imei'       => $r->imei,
            'nome'       => $r->nome,
            'placa'      => $r->placa,
            'ignicao'    => $r->ignicao,
            'em_panico'  => $r->em_panico,
            'lat'        => optional($r->posicoes->first())->latitude,
            'lon'        => optional($r->posicoes->first())->longitude,
            'velocidade' => optional($r->posicoes->first())->velocidade,
            'data_hora'  => optional($r->posicoes->first())?->data_hora?->format('d/m/Y H:i:s'),
        ])->filter(fn($r) => $r['lat'] && $r['lon'])->values();

        return view('rastreadores.mapa', compact('rastreadores', 'ultimasPosicoes'));
    }
}
