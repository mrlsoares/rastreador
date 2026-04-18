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
            ->orderByDesc('data_hora')
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
            ->with(['ultimaPosicao'])
            ->orderBy('nome')
            ->get();

        // Última posição de cada rastreador para os marcadores do mapa
        $ultimasPosicoes = $rastreadores->map(function($r) {
            $ultima = $r->ultimaPosicao;
            if (!$ultima || !$ultima->latitude || !$ultima->longitude) {
                return null;
            }

            return [
                'id'         => $r->id,
                'imei'       => $r->imei,
                'nome'       => $r->nome,
                'placa'      => $r->placa,
                'ignicao'    => (bool)$r->ignicao,
                'em_panico'  => (bool)$r->em_panico,
                'lat'        => (float)$ultima->latitude,
                'lon'        => (float)$ultima->longitude,
                'velocidade' => (int)$ultima->velocidade,
                'data_hora'  => $ultima->data_hora->format('d/m/Y H:i:s'),
            ];
        })->filter()->values();

        return view('rastreadores.mapa', compact('rastreadores', 'ultimasPosicoes'));
    }
}
