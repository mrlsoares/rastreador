<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rastreador;
use App\Models\Posicao;
use App\Models\Evento;
use Illuminate\Http\Request;

/**
 * @OA\Info(
 *     title="API de Telemetria GPS",
 *     version="1.0.0",
 *     description="API dedicada à extração e consumo de dados de rastreamento de frotas (Posições e Lógica de Combinação)."
 * )
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer"
 * )
 */
class TelemetryApiController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/telemetria/{imei}/historico",
     *     summary="Retorna o histórico de posições dentro de um período",
     *     tags={"Telemetria"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="imei",
     *         in="path",
     *         required=true,
     *         description="IMEI do rastreador",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="data_inicio",
     *         in="query",
     *         required=true,
     *         description="Data de início (Y-m-d H:i:s)",
     *         @OA\Schema(type="string", format="date-time")
     *     ),
     *     @OA\Parameter(
     *         name="data_fim",
     *         in="query",
     *         required=true,
     *         description="Data final (Y-m-d H:i:s)",
     *         @OA\Schema(type="string", format="date-time")
     *     ),
     *     @OA\Response(response=200, description="Sucesso"),
     *     @OA\Response(response=401, description="Não autorizado"),
     *     @OA\Response(response=404, description="Rastreador não encontrado")
     * )
     */
    public function historico(Request $request, $imei)
    {
        $request->validate([
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
        ]);

        $rastreador = Rastreador::where('imei', $imei)->firstOrFail();

        $posicoes = Posicao::where('rastreador_id', $rastreador->id)
            ->whereBetween('data_hora', [$request->data_inicio, $request->data_fim])
            ->orderBy('data_hora', 'desc')
            ->paginate(100);

        $eventos = Evento::where('rastreador_id', $rastreador->id)
            ->whereBetween('created_at', [$request->data_inicio, $request->data_fim])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'sucesso' => true,
            'rastreador' => [
                'imei' => $rastreador->imei,
                'nome' => $rastreador->nome,
                'sos_ativo' => $rastreador->em_panico
            ],
            'eventos' => $eventos->map(function($ev) {
                return [
                    'tipo' => 'evento',
                    'categoria' => $ev->tipo,
                    'descricao' => $ev->descricao,
                    'data_hora' => $ev->created_at ? $ev->created_at->format('d/m/Y H:i') : null,
                    'ligado' => (int) $ev->botao_ligado,
                    'desligado' => (int) $ev->botao_desligado,
                ];
            }),
            'registros' => $posicoes->through(function($pos) {
                return [
                    'tipo' => 'posicao',
                    'data_hora' => $pos->data_hora ? $pos->data_hora->format('d/m/Y H:i') : null,
                    'latitude' => (float) $pos->latitude,
                    'longitude' => (float) $pos->longitude,
                    'velocidade' => (int) $pos->velocidade,
                    'sinal_gps' => (int) $pos->sinal_gps,
                ];
            })
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/telemetria/{imei}/ultimos",
     *     summary="Retorna os últimos N registros enviados pelo rastreador",
     *     tags={"Telemetria"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="imei",
     *         in="path",
     *         required=true,
     *         description="IMEI do rastreador",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="qtde_registros",
     *         in="query",
     *         required=false,
     *         description="Quantidade de registros (Padrão: 50, Máximo: 500)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Sucesso")
     * )
     */
    public function ultimos(Request $request, $imei)
    {
        $limit = $request->query('qtde_registros', 50);
        if ($limit > 500) $limit = 500;

        $rastreador = Rastreador::where('imei', $imei)->firstOrFail();

        $posicoes = Posicao::where('rastreador_id', $rastreador->id)
            ->orderBy('data_hora', 'desc')
            ->limit($limit)
            ->get();

        $eventos = Evento::where('rastreador_id', $rastreador->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'sucesso' => true,
            'rastreador' => [
                'imei' => $rastreador->imei,
                'nome' => $rastreador->nome,
                'sos_ativo' => $rastreador->em_panico
            ],
            'eventos' => $eventos->map(function($ev) {
                return [
                    'tipo' => 'evento',
                    'categoria' => $ev->tipo,
                    'descricao' => $ev->descricao,
                    'data_hora' => $ev->created_at ? $ev->created_at->format('d/m/Y H:i') : null,
                    'ligado' => (int) $ev->botao_ligado,
                    'desligado' => (int) $ev->botao_desligado,
                ];
            }),
            'registros' => $posicoes->map(function($pos) {
                return [
                    'tipo' => 'posicao',
                    'data_hora' => $pos->data_hora ? $pos->data_hora->format('d/m/Y H:i') : null,
                    'latitude' => (float) $pos->latitude,
                    'longitude' => (float) $pos->longitude,
                    'velocidade' => (int) $pos->velocidade,
                    'sinal_gps' => (int) $pos->sinal_gps,
                ];
            })
        ]);
    }
}
