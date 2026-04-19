<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rastreador;
use Illuminate\Http\Request;

class RastreadorApiController extends Controller
{
    public function index()
    {
        return response()->json(
            Rastreador::ativos()
                ->with(['ultimaPosicao' => fn($q) => $q->where('data_hora', '<=', now())])
                ->orderBy('nome')
                ->get()
        );
    }

    public function show($id)
    {
        $rastreador = Rastreador::findOrFail($id);
        return response()->json($rastreador);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'imei'           => 'required|string|size:15|unique:rastreadores',
            'nome'           => 'required|string|max:100',
            'placa'          => 'nullable|string|max:10',
            'modelo_veiculo' => 'nullable|string|max:100',
            'descricao'      => 'nullable|string',
        ]);

        $rastreador = Rastreador::create($dados);
        return response()->json($rastreador, 201);
    }

    public function update(Request $request, $id)
    {
        $rastreador = Rastreador::findOrFail($id);

        $dados = $request->validate([
            'nome'           => 'sometimes|string|max:100',
            'placa'          => 'nullable|string|max:10',
            'modelo_veiculo' => 'nullable|string|max:100',
            'descricao'      => 'nullable|string',
            'ativo'          => 'sometimes|boolean',
        ]);

        $rastreador->update($dados);
        return response()->json($rastreador);
    }
}
