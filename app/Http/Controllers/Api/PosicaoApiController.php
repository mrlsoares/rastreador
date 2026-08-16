<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Posicao;
use App\Models\Rastreador;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PosicaoApiController extends Controller
{
    #[OA\Get(
        path: '/api/v1/posicoes',
        summary: 'Lista posições dos rastreadores TRX-16 da empresa',
        description: 'Paginado, mais recentes primeiro. Filtros opcionais por rastreador e período.',
        security: [['bearerAuth' => []]],
        tags: ['Posições TRX']
    )]
    #[OA\Parameter(name: 'rastreador_id', description: 'Filtra por rastreador', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'data_inicio', description: 'Início (Y-m-d H:i:s, America/Sao_Paulo)', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time'))]
    #[OA\Parameter(name: 'data_fim', description: 'Fim (Y-m-d H:i:s, America/Sao_Paulo)', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time'))]
    #[OA\Parameter(name: 'per_page', description: 'Registros por página (padrão 100, máx 500)', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Posições paginadas')]
    #[OA\Response(response: 401, description: 'Não autenticado')]
    public function index(Request $request)
    {
        $request->validate([
            'rastreador_id' => 'nullable|exists:rastreadores,id',
            'data_inicio'   => 'nullable|date',
            'data_fim'      => 'nullable|date|after_or_equal:data_inicio',
            'per_page'      => 'nullable|integer|min:1|max:500',
        ]);

        // Restringe às posições de rastreadores da empresa do usuário.
        $rastreadorIds = Rastreador::daEmpresaDoUsuario($request->user())->pluck('id');

        $query = Posicao::with('rastreador')
            ->validas()
            ->whereIn('rastreador_id', $rastreadorIds)
            ->orderBy('data_hora', 'desc');

        if ($request->filled('rastreador_id')) {
            $query->where('rastreador_id', $request->rastreador_id);
        }

        if ($request->filled('data_inicio')) {
            $query->where('data_hora', '>=', $request->date('data_inicio', null, 'America/Sao_Paulo')->setTimezone('UTC'));
        }

        if ($request->filled('data_fim')) {
            $query->where('data_hora', '<=', $request->date('data_fim', null, 'America/Sao_Paulo')->endOfDay()->setTimezone('UTC'));
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 100))->withQueryString()
        );
    }

    #[OA\Get(
        path: '/api/v1/rastreadores/{id}/posicoes',
        summary: 'Lista as posições de um rastreador TRX-16 por período',
        security: [['bearerAuth' => []]],
        tags: ['Posições TRX']
    )]
    #[OA\Parameter(name: 'id', description: 'ID do rastreador', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'data_inicio', description: 'Início (Y-m-d H:i:s)', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time'))]
    #[OA\Parameter(name: 'data_fim', description: 'Fim (Y-m-d H:i:s)', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time'))]
    #[OA\Parameter(name: 'per_page', description: 'Registros por página (padrão 100, máx 500)', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Posições paginadas do rastreador')]
    #[OA\Response(response: 404, description: 'Rastreador não encontrado')]
    public function porRastreador(Request $request, $id)
    {
        $rastreador = Rastreador::daEmpresaDoUsuario($request->user())->findOrFail($id);

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
                $request->date('data_inicio', null, 'America/Sao_Paulo')->setTimezone('UTC'),
                $request->date('data_fim', null, 'America/Sao_Paulo')->endOfDay()->setTimezone('UTC')
            );
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 100))->withQueryString()
        );
    }

    #[OA\Get(
        path: '/api/v1/rastreadores/{id}/ultima-posicao',
        summary: 'Retorna a última posição de um rastreador TRX-16',
        security: [['bearerAuth' => []]],
        tags: ['Posições TRX']
    )]
    #[OA\Parameter(name: 'id', description: 'ID do rastreador', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Última posição do rastreador')]
    #[OA\Response(response: 404, description: 'Rastreador ou posição não encontrado')]
    public function ultimaPosicao(Request $request, $id)
    {
        $rastreador = Rastreador::daEmpresaDoUsuario($request->user())->findOrFail($id);

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
