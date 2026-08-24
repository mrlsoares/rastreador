<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Esp32Dispositivo;
use App\Models\Esp32Telemetria;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class Esp32MonitorController extends Controller
{
    /**
     * Página de consulta: histórico de telemetria ESP32 por empresa, dispositivo
     * e período, mais a última leitura gravada. Respeita o escopo de empresa do
     * usuário (super-admin/leitor veem tudo).
     */
    public function historico(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'empresa_id'  => 'nullable|integer',
            'dispositivo' => 'nullable|string|max:50',
            'data_inicio' => 'nullable|date',
            'data_fim'    => 'nullable|date|after_or_equal:data_inicio',
        ]);

        $veTudo = $user->hasAnyRole(['super-admin', 'leitor']);

        // Empresas disponíveis no filtro.
        $empresas = $veTudo
            ? Empresa::orderBy('nome_fantasia')->get(['id', 'nome_fantasia'])
            : Empresa::whereKey($user->empresa_id)->get(['id', 'nome_fantasia']);

        // Dispositivos no escopo do usuário (para o combo).
        $dispositivos = Esp32Dispositivo::daEmpresaDoUsuario($user)
            ->with('empresa:id,nome_fantasia')
            ->orderBy('nome')
            ->get(['id', 'identificador', 'nome', 'empresa_id']);

        $dispositivo = null;
        $ultima      = null;
        $telemetrias = null;

        $mac = $request->input('dispositivo');
        if ($mac) {
            $dispositivo = Esp32Dispositivo::daEmpresaDoUsuario($user)
                ->where('identificador', $mac)
                ->with('ultimaTelemetria')
                ->first();

            if ($dispositivo) {
                $ultima = $dispositivo->ultimaTelemetria;

                $ini = $request->filled('data_inicio')
                    ? Carbon::parse($request->data_inicio, 'America/Sao_Paulo')->startOfDay()->setTimezone('UTC')
                    : Carbon::now('America/Sao_Paulo')->subDays(7)->startOfDay()->setTimezone('UTC');

                $fim = $request->filled('data_fim')
                    ? Carbon::parse($request->data_fim, 'America/Sao_Paulo')->endOfDay()->setTimezone('UTC')
                    : Carbon::now('America/Sao_Paulo')->endOfDay()->setTimezone('UTC');

                $telemetrias = Esp32Telemetria::where('esp32_dispositivo_id', $dispositivo->id)
                    ->whereBetween('data_hora', [$ini, $fim])
                    ->orderByDesc('data_hora')
                    ->paginate(50)
                    ->withQueryString();
            }
        }

        return view('esp32.historico', compact(
            'empresas', 'dispositivos', 'dispositivo', 'ultima', 'telemetrias'
        ));
    }

    /**
     * Consulta a última leitura gravada de um dispositivo (por empresa/dispositivo).
     */
    public function ultima(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'empresa_id'  => 'nullable|integer',
            'dispositivo' => 'nullable|string|max:50',
        ]);

        $veTudo = $user->hasAnyRole(['super-admin', 'leitor']);

        $empresas = $veTudo
            ? Empresa::orderBy('nome_fantasia')->get(['id', 'nome_fantasia'])
            : Empresa::whereKey($user->empresa_id)->get(['id', 'nome_fantasia']);

        $dispositivos = Esp32Dispositivo::daEmpresaDoUsuario($user)
            ->with('empresa:id,nome_fantasia')
            ->orderBy('nome')
            ->get(['id', 'identificador', 'nome', 'empresa_id']);

        $dispositivo = null;
        $ultima      = null;

        $mac = $request->input('dispositivo');
        if ($mac) {
            $dispositivo = Esp32Dispositivo::daEmpresaDoUsuario($user)
                ->where('identificador', $mac)
                ->with('ultimaTelemetria')
                ->first();

            if ($dispositivo) {
                $ultima = $dispositivo->ultimaTelemetria;
            }
        }

        return view('esp32.ultima', compact('empresas', 'dispositivos', 'dispositivo', 'ultima'));
    }
}
