<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lista de empresas para o app (seleção no cadastro de dispositivos).
 * super-admin vê todas; demais usuários veem apenas a própria empresa.
 */
class EmpresaController extends Controller
{
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
