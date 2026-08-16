<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rastreador;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class RastreadorApiController extends Controller
{
    #[OA\Get(
        path: '/api/v1/rastreadores',
        summary: 'Lista os rastreadores TRX-16 ativos da empresa do usuário',
        description: 'Retorna os rastreadores ativos com a última posição conhecida.',
        security: [['bearerAuth' => []]],
        tags: ['Rastreadores TRX']
    )]
    #[OA\Response(response: 200, description: 'Lista de rastreadores ativos')]
    #[OA\Response(response: 401, description: 'Não autenticado')]
    public function index(Request $request)
    {
        return response()->json(
            Rastreador::daEmpresaDoUsuario($request->user())
                ->ativos()
                ->with('ultimaPosicao')
                ->orderBy('nome')
                ->get()
        );
    }

    #[OA\Get(
        path: '/api/v1/rastreadores/{id}',
        summary: 'Retorna um rastreador TRX-16 por ID',
        security: [['bearerAuth' => []]],
        tags: ['Rastreadores TRX']
    )]
    #[OA\Parameter(name: 'id', description: 'ID do rastreador', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Detalhes do rastreador')]
    #[OA\Response(response: 404, description: 'Rastreador não encontrado')]
    public function show(Request $request, $id)
    {
        $rastreador = Rastreador::daEmpresaDoUsuario($request->user())->findOrFail($id);
        return response()->json($rastreador);
    }

    #[OA\Post(
        path: '/api/v1/rastreadores',
        summary: 'Cadastra um rastreador TRX-16',
        security: [['bearerAuth' => []]],
        tags: ['Rastreadores TRX']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['imei', 'nome'],
            properties: [
                new OA\Property(property: 'imei', type: 'string', example: '860000000000001', description: '15 dígitos'),
                new OA\Property(property: 'nome', type: 'string', example: 'Caminhão 01'),
                new OA\Property(property: 'placa', type: 'string', example: 'ABC1D23'),
                new OA\Property(property: 'modelo_veiculo', type: 'string', example: 'VW Constellation'),
                new OA\Property(property: 'descricao', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Rastreador criado')]
    #[OA\Response(response: 422, description: 'Dados inválidos (IMEI duplicado/tamanho)')]
    public function store(Request $request)
    {
        $dados = $request->validate([
            'imei'           => 'required|string|size:15|unique:rastreadores',
            'nome'           => 'required|string|max:100',
            'placa'          => 'nullable|string|max:10',
            'modelo_veiculo' => 'nullable|string|max:100',
            'descricao'      => 'nullable|string',
        ]);

        $dados['empresa_id'] = $request->user()->empresa_id;

        $rastreador = Rastreador::create($dados);
        return response()->json($rastreador, 201);
    }

    #[OA\Put(
        path: '/api/v1/rastreadores/{id}',
        summary: 'Atualiza um rastreador TRX-16',
        security: [['bearerAuth' => []]],
        tags: ['Rastreadores TRX']
    )]
    #[OA\Parameter(name: 'id', description: 'ID do rastreador', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'nome', type: 'string', example: 'Caminhão 01'),
                new OA\Property(property: 'placa', type: 'string', example: 'ABC1D23'),
                new OA\Property(property: 'modelo_veiculo', type: 'string'),
                new OA\Property(property: 'descricao', type: 'string'),
                new OA\Property(property: 'ativo', type: 'boolean', example: true),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Rastreador atualizado')]
    #[OA\Response(response: 404, description: 'Rastreador não encontrado')]
    #[OA\Response(response: 422, description: 'Dados inválidos')]
    public function update(Request $request, $id)
    {
        $rastreador = Rastreador::daEmpresaDoUsuario($request->user())->findOrFail($id);

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
