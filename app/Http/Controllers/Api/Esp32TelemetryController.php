<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Esp32TelemetryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Esp32TelemetryController extends Controller
{
    protected $telemetryService;

    public function __construct(Esp32TelemetryService $service)
    {
        $this->telemetryService = $service;
    }

    /**
     * Recebe telemetria da placa ESP32.
     * Endpoint: POST /api/v1/esp32/telemetry
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'identificador' => 'required|string',
            'lat'           => 'nullable|numeric',
            'lon'           => 'nullable|numeric',
            'bateria'       => 'nullable|numeric',
            'temp'          => 'nullable|numeric',
            'vel'           => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $telemetria = $this->telemetryService.processPayload($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Telemetria processada com sucesso',
                'data'    => $telemetria
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar telemetria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna o estado atual da frota ESP32 para o mapa.
     */
    public function index()
    {
        $fleet = $this->telemetryService->getActiveFleet();
        return response()->json($fleet);
    }
}
