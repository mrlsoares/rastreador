@extends('layouts.rastreador')

@section('title', 'Última Leitura ESP32')

@section('content')
<div class="page-header">
    <h1>Última Leitura ESP32</h1>
    <p>Último registro gravado de um dispositivo.</p>
</div>

{{-- ── Filtros ── --}}
<div class="card" style="margin-bottom:1.5rem">
    <form method="GET" action="{{ route('esp32.ultima') }}" class="filters">
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

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-magnifying-glass"></i> Consultar
        </button>
    </form>
</div>

@if($dispositivo)
    <div class="table-wrap">
        <div class="table-header">
            <h2><i class="fas fa-satellite-dish"></i> {{ $dispositivo->nome ?: $dispositivo->identificador }}
                <span style="color:var(--muted);font-weight:400">({{ $dispositivo->identificador }})</span>
            </h2>
            @if($ultima && $ultima->botao_panico)
                <span class="badge red"><i class="fas fa-triangle-exclamation"></i> PÂNICO</span>
            @elseif($ultima)
                <span class="badge green">OK</span>
            @endif
        </div>

        @if($ultima)
            @php $extra = $ultima->payload_extra ?? []; @endphp
            <div class="cards" style="margin:1.25rem">
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
                    <div><div class="value">{{ $ultima->velocidade }} km/h</div><div class="label">Velocidade</div></div>
                    <div class="icon blue"><i class="fas fa-gauge-high"></i></div>
                </div>
                <div class="card card-stat">
                    <div><div class="value">{{ $extra['tanque'] ?? $extra['nivel_tanque'] ?? '—' }}</div><div class="label">Tanque</div></div>
                    <div class="icon blue"><i class="fas fa-gas-pump"></i></div>
                </div>
                <div class="card card-stat">
                    <div><div class="value">{{ number_format((float) $ultima->latitude, 5, ',', '') }}, {{ number_format((float) $ultima->longitude, 5, ',', '') }}</div><div class="label">Coordenadas</div></div>
                    <div class="icon blue"><i class="fas fa-location-dot"></i></div>
                </div>
                <div class="card card-stat">
                    <div><div class="value">
                        @if($ultima->botao_panico)<span style="color:var(--danger)">SIM</span>@else não @endif
                    </div><div class="label">Pânico</div></div>
                    <div class="icon {{ $ultima->botao_panico ? 'red' : 'green' }}"><i class="fas fa-bell"></i></div>
                </div>
                <div class="card card-stat">
                    <div><div class="value">{{ $extra['sinal_gsm'] ?? '—' }}</div><div class="label">Sinal GSM (dBm)</div></div>
                    <div class="icon blue"><i class="fas fa-signal"></i></div>
                </div>
            </div>

            <div style="padding:0 1.25rem 1.25rem;color:var(--muted);font-size:.8rem">
                Último contato do dispositivo:
                {{ $dispositivo->ultimo_contato?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') ?? '—' }}
            </div>
        @else
            <p style="padding:1.5rem;color:var(--muted)">Nenhuma telemetria gravada para este dispositivo.</p>
        @endif
    </div>
@endif
@endsection

@push('scripts')
<script>
    (function () {
        const emp  = document.getElementById('filtroEmpresa');
        const disp = document.getElementById('filtroDispositivo');
        if (!emp || !disp) return;
        function aplicar() {
            const id = emp.value;
            for (const opt of disp.options) {
                if (!opt.value) continue;
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
