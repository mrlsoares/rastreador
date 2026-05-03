<?php

namespace App\Events;

use App\Models\Esp32Telemetria;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Esp32TelemetryReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $telemetria;

    public function __construct(Esp32Telemetria $telemetria)
    {
        // Carrega o dispositivo para ter os dados no frontend
        $this->telemetria = $telemetria->load('dispositivo');
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('esp32-fleet'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TelemetryReceived';
    }
}
