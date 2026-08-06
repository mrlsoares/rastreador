<?php
/*
 * Created At: 2026-05-12T12:39:45Z
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey    = (string) config('telemetria.api_key');
        $fornecida = (string) $request->header('X-API-KEY', '');

        // Fail-closed: sem chave configurada no servidor, nega tudo.
        if ($apiKey === '') {
            \Illuminate\Support\Facades\Log::error('[CheckApiKey] TELEMETRIA_API_KEY não configurada — ingestão bloqueada.');

            return response()->json([
                'error' => 'Serviço de ingestão indisponível.'
            ], 503);
        }

        // Comparação em tempo constante (evita timing attack).
        if ($fornecida === '' || ! hash_equals($apiKey, $fornecida)) {
            return response()->json([
                'error' => 'Não autorizado. Chave de API inválida ou ausente.'
            ], 401);
        }

        return $next($request);
    }
}
