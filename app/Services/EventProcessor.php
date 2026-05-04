<?php

namespace App\Services;

use App\Models\Evento;
use App\Models\Posicao;
use App\Models\Rastreador;
use App\Services\Protocols\TrxParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Responsável exclusivamente pelo processamento e persistência de eventos.
 *
 * Extract Class (Fowler, Refactoring §7.2): extraído de TrackerService para
 * respeitar SRP — cada classe deve ter um único motivo para mudar.
 * TrackerService orquestra; EventProcessor trata eventos.
 */
class EventProcessor
{
    /** Tipos de evento que indicam estado de pânico/SOS */
    private const PANIC_TYPES = ['SOS', 'PANICO'];

    /** Tipos de evento de ignição */
    private const IGNITION_TYPES = ['IGNICAO_ON', 'IGNICAO_OFF'];

    /**
     * Processa e persiste todos os eventos de uma leitura do rastreador.
     */
    public function process(Rastreador $rastreador, ?Posicao $posicao, array $dados): void
    {
        $eventos = $this->resolveRawEvents($dados);

        foreach ($eventos as $evento) {
            $tipo = $evento['tipo'];

            $this->syncPanicState($rastreador, $tipo);
            $this->syncIgnitionState($rastreador, $tipo);
            $this->persistEvent($rastreador, $posicao, $evento, $dados);
        }

        // Sincronização explícita de pânico quando o parser envia a flag direta
        if (isset($dados['em_panico'])) {
            $this->syncPanicFlag($rastreador, (bool) $dados['em_panico']);
        }
    }

    /**
     * Coleta eventos brutos de todas as fontes possíveis do payload.
     *
     * @return array<array{tipo: string, descricao: string}>
     */
    private function resolveRawEvents(array $dados): array
    {
        $eventos = [];

        // Fonte 1: Bitmask TRX (ex: "0002" → IGNICAO_ON)
        if (!empty($dados['evento_codigo']) && $dados['evento_codigo'] !== '0000') {
            $eventos = array_merge($eventos, TrxParser::decodeEventos($dados['evento_codigo']));
        }

        // Fonte 2: Alarme direto GT06
        if (!empty($dados['evento_tipo'])) {
            $eventos[] = [
                'tipo'      => $dados['evento_tipo'],
                'descricao' => $dados['evento_descricao'] ?? '',
            ];
        }

        return $eventos;
    }

    /**
     * Atualiza o estado de pânico no modelo se o evento indicar SOS.
     */
    private function syncPanicState(Rastreador $rastreador, string $tipo): void
    {
        if (in_array($tipo, self::PANIC_TYPES, strict: true)) {
            $rastreador->update(['em_panico' => true]);
        }
    }

    /**
     * Aplica a "Regra de Ouro": só persiste mudança de estado de ignição,
     * evitando duplicação no banco. Usa Cache como memória de estado.
     */
    private function syncIgnitionState(Rastreador $rastreador, string $tipo): void
    {
        if (!in_array($tipo, self::IGNITION_TYPES, strict: true)) {
            return;
        }

        $cacheKey    = "tracker_status_ignicao_{$rastreador->imei}";
        $ultimoStatus = Cache::get($cacheKey);

        if ($ultimoStatus === $tipo) {
            return; // Estado não mudou — sem persistência redundante
        }

        Cache::put($cacheKey, $tipo, 86400);
        $rastreador->update(['ignicao' => ($tipo === 'IGNICAO_ON')]);
    }

    /**
     * Persiste o evento no banco de dados.
     */
    private function persistEvent(
        Rastreador $rastreador,
        ?Posicao   $posicao,
        array      $evento,
        array      $dados
    ): void {
        $isPanico = in_array($evento['tipo'], self::PANIC_TYPES, strict: true);

        Evento::create([
            'rastreador_id'   => $rastreador->id,
            'posicao_id'      => $posicao?->id,
            'tipo'            => $evento['tipo'],
            'descricao'       => $evento['descricao'],
            'codigo_raw'      => $dados['evento_codigo'] ?? '0000',
            'botao_ligado'    => $isPanico ? 1 : 0,
            'botao_desligado' => $isPanico ? 0 : 1,
        ]);

        Log::info('[EventProcessor] Evento detectado', [
            'imei' => $rastreador->imei,
            'tipo' => $evento['tipo'],
        ]);
    }

    /**
     * Sincroniza o estado de pânico quando o parser envia flag explícita.
     * Usa dupla verificação (banco + cache) para evitar writes desnecessários.
     */
    private function syncPanicFlag(Rastreador $rastreador, bool $novoEstado): void
    {
        $cacheKey         = "tracker_status_sos_{$rastreador->imei}";
        $estadoEmCache    = Cache::get($cacheKey);

        if ($rastreador->em_panico === $novoEstado && $estadoEmCache === $novoEstado) {
            return;
        }

        Log::info('[EventProcessor] Sincronizando estado SOS', [
            'imei'  => $rastreador->imei,
            'banco' => $rastreador->em_panico,
            'cache' => $estadoEmCache,
            'novo'  => $novoEstado,
        ]);

        $rastreador->update(['em_panico' => $novoEstado]);
        Cache::put($cacheKey, $novoEstado, 3600);
    }
}
