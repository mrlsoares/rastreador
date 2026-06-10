<?php

namespace App\Http\Controllers;

use App\Models\Posicao;
use App\Models\Rastreador;
use App\Models\Esp32Dispositivo;
use Illuminate\Http\Request;

class PosicaoController extends Controller
{
    public function historico(Rastreador $rastreador, Request $request)
    {
        $request->validate([
            'data_inicio' => 'nullable|date',
            'data_fim'    => 'nullable|date|after_or_equal:data_inicio',
        ]);

        $dataInicio = $request->date('data_inicio', null, 'America/Sao_Paulo') ?? now('America/Sao_Paulo')->startOfDay();
        $dataFim    = $request->date('data_fim', null, 'America/Sao_Paulo')    ?? now('America/Sao_Paulo')->endOfDay();

        $posicoes = $rastreador->posicoes()
            ->validas()
            ->periodo(
                $dataInicio->copy()->setTimezone('UTC'),
                $dataFim->copy()->setTimezone('UTC')
            )
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
            // Filtra posições no futuro (ghosting)
            $ultima = $r->posicoes()->where('data_hora', '<=', now())->latest('data_hora')->first();
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

    /**
     * Exibe o mapa exclusivo para dispositivos ESP32.
     */
    public function mapaEsp32(Request $request)
    {
        $dispositivos = Esp32Dispositivo::where('ativo', true)
            ->with(['ultimaTelemetria'])
            ->orderBy('nome')
            ->get();

        return view('rastreadores.mapa_esp32', compact('dispositivos'));
    }
}
