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

class TelemetriaController extends Controller
{
    #[OA\Post(
        path: '/api/v1/telemetria',
        summary: 'Receber telemetria (legado TRX / chave global)',
        description: 'Ingestão legada autenticada pela chave global X-API-KEY (TELEMETRIA_API_KEY). '
            . 'Para novos dispositivos ESP32 use /esp32/telemetry com token por dispositivo.',
        security: [['apiKeyAuth' => []]],
        tags: ['Telemetria ESP32 (legado)']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['device_id', 'latitude', 'longitude'],
            properties: [
                new OA\Property(property: 'device_id', type: 'string', example: 'ESP32_01'),
                new OA\Property(property: 'latitude', type: 'number', format: 'float', example: -23.5505),
                new OA\Property(property: 'longitude', type: 'number', format: 'float', example: -46.6333),
                new OA\Property(property: 'velocidade', type: 'number', format: 'float', example: 10.5),
                new OA\Property(property: 'bateria', type: 'number', format: 'float', example: 95.0),
                new OA\Property(property: 'panic_button', type: 'boolean', example: false),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Telemetria salva com sucesso')]
    #[OA\Response(response: 401, description: 'X-API-KEY ausente ou inválida')]
    #[OA\Response(response: 422, description: 'Dados inválidos')]
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
