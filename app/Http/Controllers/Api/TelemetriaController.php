<?php
/*
 * Created At: 2026-05-12T12:42:54Z
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TelemetriaStoreRequest;
use App\Models\Telemetria;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * @OA\Server(
 *     url="https://rastreador.ddnsgratis.com.br",
 *     description="Servidor de Produção"
 * )
 */
class TelemetriaController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/telemetria",
     *     summary="Receber telemetria do ESP32",
     *     description="Salva dados de localização, velocidade, bateria e status do botão de pânico.",
     *     tags={"Telemetria ESP32"},
     *     @OA\Header(
     *         header="X-API-KEY",
     *         description="Chave de autenticação da API",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_id", "latitude", "longitude"},
     *             @OA\Property(property="device_id", type="string", example="ESP32_01"),
     *             @OA\Property(property="latitude", type="number", format="float", example=-23.5505),
     *             @OA\Property(property="longitude", type="number", format="float", example=-46.6333),
     *             @OA\Property(property="velocidade", type="number", format="float", example=10.5),
     *             @OA\Property(property="bateria", type="number", format="float", example=95.0),
     *             @OA\Property(property="panic_button", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Telemetria salva com sucesso.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Não autorizado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Dados inválidos"
     *     )
     * )
     */
    public function store(TelemetriaStoreRequest $request): JsonResponse
    {
        $telemetria = Telemetria::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Telemetria salva com sucesso.',
            'data' => $telemetria
        ], 201);
    }
}
