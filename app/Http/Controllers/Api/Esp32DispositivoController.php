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
        security: [['bearerAuth' => []]],
        tags: ['ESP32 - Dispositivos']
    )]
    #[OA\Parameter(name: 'ativo',      description: 'Filtrar por status (1=ativos, 0=inativos)', in: 'query', required: false, schema: new OA\Schema(type: 'integer', enum: [0, 1]))]
    #[OA\Parameter(name: 'por_pagina', description: 'Itens por página (padrão 15)',               in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Lista paginada de dispositivos')]
    public function index(Request $request): JsonResponse
    {
        $query = Esp32Dispositivo::daEmpresaDoUsuario($request->user())->with('ultimaTelemetria');

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
        security: [['bearerAuth' => []]],
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
    #[OA\Response(response: 201, description: 'Dispositivo criado', content: new OA\JsonContent())]
    #[OA\Response(response: 409, description: 'Identificador já cadastrado', content: new OA\JsonContent())]
    #[OA\Response(response: 422, description: 'Dados inválidos', content: new OA\JsonContent())]
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'identificador' => 'required|string|max:50|unique:esp32_dispositivos,identificador',
            'nome'          => 'nullable|string|max:100',
            'descricao'     => 'nullable|string',
            'ativo'         => 'nullable|boolean',
            'empresa_id'    => 'nullable|integer|exists:empresas,id',
        ]);

        // super-admin pode escolher a empresa do dispositivo; demais usuários
        // ficam presos à própria empresa (ignora empresa_id enviado).
        $ator = $request->user();
        $empresaId = $ator->hasRole('super-admin')
            ? ($request->empresa_id ?? $ator->empresa_id)
            : $ator->empresa_id;

        $dispositivo = Esp32Dispositivo::create([
            'empresa_id'    => $empresaId,
            'identificador' => $request->identificador,
            'nome'          => $request->nome,
            'descricao'     => $request->descricao,
            'ativo'         => $request->ativo ?? true,
        ]);

        // Token de ingestão do dispositivo — exibido apenas nesta resposta.
        $token = $dispositivo->gerarTokenApi();

        return response()->json([
            'success'      => true,
            'message'      => 'Dispositivo cadastrado com sucesso.',
            'data'         => $dispositivo,
            'device_token' => $token,
        ], 201);
    }

    // =========================================================================
    // GET /api/v1/esp32/dispositivos/{identificador}
    // =========================================================================

    #[OA\Get(
        path: '/api/v1/esp32/dispositivos/{identificador}',
        summary: 'Retorna os detalhes de um dispositivo ESP32',
        security: [['bearerAuth' => []]],
        tags: ['ESP32 - Dispositivos']
    )]
    #[OA\Parameter(name: 'identificador', description: 'MAC Address ou ID do dispositivo', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Detalhes do dispositivo com última telemetria')]
    #[OA\Response(response: 404, description: 'Dispositivo não encontrado')]
    public function show(Request $request, string $identificador): JsonResponse
    {
        $dispositivo = Esp32Dispositivo::daEmpresaDoUsuario($request->user())
            ->where('identificador', $identificador)
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
        security: [['bearerAuth' => []]],
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
        $dispositivo = Esp32Dispositivo::daEmpresaDoUsuario($request->user())
            ->where('identificador', $identificador)
            ->firstOrFail();

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
        security: [['bearerAuth' => []]],
        tags: ['ESP32 - Dispositivos']
    )]
    #[OA\Parameter(name: 'identificador', description: 'MAC Address ou ID do dispositivo', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Dispositivo removido com sucesso')]
    #[OA\Response(response: 404, description: 'Dispositivo não encontrado')]
    public function destroy(Request $request, string $identificador): JsonResponse
    {
        $dispositivo = Esp32Dispositivo::daEmpresaDoUsuario($request->user())
            ->where('identificador', $identificador)
            ->firstOrFail();
        $dispositivo->delete(); // Cascade remove as telemetrias (ver migration)

        return response()->json([
            'success' => true,
            'message' => "Dispositivo '{$identificador}' e seu histórico foram removidos.",
        ]);
    }

    // =========================================================================
    // POST /api/v1/esp32/dispositivos/{identificador}/regenerar-token
    // =========================================================================

    #[OA\Post(
        path: '/api/v1/esp32/dispositivos/{identificador}/regenerar-token',
        summary: 'Gera um novo token de ingestão para o dispositivo (revoga o anterior)',
        security: [['bearerAuth' => []]],
        tags: ['ESP32 - Dispositivos']
    )]
    #[OA\Parameter(name: 'identificador', description: 'MAC Address ou ID do dispositivo', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Novo token gerado (exibido uma única vez)')]
    #[OA\Response(response: 404, description: 'Dispositivo não encontrado')]
    public function regenerarToken(Request $request, string $identificador): JsonResponse
    {
        $dispositivo = Esp32Dispositivo::daEmpresaDoUsuario($request->user())
            ->where('identificador', $identificador)
            ->firstOrFail();

        $token = $dispositivo->gerarTokenApi();

        return response()->json([
            'success'      => true,
            'message'      => 'Novo token gerado. O token anterior foi revogado.',
            'device_token' => $token,
        ]);
    }

    // =========================================================================
    // POST /api/v1/esp32/provision  (máquina-a-máquina — X-API-KEY)
    // =========================================================================
    // Permite ao próprio ESP32 obter seu token de ingestão automaticamente,
    // sem depender do provisionamento por BLE. O dispositivo deve estar
    // pré-cadastrado (por um admin) e ativo. Como o token é guardado só como
    // hash, cada chamada gera um token NOVO (revoga o anterior) e o devolve em
    // claro uma única vez — o firmware persiste na NVS e só chama de novo se
    // não tiver token salvo.

    #[OA\Post(
        path: '/api/v1/esp32/provision',
        summary: 'Auto-provisionamento: o dispositivo obtém seu token de ingestão',
        description: 'Máquina-a-máquina. Autenticado pela chave de bootstrap (X-API-KEY). '
            . 'O dispositivo precisa estar pré-cadastrado e ativo. Gera e devolve um token '
            . 'novo (revoga o anterior), exibido uma única vez.',
        security: [['apiKeyAuth' => []]],
        tags: ['ESP32 - Dispositivos']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['identificador'],
            properties: [
                new OA\Property(property: 'identificador', type: 'string', example: 'AA:BB:CC:DD:EE:FF'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Token provisionado (exibido uma única vez)', content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'success', type: 'boolean', example: true),
            new OA\Property(property: 'message', type: 'string', example: 'Token provisionado.'),
            new OA\Property(property: 'device_token', type: 'string', example: 'aBc123...48chars'),
        ]
    ))]
    #[OA\Response(response: 401, description: 'X-API-KEY ausente ou inválida')]
    #[OA\Response(response: 403, description: 'Dispositivo inativo')]
    #[OA\Response(response: 404, description: 'Dispositivo não cadastrado')]
    #[OA\Response(response: 422, description: 'identificador ausente')]
    public function provision(Request $request): JsonResponse
    {
        $request->validate([
            'identificador' => 'required|string|max:50',
        ]);

        // Sem escopo de usuário (chamada máquina-a-máquina via X-API-KEY).
        $dispositivo = Esp32Dispositivo::where('identificador', $request->identificador)->first();

        if (! $dispositivo) {
            return response()->json([
                'success' => false,
                'error'   => 'Dispositivo não cadastrado. Registre-o antes de provisionar.',
            ], 404);
        }

        if (! $dispositivo->ativo) {
            return response()->json([
                'success' => false,
                'error'   => 'Dispositivo inativo.',
            ], 403);
        }

        $token = $dispositivo->gerarTokenApi();

        return response()->json([
            'success'      => true,
            'message'      => 'Token provisionado.',
            'device_token' => $token,
        ]);
    }
}
