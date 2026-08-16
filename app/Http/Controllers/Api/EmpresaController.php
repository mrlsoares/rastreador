<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Lista de empresas para o app (seleção no cadastro de dispositivos).
 * super-admin vê todas; demais usuários veem apenas a própria empresa.
 */
class EmpresaController extends Controller
{
    #[OA\Get(
        path: '/api/v1/empresas',
        summary: 'Lista empresas para seleção no cadastro de dispositivos',
        description: 'super-admin vê todas as empresas; demais usuários veem apenas a própria.',
        security: [['bearerAuth' => []]],
        tags: ['Empresas']
    )]
    #[OA\Response(response: 200, description: 'Lista de empresas (id, nome_fantasia, razao_social)')]
    #[OA\Response(response: 401, description: 'Não autenticado')]
    public function index(Request $request): JsonResponse
    {
        $ator = $request->user();

        $query = Empresa::query()->orderBy('nome_fantasia');

        if (! $ator->hasRole('super-admin')) {
            $query->where('id', $ator->empresa_id);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get(['id', 'nome_fantasia', 'razao_social']),
        ]);
    }
}
