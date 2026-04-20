@extends('layouts.app')

@section('content')
<div class="row page-header">
    <div class="col-12">
        <div class="d-flex align-items-center">
            <div class="header-icon"><i class="fas fa-map-location-dot"></i></div>
            <div>
                <h1 class="page-title">Mapa ao Vivo</h1>
                <p class="page-subtitle">Última posição conhecida de cada rastreador</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card map-card">
            <div class="card-header p-2">
                <div class="d-flex align-items-center gap-2">
                    <select id="filtroRastreador" class="form-select form-select-sm" style="max-width: 300px;">
                        <option value="">Todos os rastreadores</option>
                        @foreach($rastreadores as $r)
                            <option value="{{ $r->id }}">Rastreador {{ $r->imei }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-dark btn-sm" onclick="centrarMapa()">
                        <i class="fas fa-crosshairs"></i> Centralizar
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="sincronizar()">
                        <i class="fas fa-rotate"></i> Atualizar
                    </button>
                    <span id="sync-status" style="font-size:.78rem;color:var(--muted);margin-left:auto">
                        <i class="fas fa-sync" style="font-size:.7rem"></i> Sincronizando...
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="map" style="height: 600px; width: 100%; border-radius: 0 0 8px 8px;"></div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .map-card { border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
    .custom-div-icon { background: none; border: none; }
    .marker-pin {
        width: 32px; height: 32px; border-radius: 50% 50% 50% 0;
        background: #3b82f6; position: absolute;
        transform: rotate(-45deg); left: 50%; top: 50%;
        margin: -32px 0 0 -16px; border: 2px solid #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }
    .marker-pin::after {
        content: ''; width: 24px; height: 24px; margin: 2px 0 0 2px;
        background: rgba(255,255,255,0.2); position: absolute; border-radius: 50%;
    }
    .custom-div-icon i {
        position: absolute; width: 32px; font-size: 14px; color: #fff;
        text-align: center; top: 18px; left: 16px; margin-left: -16px; z-index: 10;
    }
    
    .panic-pulse { animation: pulse-red 1.5s infinite; background-color: #ef4444 !important; }
    @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
    
    .badge-status { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
    .badge-off { background: #fee2e2; color: #991b1b; }
    .badge-panic { background: #ef4444; color: #fff; animation: blink 1s infinite; }
    @keyframes blink { 50% { opacity: 0.6; } }
    
    .leaflet-popup-content-wrapper { background: #1e293b; color: #fff; border-radius: 8px; padding: 5px; }
    .leaflet-popup-tip { background: #1e293b; }
    .popup-title { font-weight: 800; border-bottom: 1px solid #444; padding-bottom: 5px; margin-bottom: 5px; color: var(--primary); }
    .popup-row { font-size: 11px; margin-bottom: 3px; display: flex; justify-content: space-between; gap: 10px; }
    .popup-row span { color: #94a3b8; }

    /* Legendas permanentes sobre o ícone */
    .marker-label {
        background: rgba(15, 23, 42, 0.9);
        color: #fff; border: 1px solid var(--primary);
        padding: 4px 10px; border-radius: 4px;
        font-size: 12px; white-space: nowrap;
        box-shadow: 0 4px 10px rgba(0,0,0,0.4);
        pointer-events: none; text-align: center;
    }
    .marker-label b { display: block; font-size: 13px; color: #fff; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 2px; }
    .label-sos-alert { background: #ef4444; color: #fff; font-weight: 800; animation: blink 1s infinite; padding: 1px 4px; border-radius: 2px; margin-top: 2px; font-size: 10px; display: inline-block; }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.3.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
<script>
(function() {
    let primeiraCarga = true;
    const markers = {};
    const map = L.map('map', {
        center: [-15.7801, -47.9292],
        zoom: 5,
        zoomControl: false
    });
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Configuração do Echo com Reverb
    try {
        const reverbKey = "{{ config('reverb.apps.0.key') }}" || "p3tq8onmsh1iv6mlyq0z";
        const reverbHost = window.location.hostname;
        
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: reverbHost,
            wsPort: window.location.protocol === 'https:' ? 443 : 8080,
            wssPort: window.location.protocol === 'https:' ? 443 : 8080,
            forceTLS: (window.location.protocol === 'https:'),
            enabledTransports: ['ws', 'wss'],
        });

        window.Echo.channel('rastreadores')
            .listen('.SosStatusChanged', (e) => {
                console.log('[WebSocket] Sinal Recebido:', e.rastreador.imei);
                if (e.rastreador) atualizarRastreadorNoMapa(e.rastreador);
            });

        window.Echo.connector.pusher.connection.bind('state_change', (states) => {
            console.log('[WebSocket] Conexão:', states.current);
            const color = states.current === 'connected' ? '#22c55e' : '#94a3b8';
            const text = states.current === 'connected' ? 'Conectado (Real-time)' : 'Sincronizando (Polling)...';
            const syncStatus = document.getElementById('sync-status');
            if (syncStatus) {
                syncStatus.innerHTML = `<i class="fas fa-circle" style="color:${color}; font-size: 8px;"></i> ${text}`;
            }
        });

    } catch (e) {
        console.warn('[WebSocket] Falha na inicialização.', e);
    }

    // Função Universal para Atualizar um Rastreador no Mapa
    window.atualizarRastreadorNoMapa = function(r) {
        if (!r) return;
        
        // Suporte a dados do banco (nested) e do WS (root)
        const pos = r.ultima_posicao || {};
        const lat = parseFloat(r.latitude || r.ultima_latitude || pos.latitude || 0);
        const lon = parseFloat(r.longitude || r.ultima_longitude || pos.longitude || 0);
        const panico = !!r.em_panico;
        const vel = r.velocidade || pos.velocidade || 0;
        const data = r.data_hora || pos.data_hora || '-';
        
        if (lat === 0 || isNaN(lat)) {
            console.warn('[UI] Rastreador sem coordenadas:', r.imei);
            return;
        }

        let marker = markers[r.imei];
        const icon = L.divIcon({
            className: 'custom-div-icon',
            html: `<div style="background-color: ${panico ? '#ef4444' : '#3b82f6'};" class="marker-pin ${panico ? 'panic-pulse' : ''}"></div><i class="fas fa-truck" style="color: white;"></i>`,
            iconSize: [40, 40],
            iconAnchor: [20, 40]
        });

        const content = `
            <div class="popup-title"><i class="fas fa-truck"></i> ${r.nome || 'Rastreador'}</div>
            <div class="popup-row">IMEI: <span>${r.imei}</span></div>
            <div class="popup-row">Botão SOS: <span class="badge-status ${panico ? 'badge-panic':'badge-off'}">${panico ? 'ATIVADO':'DESATIVADO'}</span></div>
            <div class="popup-row">Velocidade: <span>${vel} km/h</span></div>
            <div class="popup-row">Carga: <span>${data}</span></div>
        `;

        const labelContent = `
            <div class="marker-label">
                <b>${r.nome || 'Rastreador'}</b>
                <span style="font-size:10px; color:#cbd5e1">${r.imei}</span>
                <div style="margin-top:4px; text-align:left; border-top:1px solid rgba(255,255,255,0.1); padding-top:2px;">
                    <div style="font-size:10px;">SOS: <span style="color:${panico ? '#ef4444' : '#22c55e'}; font-weight:bold">${panico ? 'LIGADO' : 'Desligado'}</span></div>
                    <div style="font-size:9px; color:#94a3b8;">Contato: ${r.ultimo_contato || '-'}</div>
                    <div style="font-size:9px; color:#94a3b8;">Posição: ${data}</div>
                </div>
            </div>
        `;

        if (marker) {
            marker.setLatLng([lat, lon]);
            marker.setIcon(icon);
            marker.getPopup().setContent(content);
            marker.getTooltip().setContent(labelContent);
        } else {
            marker = L.marker([lat, lon], { icon: icon }).addTo(map);
            marker.bindPopup(content);
            marker.bindTooltip(labelContent, { 
                permanent: true, 
                direction: 'top', 
                offset: [0, -35],
                className: 'marker-label-container'
            }).openTooltip();
            markers[r.imei] = marker;
        }
    };

    // Função para Centralizar todas as unidades no mapa (Zoom Inteligente)
    window.centrarMapa = function() {
        const values = Object.values(markers);
        if (values.length > 0) {
            const group = new L.featureGroup(values);
            map.fitBounds(group.getBounds().pad(0.15));
            
            // Se houver apenas 1 veículo, dá um zoom mais próximo (nível rua)
            if (values.length === 1) {
                map.setZoom(16);
            } else if (map.getZoom() > 17) {
                map.setZoom(17);
            }
        }
    };

    // Sincronização via API (Polling)
    window.sincronizar = async function() {
        try {
            const response = await fetch('/api/v1/rastreadores?_t=' + Date.now());
            const data = await response.json();
            
            if (data && Array.isArray(data)) {
                data.forEach(r => { if (r) atualizarRastreadorNoMapa(r); });
            }

            // Auto-ajuste na primeira carga
            if (data && data.length > 0 && primeiraCarga) {
                centrarMapa();
                primeiraCarga = false;
            }

            // Atualização visual do status (Checagem Segura)
            const syncStatus = document.getElementById('sync-status');
            if (syncStatus) {
                const isConnected = window.Echo && 
                                   window.Echo.connector && 
                                   window.Echo.connector.pusher &&
                                   window.Echo.connector.pusher.connection &&
                                   window.Echo.connector.pusher.connection.state === 'connected';

                if (!isConnected) {
                    syncStatus.style.opacity = '0.5';
                    setTimeout(() => syncStatus.style.opacity = '1', 200);
                }
            }
        } catch (e) {
            console.error('[Polling] Falha ao sincronizar:', e);
        }
    };

    // Filtro de seleção
    document.getElementById('filtroRastreador').addEventListener('change', async function() {
        if (!this.value) { centrarMapa(); return; }
        const response = await fetch('/api/v1/rastreadores');
        const data = await response.json();
        const found = data.find(it => it.id == this.value);
        if (found) {
            const m = markers[found.imei];
            if (m) { map.setView(m.getLatLng(), 16); m.openPopup(); }
        }
    });

    // Inicialização
    sincronizar();
    setInterval(sincronizar, 20000);
})();
</script>
@endpush
@endsection
