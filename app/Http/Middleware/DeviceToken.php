<?php

namespace App\Http\Middleware;

use App\Models\Esp32Dispositivo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica a ingestão de telemetria por dispositivo (token por ESP32).
 * Header: X-DEVICE-TOKEN. Resolve o dispositivo e o injeta na request.
 */
class DeviceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->header('X-DEVICE-TOKEN', '');

        if ($token === '') {
            return response()->json(['error' => 'Token do dispositivo ausente.'], 401);
        }

        $dispositivo = Esp32Dispositivo::porToken($token);

        if (! $dispositivo || ! $dispositivo->ativo) {
            return response()->json(['error' => 'Token do dispositivo inválido ou inativo.'], 401);
        }

        // Disponível para o controller: dispositivo autenticado.
        $request->attributes->set('esp32_device', $dispositivo);

        return $next($request);
    }
}
