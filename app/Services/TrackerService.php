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
            Cache::put("tracker_imei_{$ip}", $dados['imei'], 3600);
            return;
        }

        // Tenta recuperar IMEI do cache se não estiver presente no pacote atual
        if (!isset($dados['imei'])) {
            $dados['imei'] = Cache::get("tracker_imei_{$ip}");
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

            Evento::create([
                'rastreador_id' => $rastreador->id,
                'posicao_id'    => $posicao?->id,
                'tipo'          => $tipo,
                'descricao'     => $evento['descricao'],
                'codigo_raw'    => $dados['evento_codigo'] ?? '0000',
            ]);

            Log::info("[TrackerService] Evento detectado", [
                'imei' => $rastreador->imei,
                'tipo' => $tipo
            ]);
        }

        // Caso especial: Marcamos como pânico se detectado, 
        // mas não resetamos automaticamente apenas porque o pacote atual não o contém.
        // O reset deve vir de um evento específico ou comando.
    }
}
