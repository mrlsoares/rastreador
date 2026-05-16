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
        $apiKey = config('app.telemetria_api_key') ?: env('TELEMETRIA_API_KEY');

        if (!$request->hasHeader('X-API-KEY') || $request->header('X-API-KEY') !== $apiKey) {
            return response()->json([
                'error' => 'Não autorizado. Chave de API inválida ou ausente.'
            ], 401);
        }

        return $next($request);
    }
}
