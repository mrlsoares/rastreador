<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Posicao;
use App\Models\Rastreador;
use Illuminate\Http\Request;

class PosicaoApiController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'rastreador_id' => 'nullable|exists:rastreadores,id',
            'data_inicio'   => 'nullable|date',
            'data_fim'      => 'nullable|date|after_or_equal:data_inicio',
            'per_page'      => 'nullable|integer|min:1|max:500',
        ]);

        $query = Posicao::with('rastreador')
            ->validas()
            ->orderBy('data_hora', 'desc');

        if ($request->filled('rastreador_id')) {
            $query->where('rastreador_id', $request->rastreador_id);
        }

        if ($request->filled('data_inicio')) {
            $query->where('data_hora', '>=', $request->date('data_inicio'));
        }

        if ($request->filled('data_fim')) {
            $query->where('data_hora', '<=', $request->date('data_fim')->endOfDay());
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 100))
        );
    }

    public function porRastreador(Request $request, $id)
    {
        $rastreador = Rastreador::findOrFail($id);

        $request->validate([
            'data_inicio' => 'nullable|date',
            'data_fim'    => 'nullable|date|after_or_equal:data_inicio',
            'per_page'    => 'nullable|integer|min:1|max:500',
        ]);

        $query = $rastreador->posicoes()
            ->validas()
            ->orderBy('data_hora');

        if ($request->filled('data_inicio') && $request->filled('data_fim')) {
            $query->periodo(
                $request->date('data_inicio'),
                $request->date('data_fim')->endOfDay()
            );
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 100))
        );
    }

    public function ultimaPosicao($id)
    {
        $rastreador = Rastreador::findOrFail($id);

        $posicao = $rastreador->posicoes()
            ->validas()
            ->latest('data_hora')
            ->first();

        if (!$posicao) {
            return response()->json(['message' => 'Nenhuma posição encontrada'], 404);
        }

        return response()->json($posicao);
    }
}
