<?php

namespace App\Services;

use App\Models\Posicao;
use App\Models\Rastreador;
use App\Models\Evento;
use App\Services\Protocols\ProtocolParserInterface;
use App\Services\Protocols\TrxParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Serviço responsável pela lógica de negócio do rastreamento.
 * Segue o princípio da responsabilidade única (SRP).
 */
class TrackerService
{
    /**
     * Cache em memória (Process-level) como fallback de ultra-velocidade.
     */
    protected static array $imeiMap = [];

    /**
     * Processa os dados parseados e persiste no banco de dados.
     */
    public function handle(ProtocolParserInterface $parser, array $dados, string $ip): void
    {
        // Tratamento de Heartbeat
        if (isset($dados['tipo']) && $dados['tipo'] === 'heartbeat') {
            return;
        }

        // Cache de IMEI para protocolos que não enviam em todos os frames (ex: GT06)
        if (isset($dados['tipo']) && $dados['tipo'] === 'login' && isset($dados['imei'])) {
            self::$imeiMap[$ip] = $dados['imei'];
            Cache::put("tracker_imei_{$ip}", $dados['imei'], 86400); // 24 horas
            Log::info("[TrackerService] IMEI vinculado ao IP via Login", ['ip' => $ip, 'imei' => $dados['imei']]);
            return;
        }

        // Tenta recuperar IMEI (1. Memória local, 2. Cache central)
        if (!isset($dados['imei'])) {
            $dados['imei'] = self::$imeiMap[$ip] ?? Cache::get("tracker_imei_{$ip}");
            
            // Se achou no cache central mas não na memória, sincroniza a memória
            if ($dados['imei'] && !isset(self::$imeiMap[$ip])) {
                self::$imeiMap[$ip] = $dados['imei'];
            }
        }

        if (!$dados['imei']) {
            Log::warning("[TrackerService] Dados ignorados: IMEI não identificado para o IP {$ip}");
            return;
        }

        $this->persist($dados);
    }

    /**
     * Persiste a posição e eventos no banco de dados.
     */
    protected function persist(array $dados): void
    {
        DB::transaction(function () use ($dados) {
            $rastreador = Rastreador::firstOrCreate(
                ['imei' => $dados['imei']],
                [
                    'nome'  => 'Rastreador ' . substr($dados['imei'], -4),
                    'ativo' => true,
                ]
            );

            $rastreador->update(['ultimo_contato' => now()]);

            $posicao = null;
            if (isset($dados['latitude']) && isset($dados['longitude'])) {
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

                Log::info("[TrackerService] Posição recebida", [
                    'imei'      => $dados['imei'],
                    'lat'       => $dados['latitude'],
                    'lon'       => $dados['longitude']
                ]);
            }

            $this->processEvents($rastreador, $posicao, $dados);
        });
    }

    /**
     * Processa e salva eventos associados à posição com detecção de mudança de estado.
     */
    protected function processEvents(Rastreador $rastreador, ?Posicao $posicao, array $dados): void
    {
        $eventosBrutos = [];

        // Eventos mapeados por código (TRX/Bitmask)
        if (isset($dados['evento_codigo']) && $dados['evento_codigo'] !== '0000') {
            $eventosBrutos = array_merge($eventosBrutos, TrxParser::decodeEventos($dados['evento_codigo']));
        }

        // Eventos diretos (GT06 Alarme)
        if (isset($dados['evento_tipo'])) {
            $eventosBrutos[] = [
                'tipo' => $dados['evento_tipo'],
                'descricao' => $dados['evento_descricao'],
            ];
        }

        foreach ($eventosBrutos as $evento) {
            $tipo = $evento['tipo'];

            // Atualiza estado de Pânico no modelo Rastreador
            if (in_array($tipo, ['SOS', 'PANICO'])) {
                $rastreador->update(['em_panico' => true]);
            }

            // Regra de Ouro: Apenas salvamos mudanças de estado para Ignição
            if (in_array($tipo, ['IGNICAO_ON', 'IGNICAO_OFF'])) {
                $cacheKey = "tracker_status_ignicao_{$rastreador->imei}";
                $ultimoStatus = Cache::get($cacheKey);

                if ($ultimoStatus === $tipo) {
                    continue; // Ignora se o estado não mudou
                }

                Cache::put($cacheKey, $tipo, 86400);
                
                // Atualiza o estado no modelo para o Mapa
                $rastreador->update(['ignicao' => ($tipo === 'IGNICAO_ON')]);
            }

            $isPanico = in_array($tipo, ['SOS', 'PANICO']);

            Evento::create([
                'rastreador_id'   => $rastreador->id,
                'posicao_id'      => $posicao?->id,
                'tipo'            => $tipo,
                'descricao'       => $evento['descricao'],
                'codigo_raw'      => $dados['evento_codigo'] ?? '0000',
                'botao_ligado'    => $isPanico ? 1 : 0,
                'botao_desligado' => $isPanico ? 0 : 1,
            ]);

            Log::info("[TrackerService] Evento detectado", [
                'imei' => $rastreador->imei,
                'tipo' => $tipo
            ]);
        }

        // Sincroniza estado de pânico se o parser enviar a flag (ex: pacotes de status ou alarme fim)
        if (isset($dados['em_panico'])) {
            $novoEstado = (bool)$dados['em_panico'];
            
            $cacheKeySos = "tracker_status_sos_{$rastreador->imei}";
            $ultimoEstadoCache = Cache::get($cacheKeySos);

            // Regra Robusta: Atualiza se o banco estiver diferente OU o cache estiver diferente do novo estado
            if ($rastreador->em_panico !== $novoEstado || $ultimoEstadoCache !== $novoEstado) {
                Log::info("[TrackerService] Sincronizando estado SOS", [
                    'imei' => $rastreador->imei,
                    'banco' => $rastreador->em_panico,
                    'cache' => $ultimoEstadoCache,
                    'novo' => $novoEstado
                ]);

                // Atualiza o banco
                $rastreador->update(['em_panico' => $novoEstado]);

                // Atualiza o cache (válido por 1 hora para evitar flood, mas permite correções)
                Cache::put($cacheKeySos, $novoEstado, 3600);
            }
        }

        // Sincronização de estado concluída (incluindo Reset Automático via flag 'em_panico')
    }
}
