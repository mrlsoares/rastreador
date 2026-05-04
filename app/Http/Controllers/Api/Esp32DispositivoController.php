<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Esp32Dispositivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class Esp32DispositivoController extends Controller
{
    // =========================================================================
    // GET /api/v1/esp32/dispositivos
    // =========================================================================

    #[OA\Get(
        path: '/api/v1/esp32/dispositivos',
        summary: 'Lista todos os dispositivos ESP32 cadastrados',
        description: 'Retorna a lista paginada de dispositivos com sua última telemetria.',
        tags: ['ESP32 - Dispositivos']
    )]
    #[OA\Parameter(name: 'ativo',      description: 'Filtrar por status (1=ativos, 0=inativos)', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [0, 1]))]
    #[OA\Parameter(name: 'por_pagina', description: 'Itens por página (padrão 15)',               in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Lista paginada de dispositivos')]
    public function index(Request $request): JsonResponse
    {
        $query = Esp32Dispositivo::with('ultimaTelemetria');

        if ($request->has('ativo')) {
            $query->where('ativo', (bool) $request->ativo);
        }

        $dispositivos = $query->orderBy('ultimo_contato', 'desc')
            ->paginate($request->por_pagina ?? 15);

        return response()->json(['success' => true, 'data' => $dispositivos]);
    }

    // =========================================================================
    // POST /api/v1/esp32/dispositivos
    // =========================================================================

    #[OA\Post(
        path: '/api/v1/esp32/dispositivos',
        summary: 'Cadastra um novo dispositivo ESP32 manualmente',
        description: 'Útil para pré-registrar um dispositivo antes de ele enviar o primeiro pacote.',
        tags: ['ESP32 - Dispositivos']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['identificador'],
            properties: [
                new OA\Property(property: 'identificador', type: 'string', example: 'AA:BB:CC:DD:EE:FF'),
                new OA\Property(property: 'nome',          type: 'string', example: 'Sensor da Portaria'),
                new OA\Property(property: 'descricao',     type: 'string', example: 'ESP32 instalado na portaria principal'),
                new OA\Property(property: 'ativo',         type: 'boolean', example: true),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Dispositivo criado')]
    #[OA\Response(response: 409, description: 'Identificador já cadastrado')]
    #[OA\Response(response: 422, description: 'Dados inválidos')]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'identificador' => 'required|string|max:50|unique:esp32_dispositivos,identificador',
            'nome'          => 'nullable|string|max:100',
            'descricao'     => 'nullable|string',
            'ativo'         => 'nullable|boolean',
        ]);

        $dispositivo = Esp32Dispositivo::create([
            'identificador' => $request->identificador,
            'nome'          => $request->nome,
            'descricao'     => $request->descricao,
            'ativo'         => $request->ativo ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dispositivo cadastrado com sucesso.',
            'data'    => $dispositivo,
        ], 201);
    }

    // =========================================================================
    // GET /api/v1/esp32/dispositivos/{identificador}
    // =========================================================================

    #[OA\Get(
        path: '/api/v1/esp32/dispositivos/{identificador}',
        summary: 'Retorna os detalhes de um dispositivo ESP32',
        tags: ['ESP32 - Dispositivos']
    )]
    #[OA\Parameter(name: 'identificador', description: 'MAC Address ou ID do dispositivo', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Detalhes do dispositivo com última telemetria')]
    #[OA\Response(response: 404, description: 'Dispositivo não encontrado')]
    public function show(string $identificador): JsonResponse
    {
        $dispositivo = Esp32Dispositivo::where('identificador', $identificador)
            ->with('ultimaTelemetria')
            ->firstOrFail();

        return response()->json(['success' => true, 'data' => $dispositivo]);
    }

    // =========================================================================
    // PUT /api/v1/esp32/dispositivos/{identificador}
    // =========================================================================

    #[OA\Put(
        path: '/api/v1/esp32/dispositivos/{identificador}',
        summary: 'Atualiza os dados cadastrais de um dispositivo ESP32',
        tags: ['ESP32 - Dispositivos']
    )]
    #[OA\Parameter(name: 'identificador', description: 'MAC Address ou ID do dispositivo', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'nome',      type: 'string', example: 'Sensor Atualizado'),
                new OA\Property(property: 'descricao', type: 'string', example: 'Localização: Galpão B'),
                new OA\Property(property: 'ativo',     type: 'boolean', example: false),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Dispositivo atualizado')]
    #[OA\Response(response: 404, description: 'Dispositivo não encontrado')]
    public function update(Request $request, string $identificador): JsonResponse
    {
        $dispositivo = Esp32Dispositivo::where('identificador', $identificador)->firstOrFail();

        $request->validate([
            'nome'      => 'nullable|string|max:100',
            'descricao' => 'nullable|string',
            'ativo'     => 'nullable|boolean',
        ]);

        $dispositivo->update($request->only(['nome', 'descricao', 'ativo']));

        return response()->json([
            'success' => true,
            'message' => 'Dispositivo atualizado com sucesso.',
            'data'    => $dispositivo->fresh('ultimaTelemetria'),
        ]);
    }

    // =========================================================================
    // DELETE /api/v1/esp32/dispositivos/{identificador}
    // =========================================================================

    #[OA\Delete(
        path: '/api/v1/esp32/dispositivos/{identificador}',
        summary: 'Remove um dispositivo ESP32 e todo o seu histórico de telemetria',
        tags: ['ESP32 - Dispositivos']
    )]
    #[OA\Parameter(name: 'identificador', description: 'MAC Address ou ID do dispositivo', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Dispositivo removido com sucesso')]
    #[OA\Response(response: 404, description: 'Dispositivo não encontrado')]
    public function destroy(string $identificador): JsonResponse
    {
        $dispositivo = Esp32Dispositivo::where('identificador', $identificador)->firstOrFail();
        $dispositivo->delete(); // Cascade remove as telemetrias (ver migration)

        return response()->json([
            'success' => true,
            'message' => "Dispositivo '{$identificador}' e seu histórico foram removidos.",
        ]);
    }
}
