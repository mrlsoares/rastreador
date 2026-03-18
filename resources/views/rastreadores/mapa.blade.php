@extends('layouts.app')

@section('title', 'Mapa ao Vivo')

@push('styles')
<style>
    #map { height: calc(100vh - 160px); border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
    .map-controls {
        display: flex; gap: .75rem; flex-wrap: wrap;
        margin-bottom: 1rem; align-items: center;
    }
    .leaflet-popup-content { font-family: 'Inter', sans-serif; min-width: 180px; }
    .popup-title { font-weight: 700; font-size: .9rem; color: #0ea5e9; margin-bottom: .4rem; }
    .popup-row   { font-size: .8rem; color: #94a3b8; margin: .2rem 0; }
    .popup-row span { color: #e2e8f0; font-weight: 500; }
</style>
@endpush

@section('content')
<div class="page-header">
    <h1><i class="fas fa-map" style="color:var(--primary)"></i> Mapa ao Vivo</h1>
    <p>Última posição conhecida de cada rastreador</p>
</div>

<div class="map-controls">
    <select id="filtroRastreador">
        <option value="">Todos os rastreadores</option>
        @foreach($rastreadores as $r)
            <option value="{{ $r->id }}">{{ $r->nome }} {{ $r->placa ? '('.$r->placa.')' : '' }}</option>
        @endforeach
    </select>

    <button class="btn btn-ghost btn-sm" onclick="centrarMapa()">
        <i class="fas fa-crosshairs"></i> Centralizar
    </button>
    <button class="btn btn-primary btn-sm" onclick="atualizarMapa()">
        <i class="fas fa-rotate"></i> Atualizar
    </button>
    <span style="font-size:.78rem;color:var(--muted);margin-left:auto">
        <i class="fas fa-circle" style="color:var(--success);font-size:.6rem"></i>
        {{ $ultimasPosicoes->count() }} rastreador(es) com posição
    </span>
</div>

<div id="map"></div>
@endsection

@push('scripts')
<script>
// Dados das últimas posições (passados pelo controller)
const posicoes = @json($ultimasPosicoes);

// Inicializa mapa centrado no Brasil
const map = L.map('map', {
    center: [-15.7801, -47.9292],
    zoom: 5,
    zoomControl: true,
});

// Tile layer OpenStreetMap (sem API key necessária)
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/">OpenStreetMap</a>',
    maxZoom: 19,
}).addTo(map);

// Ícone personalizado para rastreador
const iconePadrao = L.divIcon({
    html: `<div style="
        width:32px; height:32px;
        background:linear-gradient(135deg,#0ea5e9,#0284c7);
        border:3px solid #fff;
        border-radius:50%;
        display:flex; align-items:center; justify-content:center;
        box-shadow:0 2px 8px rgba(0,0,0,.5);
        font-size:13px; color:#fff;
    "><i class="fas fa-truck"></i></div>`,
    className: '',
    iconSize: [32, 32],
    iconAnchor: [16, 16],
    popupAnchor: [0, -18],
});

const marcadores    = {};
const camadaMarcadores = L.layerGroup().addTo(map);

function adicionarMarcadores(dados) {
    camadaMarcadores.clearLayers();

    dados.forEach(r => {
        if (!r.lat || !r.lon) return;

        const marker = L.marker([r.lat, r.lon], { icon: iconePadrao })
            .bindPopup(`
                <div class="popup-title">
                    <i class="fas fa-truck"></i> ${r.nome}
                </div>
                ${r.placa ? `<div class="popup-row">Placa: <span>${r.placa}</span></div>` : ''}
                <div class="popup-row">Lat/Lon: <span>${r.lat.toFixed(6)}, ${r.lon.toFixed(6)}</span></div>
                <div class="popup-row">Velocidade: <span>${r.velocidade ?? 0} km/h</span></div>
                <div class="popup-row">Últ. contato: <span>${r.data_hora ?? '—'}</span></div>
                <div style="margin-top:.6rem">
                    <a href="/rastreadores/${r.id}/historico" style="font-size:.78rem;color:#0ea5e9;text-decoration:none">
                        <i class="fas fa-clock-rotate-left"></i> Ver histórico
                    </a>
                </div>
            `);

        marcadores[r.id] = marker;
        camadaMarcadores.addLayer(marker);
    });

    // Ajusta zoom para exibir todos os marcadores
    if (Object.keys(marcadores).length > 0) {
        const grupo = L.featureGroup(Object.values(marcadores));
        map.fitBounds(grupo.getBounds().pad(.2));
    }
}

// Filtra marcadores por rastreador selecionado
document.getElementById('filtroRastreador').addEventListener('change', function () {
    const id = this.value;
    if (!id) {
        adicionarMarcadores(posicoes);
    } else {
        const filtrado = posicoes.filter(r => r.id == id);
        adicionarMarcadores(filtrado);
        if (filtrado.length && marcadores[filtrado[0].id]) {
            map.setView([filtrado[0].lat, filtrado[0].lon], 15);
            marcadores[filtrado[0].id].openPopup();
        }
    }
});

function centrarMapa() {
    if (Object.keys(marcadores).length > 0) {
        const grupo = L.featureGroup(Object.values(marcadores));
        map.fitBounds(grupo.getBounds().pad(.2));
    } else {
        map.setView([-15.78, -47.93], 5);
    }
}

async function atualizarMapa() {
    try {
        const res  = await fetch('/api/v1/rastreadores');
        const data = await res.json();
        // Recarrega página para buscar últimas posições atualizadas
        window.location.reload();
    } catch (e) {
        console.error('Erro ao atualizar:', e);
    }
}

// Renderiza na carga inicial
adicionarMarcadores(posicoes);
</script>
@endpush
