<?php

namespace App\Services;

use App\Models\Esp32Dispositivo;
use App\Models\Esp32Telemetria;
use App\Events\Esp32TelemetryReceived;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Service Layer para telemetria ESP32.
 * Segue o princípio de Single Responsibility (SOLID).
 */
class Esp32TelemetryService
{
    /**
     * Processa a ingestão de dados de uma placa ESP32.
     * 
     * @param array $data
     * @return Esp32Telemetria
     */
    public function registrarTelemetria(Esp32Dispositivo $dispositivo, array $data): Esp32Telemetria
    {
        $telemetria = DB::transaction(function () use ($dispositivo, $data) {
            // Registra a telemetria no dispositivo autenticado pelo token.
            $telemetria = $dispositivo->telemetrias()->create([
                'latitude'      => $data['lat'] ?? null,
                'longitude'     => $data['lon'] ?? null,
                'bateria_vcc'   => $data['bateria'] ?? null,
                'temperatura'   => $data['temp'] ?? null,
                'velocidade'    => $data['vel'] ?? 0,
                'payload_extra' => $data['extra'] ?? null,
                'data_hora'     => isset($data['timestamp']) ? Carbon::parse($data['timestamp']) : now(),
            ]);

            // Atualiza o status de último contato do dispositivo
            $dispositivo->update(['ultimo_contato' => now()]);

            return $telemetria;
        });

        // Broadcast de tempo real é BEST-EFFORT e fica FORA da transação: uma
        // falha do Reverb (servidor WebSocket offline) não deve reverter a
        // ingestão já persistida nem retornar 500 ao dispositivo.
        try {
            broadcast(new Esp32TelemetryReceived($telemetria));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                '[Esp32Telemetry] Broadcast falhou (ingestão OK, seguindo)',
                ['erro' => $e->getMessage()]
            );
        }

        return $telemetria;
    }

    /**
     * Retorna a lista de dispositivos com sua última telemetria válida.
     */
    public function getActiveFleet(?\App\Models\User $user = null)
    {
        return Esp32Dispositivo::daEmpresaDoUsuario($user)
            ->with('ultimaTelemetria')
            ->where('ativo', true)
            ->get();
    }
}
