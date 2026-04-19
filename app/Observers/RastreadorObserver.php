<?php

namespace App\Observers;

use App\Models\Rastreador;

use App\Events\SosStatusChanged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RastreadorObserver
{
    /**
     * Handle the Rastreador "updated" event.
     */
    public function updated(Rastreador $rastreador): void
    {
        // Detecta se houve mudança no Pânico ou na Ignição
        if ($rastreador->isDirty(['em_panico', 'ignicao', 'ultima_latitude', 'ultima_longitude'])) {
            
            Log::info("[Observer] Disparando Broadcast via Reverb", [
                'imei' => $rastreador->imei,
                'panico' => $rastreador->em_panico,
                'ignicao' => $rastreador->ignicao
            ]);

            // Dispara o evento de WebSocket (Broadcasting)
            SosStatusChanged::dispatch($rastreador);

            // Garante que o cache de status esteja limpo para o próximo processamento
            Cache::forget("tracker_status_sos_{$rastreador->imei}");
            Cache::forget("tracker_status_ignicao_{$rastreador->imei}");
        }
    }
}
