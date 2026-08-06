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
        return DB::transaction(function () use ($dispositivo, $data) {
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
    public function getActiveFleet(?\App\Models\User $user = null)
    {
        return Esp32Dispositivo::daEmpresaDoUsuario($user)
            ->with('ultimaTelemetria')
            ->where('ativo', true)
            ->get();
    }
}
