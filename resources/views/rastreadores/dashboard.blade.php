@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-gauge-high" style="color:var(--primary)"></i> Dashboard</h1>
    <p>Visão geral dos rastreadores TRX-16</p>
</div>

<!-- Cards de estatísticas -->
<div class="cards">
    <div class="card">
        <div class="card-stat">
            <div>
                <div class="value">{{ $totalAtivos }}</div>
                <div class="label">Rastreadores Ativos</div>
            </div>
            <div class="icon blue"><i class="fas fa-satellite-dish"></i></div>
        </div>
    </div>
    <div class="card">
        <div class="card-stat">
            <div>
                <div class="value">{{ number_format($totalPosicoes) }}</div>
                <div class="label">Total de Posições</div>
            </div>
            <div class="icon green"><i class="fas fa-location-dot"></i></div>
        </div>
    </div>
    <div class="card">
        <div class="card-stat">
            <div>
                <div class="value">
                    {{ $rastreadores->filter(fn($r) => optional($r->posicoes->first())?->data_hora?->isToday())->count() }}
                </div>
                <div class="label">Com Sinal Hoje</div>
            </div>
            <div class="icon amber"><i class="fas fa-signal"></i></div>
        </div>
    </div>
</div>

<!-- Tabela de rastreadores -->
<div class="table-wrap">
    <div class="table-header">
        <h2><i class="fas fa-truck" style="color:var(--primary)"></i> Rastreadores</h2>
        <div style="display:flex;gap:.5rem">
            <a href="{{ route('mapa') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-map"></i> Abrir Mapa
            </a>
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th>Status</th>
            <th>Nome / Placa</th>
            <th>IMEI</th>
            <th>Último Contato</th>
            <th>Posições</th>
            <th>Ações</th>
        </tr>
        </thead>
        <tbody>
        @forelse($rastreadores as $r)
            @php
                $ultima   = $r->posicoes->first();
                $online   = $r->ultimo_contato && $r->ultimo_contato->diffInMinutes() < 10;
                $hoje     = $ultima && $ultima->data_hora->isToday();
            @endphp
            <tr>
                <td>
                    <span class="status-dot {{ $online ? 'online' : 'offline' }}"></span>
                    <span style="font-size:.75rem;color:var(--muted);margin-left:.4rem">{{ $online ? 'Online' : 'Offline' }}</span>
                </td>
                <td>
                    <div style="font-weight:600">{{ $r->nome }}</div>
                    @if($r->placa)
                        <div style="font-size:.75rem;color:var(--muted)">{{ $r->placa }}</div>
                    @endif
                </td>
                <td style="font-family:monospace;font-size:.82rem;color:var(--muted)">{{ $r->imei }}</td>
                <td style="color:var(--muted);font-size:.82rem">
                    {{ $r->ultimo_contato ? $r->ultimo_contato->format('d/m/Y H:i') : '—' }}
                </td>
                <td>
                    <span class="badge blue">{{ number_format($r->posicoes_count) }}</span>
                </td>
                <td>
                    <a href="{{ route('rastreadores.historico', $r) }}" class="btn btn-ghost btn-sm">
                        <i class="fas fa-clock-rotate-left"></i> Histórico
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" style="text-align:center;color:var(--muted);padding:2rem">
                    <i class="fas fa-satellite-dish" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.75rem"></i>
                    Nenhum rastreador encontrado. Aguardando conexão do TRX-16 na porta TCP 5023.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
