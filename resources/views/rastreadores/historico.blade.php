@extends('layouts.app')

@section('title', 'Histórico — ' . $rastreador->nome)

@push('styles')
<style>
    #mapa-historico { height: 380px; border-radius: 10px; overflow: hidden; border: 1px solid var(--border); margin-bottom: 1.5rem; }
    .velocidade-bar {
        display: flex; align-items: center; gap: .5rem;
    }
    .vel-fill {
        height: 6px; border-radius: 3px;
        background: linear-gradient(90deg, var(--success), var(--accent), var(--danger));
        min-width: 4px;
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <h1>
        <i class="fas fa-clock-rotate-left" style="color:var(--primary)"></i>
        Histórico — {{ $rastreador->nome }}
    </h1>
    <p>
        @if($rastreador->placa) Placa: <strong>{{ $rastreador->placa }}</strong> &bull; @endif
        IMEI: <code style="font-size:.8rem;opacity:.7">{{ $rastreador->imei }}</code>
    </p>
</div>

<!-- Filtros -->
<div class="table-wrap" style="margin-bottom:1.25rem">
    <div style="padding:1rem 1.25rem">
        <form method="GET" action="{{ route('rastreadores.historico', $rastreador) }}">
            <div class="filters">
                <div class="form-group">
                    <label>Rastreador</label>
                    <select name="rastreador_redirect" onchange="window.location='/rastreadores/'+this.value+'/historico'">
                        @foreach($rastreadores as $r)
                            <option value="{{ $r->id }}" {{ $r->id == $rastreador->id ? 'selected' : '' }}>
                                {{ $r->nome }} {{ $r->placa ? '('.$r->placa.')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Data Início</label>
                    <input type="date" name="data_inicio" value="{{ $dataInicio->format('Y-m-d') }}">
                </div>

                <div class="form-group">
                    <label>Data Fim</label>
                    <input type="date" name="data_fim" value="{{ $dataFim->format('Y-m-d') }}">
                </div>

                <button type="submit" class="btn btn-primary" style="align-self:flex-end">
                    <i class="fas fa-filter"></i> Filtrar
                </button>

                <a href="{{ route('rastreadores.historico', $rastreador) }}" class="btn btn-ghost" style="align-self:flex-end">
                    <i class="fas fa-rotate"></i> Hoje
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Mapa do percurso -->
@if($posicoes->count() > 0)
<div id="mapa-historico"></div>
@endif

<!-- Tabela de posições -->
<div class="table-wrap">
    <div class="table-header">
        <h2>
            <i class="fas fa-location-dot" style="color:var(--primary)"></i>
            Posições
            <span style="font-size:.8rem;font-weight:400;color:var(--muted);margin-left:.5rem">
                ({{ $posicoes->total() }} registros)
            </span>
        </h2>
    </div>

    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>Data / Hora</th>
            <th>Latitude</th>
            <th>Longitude</th>
            <th>Velocidade</th>
            <th>Direção</th>
            <th>Sinal GPS</th>
        </tr>
        </thead>
        <tbody>
        @forelse($posicoes as $i => $p)
            <tr>
                <td style="color:var(--muted);font-size:.78rem">{{ $posicoes->firstItem() + $i }}</td>
                <td style="white-space:nowrap">{{ $p->data_hora->format('d/m/Y H:i:s') }}</td>
                <td style="font-family:monospace;font-size:.82rem">{{ number_format($p->latitude, 6) }}</td>
                <td style="font-family:monospace;font-size:.82rem">{{ number_format($p->longitude, 6) }}</td>
                <td>
                    <div class="velocidade-bar">
                        <div class="vel-fill" style="width:{{ min($p->velocidade / 120 * 80, 80) }}px"></div>
                        <span style="font-size:.82rem">{{ $p->velocidade }} km/h</span>
                    </div>
                </td>
                <td>
                    <span class="badge blue">{{ $p->direcao ?? '—' }}</span>
                </td>
                <td>
                    @php $sinal = $p->sinal_gps; @endphp
                    <span class="badge {{ $sinal >= 7 ? 'green' : ($sinal >= 4 ? 'amber' : 'red') }}">
                        <i class="fas fa-signal" style="font-size:.65rem"></i> {{ $sinal }}/9
                    </span>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">
                    <i class="fas fa-location-dot" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.75rem"></i>
                    Nenhuma posição encontrada no período selecionado.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>

    @if($posicoes->hasPages())
    <div class="pagination">
        {!! $posicoes->links('pagination::simple-default') !!}
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
@if($posicoes->count() > 0)
// Dados para o mapa do percurso (somente página atual)
const pontos = @json($posicoes->map(fn($p) => [$p->latitude, $p->longitude, $p->velocidade]));

const mapaHistorico = L.map('mapa-historico').setView(pontos[0], 13);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 19,
}).addTo(mapaHistorico);

// Polyline do percurso
const coordenadas = pontos.map(p => [p[0], p[1]]);
L.polyline(coordenadas, {
    color: '#0ea5e9',
    weight: 3,
    opacity: .8,
    dashArray: null,
}).addTo(mapaHistorico);

// Marcador de início
L.circleMarker(coordenadas[0], {
    radius: 8, fillColor: '#22c55e', color: '#fff', weight: 2, fillOpacity: 1,
}).addTo(mapaHistorico).bindPopup('<b style="color:#22c55e">Início</b>');

// Marcador de fim
if (coordenadas.length > 1) {
    L.circleMarker(coordenadas[coordenadas.length - 1], {
        radius: 8, fillColor: '#ef4444', color: '#fff', weight: 2, fillOpacity: 1,
    }).addTo(mapaHistorico).bindPopup('<b style="color:#ef4444">Fim</b>');
}

// Ajusta zoom para o percurso
mapaHistorico.fitBounds(L.polyline(coordenadas).getBounds().pad(.1));
@endif
</script>
@endpush
