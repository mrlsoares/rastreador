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
    public function processPayload(array $data): Esp32Telemetria
    {
        return DB::transaction(function () use ($data) {
            // 1. Localiza ou cria o dispositivo (MAC Address/ID)
            $dispositivo = Esp32Dispositivo::firstOrCreate(
                ['identificador' => $data['identificador']],
                [
                    'nome' => $data['nome'] ?? 'ESP32-' . substr($data['identificador'], -4),
                    'ativo' => true
                ]
            );

            // 2. Registra a telemetria
            $telemetria = $dispositivo->telemetrias()->create([
                'latitude'      => $data['lat'] ?? null,
                'longitude'     => $data['lon'] ?? null,
                'bateria_vcc'   => $data['bateria'] ?? null,
                'temperatura'   => $data['temp'] ?? null,
                'velocidade'    => $data['vel'] ?? 0,
                'payload_extra' => $data['extra'] ?? null,
                'data_hora'     => isset($data['timestamp']) ? Carbon::parse($data['timestamp']) : now(),
            ]);

            // 3. Atualiza o status de último contato do dispositivo
            $dispositivo->update(['ultimo_contato' => now()]);

            // 4. Dispara evento de tempo real
            broadcast(new Esp32TelemetryReceived($telemetria));

            return $telemetria;
        });
    }

    /**
     * Retorna a lista de dispositivos com sua última telemetria válida.
     */
    public function getActiveFleet()
    {
        return Esp32Dispositivo::with('ultimaTelemetria')
            ->where('ativo', true)
            ->get();
    }
}
