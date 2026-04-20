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

    /* Estilos para Popups e Ícones */
    .custom-div-icon { background: none; border: none; }
    .marker-pin {
        width: 38px; height: 38px; border-radius: 50% 50% 50% 0;
        position: absolute; transform: rotate(-45deg);
        left: 50%; top: 50%; margin: -19px 0 0 -19px;
        border: 3px solid #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }
    .custom-div-icon i {
        position: absolute; width: 38px; font-size: 16px;
        left: 0; top: 8px; text-align: center; color: #fff;
    }

    .panic-pulse {
        animation: pulse-red 1.5s infinite;
        background-color: #ef4444; /* Default panic color */
    }

    @keyframes pulse-red {
        0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); }
        100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }

    .popup-title { font-weight: 700; color: #fff; border-bottom: 1px solid #444; padding-bottom: 5px; margin-bottom: 8px; font-size: 14px; }
    .popup-row { font-size: 12px; margin-bottom: 3px; color: #ccc; }
    .popup-row span { font-weight: 600; color: #fff; }
    .badge-status { padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
    .badge-on { background: #22c55e; color: #fff; } /* Green */
    .badge-off { background: #64748b; color: #fff; } /* Slate */
    .badge-panic { background: #ef4444; color: #fff; animation: blink 1s infinite; }
    
    @keyframes blink { 50% { opacity: 0.6; } }
    
    .leaflet-popup-content-wrapper { background: #1e293b; color: #fff; border-radius: 8px; padding: 5px; }
    .leaflet-popup-tip { background: #1e293b; }
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
    <button class="btn btn-primary btn-sm" onclick="sincronizar()">
        <i class="fas fa-rotate"></i> Atualizar
    </button>
    <span id="sync-status" style="font-size:.78rem;color:var(--muted);margin-left:auto">
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
let primeiraCarga = true;
const markers = {};

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

    console.log('[WebSocket] Echo Inicializado em:', reverbHost, 'Key:', reverbKey);

    window.Echo.channel('rastreamento')
        .listen('.sos.changed', (e) => {
            const r = e.rastreador;
            console.log(`[WebSocket] Sinal Recebido - Rastreador ${r.imei}: Panico=${r.em_panico}`);
            atualizarRastreadorNoMapa(r);
        });

    window.Echo.connector.pusher.connection.bind('state_change', (states) => {
        console.log('[WebSocket] Conexão:', states.current);
        const color = states.current === 'connected' ? '#22c55e' : '#94a3b8';
        const text = states.current === 'connected' ? 'Conectado (Real-time)' : 'Sincronizando (Polling)...';
        $('#sync-status').html(`<i class="fas fa-circle" style="color:${color}; font-size: 8px;"></i> ${text}`);
    });

} catch (e) {
    console.warn('[WebSocket] Falha na inicialização. Usando apenas Polling.', e);
}

// Inicializa mapa 
const map = L.map('map', {
    center: [-15.7801, -47.9292],
    zoom: 5,
    zoomControl: false
});
L.control.zoom({ position: 'bottomright' }).addTo(map);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

// Função Universal para Atualizar um Rastreador no Mapa
function atualizarRastreadorNoMapa(r) {
    // Normalização de dados (suporta dados do banco e do WS)
    const lat = parseFloat(r.latitude || r.ultima_latitude || 0);
    const lon = parseFloat(r.longitude || r.ultima_longitude || 0);
    const panico = !!r.em_panico;
    const ignicao = !!r.ignicao;
    
    if (lat === 0) return;

    let marker = markers[r.imei];

    const icon = L.divIcon({
        className: 'custom-div-icon',
        html: `<div style="background-color: ${panico ? '#ef4444' : '#3b82f6'};" class="marker-pin ${panico ? 'panic-pulse' : ''}"></div><i class="fas fa-truck" style="color: white;"></i>`,
        iconSize: [40, 40],
        iconAnchor: [20, 40]
    });

    const content = `
        <div class="popup-title"><i class="fas fa-truck"></i> ${r.nome}</div>
        <div class="popup-row">IMEI: <span>${r.imei}</span></div>
        <div class="popup-row">Botão SOS: <span class="badge-status ${panico ? 'badge-panic':'badge-off'}">${panico ? 'ATIVADO':'DESATIVADO'}</span></div>
        <div class="popup-row">Velocidade: <span>${r.velocidade || 0} km/h</span></div>
        <div class="popup-row">Carga: <span>${r.data_hora || '-'}</span></div>
    `;

    if (marker) {
        marker.setLatLng([lat, lon]);
        marker.setIcon(icon);
        marker.getPopup().setContent(content);
    } else {
        marker = L.marker([lat, lon], { icon: icon }).addTo(map);
        marker.bindPopup(content);
        markers[r.imei] = marker;
    }
}

// Sincronização AJAX (Polling como Backup)
function sincronizar() {
    $.get('/api/v1/rastreadores?_t=' + Date.now(), function(data) {
        console.log('[Polling] Atualizando dados via API...');
        data.forEach(r => atualizarRastreadorNoMapa(r));
        
        if (data.length > 0 && primeiraCarga) {
            const group = new L.featureGroup(Object.values(markers));
            map.fitBounds(group.getBounds().pad(0.1));
            primeiraCarga = false;
        }
        
        // Efeito visual de atualização
        if (!window.Echo || window.Echo.connector.pusher.connection.state !== 'connected') {
             $('#sync-status').fadeOut(100).fadeIn(100);
        }
    });
}

// Filtro de seleção
document.getElementById('filtroRastreador').addEventListener('change', function() {
    const id = this.value;
    if (!id) {
         const group = new L.featureGroup(Object.values(markers));
         map.fitBounds(group.getBounds().pad(0.1));
         return;
    }
    // Procura o marker pelo IMEI que está no select (ou ID se você preferir)
    // Para simplificar, vamos no primeiro que der match se o ID for do banco
    $.get('/api/v1/rastreadores', function(data) {
        const found = data.find(it => it.id == id);
        if (found) {
            const m = markers[found.imei];
            if (m) { map.setView(m.getLatLng(), 16); m.openPopup(); }
        }
    });
});

// Inicialização
sincronizar();
setInterval(sincronizar, 20000); // Polling a cada 20s como garantia
</script>
@endpush
