@extends('layouts.app')

@section('title', 'Rastreadores')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-truck" style="color:var(--primary)"></i> Rastreadores</h1>
    <p>Todos os dispositivos TRX-16 cadastrados</p>
</div>

<div class="table-wrap">
    <div class="table-header">
        <h2>Dispositivos ({{ $rastreadores->total() }})</h2>
    </div>

    <table>
        <thead>
        <tr>
            <th>Status</th>
            <th>Nome</th>
            <th>Placa</th>
            <th>IMEI</th>
            <th>Modelo</th>
            <th>Último Contato</th>
            <th>Posições</th>
            <th>Ações</th>
        </tr>
        </thead>
        <tbody>
        @forelse($rastreadores as $r)
            @php $online = $r->ultimo_contato && $r->ultimo_contato->diffInMinutes() < 10; @endphp
            <tr>
                <td>
                    <span class="status-dot {{ $online ? 'online' : 'offline' }}"></span>
                    <span style="font-size:.75rem;color:var(--muted);margin-left:.4rem">{{ $online ? 'Online' : 'Offline' }}</span>
                </td>
                <td style="font-weight:600">{{ $r->nome }}</td>
                <td><span class="badge {{ $r->placa ? 'blue' : '' }}" style="{{ !$r->placa ? 'color:var(--muted)' : '' }}">{{ $r->placa ?: '—' }}</span></td>
                <td style="font-family:monospace;font-size:.78rem;color:var(--muted)">{{ $r->imei }}</td>
                <td style="color:var(--muted);font-size:.82rem">{{ $r->modelo_veiculo ?: '—' }}</td>
                <td style="color:var(--muted);font-size:.82rem;white-space:nowrap">
                    {{ $r->ultimo_contato ? $r->ultimo_contato->format('d/m/Y H:i') : '—' }}
                </td>
                <td><span class="badge blue">{{ number_format($r->posicoes_count) }}</span></td>
                <td style="white-space:nowrap">
                    <a href="{{ route('rastreadores.historico', $r) }}" class="btn btn-ghost btn-sm">
                        <i class="fas fa-clock-rotate-left"></i> Histórico
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" style="text-align:center;color:var(--muted);padding:2.5rem">
                    <i class="fas fa-satellite-dish" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:1rem"></i>
                    Nenhum rastreador cadastrado ainda.<br>
                    <small>Os dispositivos TRX-16 são cadastrados automaticamente quando enviam dados via socket TCP (porta 5023).</small>
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>

    @if($rastreadores->hasPages())
    <div class="pagination">
        {!! $rastreadores->links('pagination::simple-default') !!}
    </div>
    @endif
</div>
@endsection
