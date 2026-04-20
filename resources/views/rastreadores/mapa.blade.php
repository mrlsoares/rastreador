@extends('layouts.app')

@section('title', 'Mapa ao Vivo')

@push('styles')
<style>
    #map { height: calc(100vh - 160px); border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
    .map-controls {
        display: flex; gap: .75rem; flex-wrap: wrap;
        margin-bottom: 1rem; align-items: center;
    }
    .map-controls select { flex: 1; min-width: 200px; }
    
    @media (max-width: 768px) {
        #map { height: calc(100vh - 120px); border-radius: 0; margin: 0 -1rem; border-left: none; border-right: none; }
        .page-header { display: none; }
        .map-controls { gap: .5rem; }
        .map-controls select { width: 100%; order: 1; }
        .map-controls .btn { flex: 1; order: 2; font-size: .75rem; padding: .5rem; }
        .map-controls span { width: 100%; order: 3; text-align: center; margin-top: .25rem; }
    }
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
    <span id="syncText" style="font-size:.78rem;color:var(--muted);margin-left:auto">
        <i class="fas fa-sync" style="font-size:.7rem"></i> Sincronizando...
    </span>
</div>

<div id="map"></div>
@endsection

@push('scripts')
<!-- WebSockets: Pusher & Echo -->
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.3.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>

<script>
let posicoes = @json($ultimasPosicoes);
const marcadores = {};
const camadaMarcadores = L.layerGroup();
let primeiraCarga = true;

// --- Configuração WebSocket (Reverb) ---
try {
    const reverbKey = '{{ config('reverb.apps.0.key') }}' || 'dtkuedmal9qpzrhoe1xa';
    const reverbHost = '{{ config('reverb.apps.0.options.host') }}' || window.location.hostname;
    const isHttps = (window.location.protocol === 'https:');
    
    window.Pusher = Pusher;
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: isHttps ? 443 : 8080,
        wssPort: isHttps ? 443 : 8080,
        forceTLS: isHttps,
        enabledTransports: ['ws', 'wss'],
    });

    console.log('Echo Inicializado:', reverbHost, 'Key:', reverbKey);
} catch (e) {
    console.warn('Erro ao carregar WebSockets. Usando Polling.', e);
}

// Inicializa mapa 
const map = L.map('map', {
    center: [-15.7801, -47.9292],
    zoom: 5,
    zoomControl: true,
});

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 19,
}).addTo(map);

camadaMarcadores.addTo(map);

function transformarItem(r) {
    return {
        id: r.id, imei: r.imei, nome: r.nome, placa: r.placa,
        ignicao: !!r.ignicao, em_panico: !!r.em_panico,
        lat: parseFloat(r.ultima_latitude || 0), 
        lon: parseFloat(r.ultima_longitude || 0),
        velocidade: parseInt(r.velocidade || 0),
        data_hora: new Date().toLocaleString('pt-BR')
    };
}

function transformarDadosApi(dados) {
    return dados.map(r => {
        const u = r.ultima_posicao;
        if (!u) return null;
        const item = transformarItem(r);
        item.lat = parseFloat(u.latitude);
        item.lon = parseFloat(u.longitude);
        item.velocidade = parseInt(u.velocidade);
        item.data_hora = new Date(u.data_hora).toLocaleString('pt-BR');
        return item;
    }).filter(f => f);
}

function criarIcone(r) {
    let cor1 = "#0ea5e9", cor2 = "#0284c7", pulse = "";
    if (r.em_panico) {
        cor1 = "#ef4444"; cor2 = "#b91c1c";
        pulse = "animation: pulse-red 1.5s infinite;";
    } else if (!r.ignicao) {
        cor1 = "#94a3b8"; cor2 = "#475569";
    }
    return L.divIcon({
        html: `<div style="width:32px; height:32px; background:linear-gradient(135deg,${cor1},${cor2}); border:3px solid #fff; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(0,0,0,.5); font-size:13px; color:#fff; ${pulse}"><i class="fas fa-truck"></i></div>`,
        className: '', iconSize: [32, 32], iconAnchor: [16, 16], popupAnchor: [0, -18],
    });
}

function adicionarMarcadores(dados) {
    dados.forEach(r => {
        const content = `
            <div class="popup-title"><i class="fas fa-truck"></i> ${r.nome}</div>
            <div class="popup-row">IMEI: <span>${r.imei}</span></div>
            <div class="popup-row">Botão SOS: <span class="badge-status ${r.em_panico ? 'badge-panic':'badge-off'}">${r.em_panico ? 'ATIVADO':'DESATIVADO'}</span></div>
            <div class="popup-row">Velocidade: <span>${r.velocidade} km/h</span></div>
            <div class="popup-row">Carga: <span>${r.data_hora}</span></div>
        `;

        if (marcadores[r.id]) {
            marcadores[r.id].setLatLng([r.lat, r.lon]);
            marcadores[r.id].setIcon(criarIcone(r));
            marcadores[r.id].setPopupContent(content);
        } else {
            const m = L.marker([r.lat, r.lon], { icon: criarIcone(r) }).bindPopup(content);
            marcadores[r.id] = m;
            camadaMarcadores.addLayer(m);
        }
    });

    if (primeiraCarga && Object.keys(marcadores).length > 0) {
        primeiraCarga = false;
        centrarMapa();
    }
}

async function atualizarMapa() {
    const sync = document.getElementById('syncText');
    if(sync) sync.style.opacity = '1';
    
    try {
        const res = await fetch('/api/v1/rastreadores');
        const rData = await res.json();
        posicoes = transformarDadosApi(rData);
        adicionarMarcadores(posicoes);
        if(sync) sync.innerHTML = `<i class="fas fa-check" style="color:var(--success)"></i> Atualizado: ${new Date().toLocaleTimeString('pt-BR')}`;
    } catch (e) {
        console.error(e);
        if(sync) sync.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Erro ao sincronizar';
    }
}

function centrarMapa() {
    const values = Object.values(marcadores);
    if (!values.length) return;
    const group = L.featureGroup(values);
    map.fitBounds(group.getBounds().pad(.2));
    if (map.getZoom() > 16) map.setZoom(16);
}

// Ouvir WebSockets (Tempo Real)
window.Echo.channel('rastreamento')
    .listen('.sos.changed', (e) => {
        console.log('Update WebSocket recebido:', e);
        const item = transformarItem(e.rastreador);
        adicionarMarcadores([item]);
    });

// Filtro
document.getElementById('filtroRastreador').addEventListener('change', function() {
    const id = this.value;
    if (!id) return centrarMapa();
    const m = marcadores[id];
    if (m) { map.setView(m.getLatLng(), 16); m.openPopup(); }
});

// Loop (Mantido como fallback caso o socket caia)
adicionarMarcadores(posicoes);
setInterval(atualizarMapa, 30000); // Aumentado para 30s pois o WebSocket é o principal
setTimeout(atualizarMapa, 1000);
</script>
@endpush
