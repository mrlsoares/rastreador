@extends('layouts.rastreador')

@section('title', 'Histórico ESP32')

@section('content')
<div class="page-header">
    <h1>Histórico ESP32</h1>
    <p>Consulta de telemetria por empresa, dispositivo e período — e a última leitura gravada.</p>
</div>

{{-- ── Filtros ── --}}
<div class="card" style="margin-bottom:1.5rem">
    <form method="GET" action="{{ route('esp32.historico') }}" class="filters">
        <div class="form-group">
            <label>Empresa</label>
            <select id="filtroEmpresa" name="empresa_id" style="min-width:180px">
                <option value="">Todas</option>
                @foreach($empresas as $e)
                    <option value="{{ $e->id }}" @selected(request('empresa_id') == $e->id)>
                        {{ $e->nome_fantasia }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>Dispositivo</label>
            <select id="filtroDispositivo" name="dispositivo" style="min-width:260px" required>
                <option value="">Selecione…</option>
                @foreach($dispositivos as $d)
                    <option value="{{ $d->identificador }}"
                            data-empresa="{{ $d->empresa_id }}"
                            @selected(request('dispositivo') == $d->identificador)>
                        {{ $d->nome ?: $d->identificador }} ({{ $d->identificador }})
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label>Início</label>
            <input type="date" name="data_inicio" value="{{ request('data_inicio', now()->subDays(7)->format('Y-m-d')) }}">
        </div>

        <div class="form-group">
            <label>Fim</label>
            <input type="date" name="data_fim" value="{{ request('data_fim', now()->format('Y-m-d')) }}">
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-magnifying-glass"></i> Consultar
        </button>
    </form>
</div>

{{-- ── Última leitura ── --}}
@if($dispositivo)
    <div class="card" style="margin-bottom:1.5rem">
        <div class="table-header" style="border:none;padding:0 0 1rem">
            <h2><i class="fas fa-satellite-dish"></i> Última leitura — {{ $dispositivo->nome ?: $dispositivo->identificador }}</h2>
            @if($ultima && $ultima->botao_panico)
                <span class="badge red"><i class="fas fa-triangle-exclamation"></i> PÂNICO</span>
            @endif
        </div>

        @if($ultima)
            @php $extra = $ultima->payload_extra ?? []; @endphp
            <div class="cards" style="margin:0">
                <div class="card card-stat">
                    <div><div class="value">{{ $ultima->data_hora?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}</div><div class="label">Data/Hora</div></div>
                    <div class="icon blue"><i class="fas fa-clock"></i></div>
                </div>
                <div class="card card-stat">
                    <div><div class="value">{{ number_format($ultima->bateria_vcc, 2, ',', '') }} V</div><div class="label">Bateria</div></div>
                    <div class="icon green"><i class="fas fa-battery-three-quarters"></i></div>
                </div>
                <div class="card card-stat">
                    <div><div class="value">{{ number_format($ultima->temperatura, 1, ',', '') }} °C</div><div class="label">Temperatura</div></div>
                    <div class="icon amber"><i class="fas fa-temperature-half"></i></div>
                </div>
                <div class="card card-stat">
                    <div><div class="value">{{ $extra['tanque'] ?? $extra['nivel_tanque'] ?? '—' }}</div><div class="label">Tanque</div></div>
                    <div class="icon blue"><i class="fas fa-gas-pump"></i></div>
                </div>
                <div class="card card-stat">
                    <div><div class="value">{{ number_format((float) $ultima->latitude, 5, ',', '') }}, {{ number_format((float) $ultima->longitude, 5, ',', '') }}</div><div class="label">Coordenadas</div></div>
                    <div class="icon blue"><i class="fas fa-location-dot"></i></div>
                </div>
            </div>
        @else
            <p style="color:var(--muted)">Nenhuma telemetria gravada para este dispositivo.</p>
        @endif
    </div>
@endif

{{-- ── Histórico ── --}}
@if($telemetrias !== null)
    <div class="table-wrap">
        <div class="table-header">
            <h2>Histórico</h2>
            <span style="color:var(--muted);font-size:.8rem">{{ $telemetrias->total() }} registro(s)</span>
        </div>

        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Bateria</th>
                    <th>Temp.</th>
                    <th>Vel.</th>
                    <th>Pânico</th>
                    <th>Tanque</th>
                </tr>
            </thead>
            <tbody>
                @forelse($telemetrias as $t)
                    @php $ex = $t->payload_extra ?? []; @endphp
                    <tr>
                        <td>{{ $t->data_hora?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}</td>
                        <td>{{ number_format((float) $t->latitude, 5, ',', '') }}</td>
                        <td>{{ number_format((float) $t->longitude, 5, ',', '') }}</td>
                        <td>{{ number_format($t->bateria_vcc, 2, ',', '') }} V</td>
                        <td>{{ number_format($t->temperatura, 1, ',', '') }} °C</td>
                        <td>{{ $t->velocidade }}</td>
                        <td>
                            @if($t->botao_panico)
                                <span class="badge red">Sim</span>
                            @else
                                <span class="badge green">não</span>
                            @endif
                        </td>
                        <td>{{ $ex['tanque'] ?? $ex['nivel_tanque'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;color:var(--muted)">Nenhum registro no período.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>

        @if($telemetrias->hasPages())
            <div class="pagination">
                @if($telemetrias->onFirstPage())
                    <span>‹ Anterior</span>
                @else
                    <a href="{{ $telemetrias->previousPageUrl() }}">‹ Anterior</a>
                @endif
                <span class="active">{{ $telemetrias->currentPage() }} / {{ $telemetrias->lastPage() }}</span>
                @if($telemetrias->hasMorePages())
                    <a href="{{ $telemetrias->nextPageUrl() }}">Próxima ›</a>
                @else
                    <span>Próxima ›</span>
                @endif
            </div>
        @endif
    </div>
@endif
@endsection

@push('scripts')
<script>
    // Filtra os dispositivos do combo pela empresa selecionada.
    (function () {
        const emp  = document.getElementById('filtroEmpresa');
        const disp = document.getElementById('filtroDispositivo');
        if (!emp || !disp) return;

        function aplicar() {
            const id = emp.value;
            for (const opt of disp.options) {
                if (!opt.value) continue; // "Selecione…"
                const show = !id || opt.dataset.empresa === id;
                opt.hidden = !show;
                if (!show && opt.selected) disp.value = '';
            }
        }
        emp.addEventListener('change', aplicar);
        aplicar();
    })();
</script>
@endpush
