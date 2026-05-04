<?php

namespace App\Services;

use App\Models\Posicao;
use App\Models\Rastreador;
use App\Services\Protocols\ProtocolParserInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquestra o fluxo de dados de um rastreador GPS.
 *
 * SRP aplicado (Clean Code cap. 10): esta classe tem um único motivo para
 * existir — coordenar a entrada de dados. Processamento de eventos foi
 * extraído para EventProcessor (Fowler, Refactoring §7.2 — Extract Class).
 */
class TrackerService
{
    /**
     * Cache de processo (ultra-velocidade) para associar IP → IMEI.
     * Evita acesso ao Redis/banco a cada pacote em conexões persistentes.
     */
    private static array $imeiMap = [];

    public function __construct(
        private readonly EventProcessor $eventProcessor
    ) {}

    /**
     * Ponto de entrada principal: recebe dados parseados e orquestra a persistência.
     */
    public function handle(ProtocolParserInterface $parser, array $dados, string $ip): void
    {
        if ($this->isHeartbeat($dados)) {
            return;
        }

        if ($this->isLoginPacket($dados)) {
            $this->bindImeiToIp($ip, $dados['imei']);
            return;
        }

        $dados['imei'] = $this->resolveImei($dados, $ip);

        if (empty($dados['imei'])) {
            Log::warning('[TrackerService] Dados ignorados: IMEI não identificado', ['ip' => $ip]);
            return;
        }

        $this->persist($dados);
    }

    // -------------------------------------------------------------------------
    // Métodos privados de suporte
    // -------------------------------------------------------------------------

    private function isHeartbeat(array $dados): bool
    {
        return ($dados['tipo'] ?? '') === 'heartbeat';
    }

    private function isLoginPacket(array $dados): bool
    {
        return ($dados['tipo'] ?? '') === 'login' && isset($dados['imei']);
    }

    private function bindImeiToIp(string $ip, string $imei): void
    {
        self::$imeiMap[$ip] = $imei;
        Cache::put("tracker_imei_{$ip}", $imei, 86400);
        Log::info('[TrackerService] IMEI vinculado ao IP', ['ip' => $ip, 'imei' => $imei]);
    }

    private function resolveImei(array $dados, string $ip): ?string
    {
        if (!empty($dados['imei'])) {
            return $dados['imei'];
        }

        $imei = self::$imeiMap[$ip] ?? Cache::get("tracker_imei_{$ip}");

        if ($imei && !isset(self::$imeiMap[$ip])) {
            self::$imeiMap[$ip] = $imei;
        }

        return $imei;
    }

    private function persist(array $dados): void
    {
        DB::transaction(function () use ($dados) {
            $rastreador = Rastreador::firstOrCreate(
                ['imei' => $dados['imei']],
                ['nome' => 'Rastreador ' . substr($dados['imei'], -4), 'ativo' => true]
            );

            $rastreador->update(['ultimo_contato' => now()]);

            $posicao = $this->persistPosition($rastreador, $dados);

            $this->eventProcessor->process($rastreador, $posicao, $dados);
        });
    }

    private function persistPosition(Rastreador $rastreador, array $dados): ?Posicao
    {
        if (empty($dados['latitude']) || empty($dados['longitude'])) {
            return null;
        }

        $posicao = Posicao::create([
            'rastreador_id' => $rastreador->id,
            'data_hora'     => $dados['data_hora'] ?? now(),
            'latitude'      => $dados['latitude'],
            'longitude'     => $dados['longitude'],
            'velocidade'    => $dados['velocidade'] ?? 0,
            'angulo'        => $dados['angulo'] ?? 0,
            'sinal_gps'     => $dados['sinal_gps'] ?? 0,
            'raw_data'      => $dados['raw_data'] ?? '',
        ]);

        Log::info('[TrackerService] Posição registrada', [
            'imei' => $dados['imei'],
            'lat'  => $dados['latitude'],
            'lon'  => $dados['longitude'],
        ]);

        return $posicao;
    }
}
