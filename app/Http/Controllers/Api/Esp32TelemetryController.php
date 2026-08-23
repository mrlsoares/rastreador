<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Esp32Dispositivo;
use App\Models\Esp32Telemetria;
use App\Services\Esp32TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class Esp32TelemetryController extends Controller
{
    public function __construct(
        protected Esp32TelemetryService $telemetryService
    ) {}

    // =========================================================================
    // POST /api/v1/esp32/telemetry
    // =========================================================================

    #[OA\Post(
        path: '/api/v1/esp32/telemetry',
        summary: 'Recebe e processa dados de telemetria de uma placa ESP32',
        description: 'Ingestão autenticada por token de dispositivo (header X-DEVICE-TOKEN). '
            . 'O dispositivo deve estar pré-cadastrado e ativo — não há criação automática. '
            . 'Token ausente/inválido/revogado ou dispositivo inativo retornam 401. '
            . 'Provisione o token via /esp32/provision (máquina-a-máquina) ou pelo app.',
        security: [['deviceToken' => []]],
        tags: ['ESP32']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['identificador'],
            properties: [
                new OA\Property(property: 'identificador', type: 'string', example: 'AA:BB:CC:DD:EE:FF', description: 'MAC Address ou ID único do chip'),
                new OA\Property(property: 'nome',          type: 'string', example: 'ESP32-Caminhão-01', description: 'Nome amigável do dispositivo'),
                new OA\Property(property: 'lat',           type: 'number', format: 'float', example: -23.55052),
                new OA\Property(property: 'lon',           type: 'number', format: 'float', example: -46.63330),
                new OA\Property(property: 'bateria',       type: 'number', format: 'float', example: 3.85, description: 'Voltagem da bateria (V)'),
                new OA\Property(property: 'temp',          type: 'number', format: 'float', example: 34.5, description: 'Temperatura em °C'),
                new OA\Property(property: 'vel',           type: 'integer', example: 60, description: 'Velocidade em km/h'),
                new OA\Property(property: 'timestamp',     type: 'string', format: 'date-time', example: '2026-05-03T20:00:00Z', description: 'Data/hora do dispositivo (opcional, usa now() se omitido)'),
                new OA\Property(property: 'extra',         type: 'object', example: ['sinal_gsm' => -75], description: 'Payload extra em JSON livre'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Telemetria processada com sucesso', content: new OA\JsonContent())]
    #[OA\Response(response: 401, description: 'X-DEVICE-TOKEN ausente/inválido/revogado ou dispositivo inativo', content: new OA\JsonContent())]
    #[OA\Response(response: 422, description: 'Dados inválidos', content: new OA\JsonContent())]
    #[OA\Response(response: 500, description: 'Erro interno', content: new OA\JsonContent())]
    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\Esp32Dispositivo $dispositivo */
        $dispositivo = $request->attributes->get('esp32_device');

        $validator = Validator::make($request->all(), [
            'lat'       => 'nullable|numeric|between:-90,90',
            'lon'       => 'nullable|numeric|between:-180,180',
            'bateria'   => 'nullable|numeric|min:0',
            'temp'      => 'nullable|numeric',
            'vel'       => 'nullable|integer|min:0',
            'timestamp' => 'nullable|date',
            'extra'     => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $telemetria = $this->telemetryService->registrarTelemetria($dispositivo, $request->all());
            return response()->json([
                'success' => true,
                'message' => 'Telemetria processada com sucesso',
                'data'    => $telemetria->load('dispositivo'),
            ], 201);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[Esp32Telemetry] Falha ao processar telemetria', [
                'erro'          => $e->getMessage(),
                'identificador' => $request->input('identificador'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno ao processar telemetria.',
            ], 500);
        }
    }

    // =========================================================================
    // GET /api/v1/esp32/fleet
    // =========================================================================

    #[OA\Get(
        path: '/api/v1/esp32/fleet',
        summary: 'Retorna todos os dispositivos ESP32 ativos com a última telemetria',
        description: 'Snapshot em tempo real da frota de placas ESP32. Ideal para atualizar pins no mapa.',
        security: [['bearerAuth' => []]],
        tags: ['ESP32']
    )]
    #[OA\Response(response: 200, description: 'Lista de dispositivos ativos com última telemetria')]
    public function fleet(Request $request): JsonResponse
    {
        $fleet = $this->telemetryService->getActiveFleet($request->user());
        return response()->json(['success' => true, 'data' => $fleet]);
    }

    // =========================================================================
    // GET /api/v1/esp32/monitor/dispositivos
    // =========================================================================

    #[OA\Get(
        path: '/api/v1/esp32/monitor/dispositivos',
        summary: 'Lista os dispositivos ESP32 agrupados por empresa (somente leitura)',
        description: 'Listagem enxuta para o app monitor (role leitor): identificador, nome, '
            . 'status e último contato, agrupados por empresa. Sem token de ingestão nem payload. '
            . 'Respeita o escopo de empresa (leitor enxerga todas).',
        security: [['bearerAuth' => []]],
        tags: ['ESP32']
    )]
    #[OA\Parameter(name: 'empresa_id', description: 'Filtra por empresa',                    in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'ativo',      description: 'Filtra por status (1=ativos, 0=inativos)', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [0, 1]))]
    #[OA\Response(response: 200, description: 'Dispositivos agrupados por empresa')]
    public function dispositivos(Request $request): JsonResponse
    {
        $query = Esp32Dispositivo::daEmpresaDoUsuario($request->user())
            ->with('empresa:id,nome_fantasia,razao_social');

        if ($request->has('ativo')) {
            $query->where('ativo', (bool) $request->ativo);
        }

        if ($request->filled('empresa_id')) {
            $query->where('empresa_id', $request->integer('empresa_id'));
        }

        $porEmpresa = $query->orderBy('nome')->get()
            ->groupBy('empresa_id')
            ->map(function ($grupo) {
                $empresa = $grupo->first()->empresa;

                return [
                    'empresa' => $empresa ? [
                        'id'   => $empresa->id,
                        'nome' => $empresa->nome_fantasia ?: $empresa->razao_social,
                    ] : null,
                    'dispositivos' => $grupo->map(fn ($d) => [
                        'identificador'  => $d->identificador,
                        'nome'           => $d->nome,
                        'ativo'          => $d->ativo,
                        'ultimo_contato' => $d->ultimo_contato,
                    ])->values(),
                ];
            })
            ->values();

        return response()->json(['success' => true, 'data' => $porEmpresa]);
    }

    // =========================================================================
    // GET /api/v1/esp32/{identificador}/historico
    // =========================================================================

    #[OA\Get(
        path: '/api/v1/esp32/{identificador}/historico',
        summary: 'Retorna o histórico de telemetria de um dispositivo ESP32 por período',
        security: [['bearerAuth' => []]],
        tags: ['ESP32']
    )]
    #[OA\Parameter(name: 'identificador', description: 'MAC Address ou ID do dispositivo', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'data_inicio',   description: 'Data de início (Y-m-d H:i:s)',     in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date-time'))]
    #[OA\Parameter(name: 'data_fim',      description: 'Data final (Y-m-d H:i:s)',          in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date-time'))]
    #[OA\Parameter(name: 'por_pagina',    description: 'Registros por página (padrão 100)', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Histórico paginado')]
    #[OA\Response(response: 404, description: 'Dispositivo não encontrado')]
    #[OA\Response(response: 422, description: 'Parâmetros inválidos')]
    public function historico(Request $request, string $identificador): JsonResponse
    {
        $request->validate([
            'data_inicio' => 'required|date',
            'data_fim'    => 'required|date|after_or_equal:data_inicio',
            'por_pagina'  => 'nullable|integer|min:1|max:500',
        ]);

        $dispositivo = Esp32Dispositivo::daEmpresaDoUsuario($request->user())
            ->where('identificador', $identificador)
            ->firstOrFail();

        $dataInicio = $request->date('data_inicio', null, 'America/Sao_Paulo')->setTimezone('UTC');
        $dataFim = $request->date('data_fim', null, 'America/Sao_Paulo')->endOfDay()->setTimezone('UTC');

        $telemetrias = Esp32Telemetria::where('esp32_dispositivo_id', $dispositivo->id)
            ->whereBetween('data_hora', [$dataInicio, $dataFim])
            ->orderBy('data_hora', 'desc')
            ->paginate($request->por_pagina ?? 100)
            ->withQueryString();

        return response()->json([
            'success'     => true,
            'dispositivo' => [
                'identificador'  => $dispositivo->identificador,
                'nome'           => $dispositivo->nome,
                'ultimo_contato' => $dispositivo->ultimo_contato ? $dispositivo->ultimo_contato->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') : null,
            ],
            'telemetrias' => $telemetrias,
        ]);
    }

    // =========================================================================
    // GET /api/v1/esp32/{identificador}/ultima
    // =========================================================================

    #[OA\Get(
        path: '/api/v1/esp32/{identificador}/ultima',
        summary: 'Retorna a última telemetria recebida de um dispositivo ESP32',
        security: [['bearerAuth' => []]],
        tags: ['ESP32']
    )]
    #[OA\Parameter(name: 'identificador', description: 'MAC Address ou ID do dispositivo', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Última telemetria do dispositivo, ou success=false + message quando ainda não há telemetria')]
    #[OA\Response(response: 404, description: 'Dispositivo não encontrado')]
    public function ultima(Request $request, string $identificador): JsonResponse
    {
        $dispositivo = Esp32Dispositivo::daEmpresaDoUsuario($request->user())
            ->where('identificador', $identificador)
            ->with('ultimaTelemetria')
            ->firstOrFail();

        // Dispositivo existe mas ainda sem telemetria: 200 com aviso (não é erro
        // HTTP — o cliente Delphi captura a mensagem sem disparar exceção 404).
        if (! $dispositivo->ultimaTelemetria) {
            return response()->json([
                'success'     => false,
                'message'     => 'Nenhuma telemetria encontrada para este dispositivo.',
                'dispositivo' => [
                    'identificador'  => $dispositivo->identificador,
                    'nome'           => $dispositivo->nome,
                    'ativo'          => $dispositivo->ativo,
                    'ultimo_contato' => $dispositivo->ultimo_contato,
                ],
                'telemetria'  => null,
            ], 200);
        }

        return response()->json([
            'success'     => true,
            'dispositivo' => [
                'identificador'  => $dispositivo->identificador,
                'nome'           => $dispositivo->nome,
                'ativo'          => $dispositivo->ativo,
                'ultimo_contato' => $dispositivo->ultimo_contato,
            ],
            'telemetria'  => $dispositivo->ultimaTelemetria,
        ]);
    }
}
