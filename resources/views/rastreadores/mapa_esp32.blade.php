@extends('layouts.app')

@section('content')
<div class="row page-header">
    <div class="col-12">
        <div class="d-flex align-items-center">
            <div class="header-icon" style="background: var(--primary-light); color: var(--primary)">
                <i class="fas fa-microchip"></i>
            </div>
            <div>
                <h1 class="page-title">Monitoramento ESP32</h1>
                <p class="page-subtitle">Telemetria em tempo real das placas ativas</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card map-card">
            <div class="card-header p-2">
                <div class="d-flex align-items-center gap-2">
                    <select id="filtroDispositivo" class="form-select form-select-sm" style="max-width: 300px;">
                        <option value="">Todas as placas</option>
                        @foreach($dispositivos as $d)
                            <option value="{{ $d->identificador }}">{{ $d->nome }} ({{ $d->identificador }})</option>
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
                <div id="map" style="height: 650px; width: 100%; border-radius: 0 0 8px 8px;"></div>
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
        background: #8b5cf6; position: absolute;
        transform: rotate(-45deg); left: 50%; top: 50%;
        margin: -32px 0 0 -16px; border: 2px solid #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }
    .custom-div-icon i {
        position: absolute; width: 32px; font-size: 14px; color: #fff;
        text-align: center; top: 18px; left: 16px; margin-left: -16px; z-index: 10;
    }
    .marker-label {
        background: rgba(15, 23, 42, 0.9);
        color: #fff; border: 1px solid #8b5cf6;
        padding: 4px 10px; border-radius: 4px;
        font-size: 12px; white-space: nowrap;
        text-align: center;
    }
    .marker-label b { display: block; color: #a78bfa; }
    .telemetry-badge { font-size: 10px; padding: 2px 5px; border-radius: 3px; background: #334155; margin-right: 3px; }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.3.0/dist/web/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js"></script>
<script>
(function() {
    let markers = {};
    const map = L.map('map', { center: [-15.7801, -47.9292], zoom: 5, zoomControl: false });
    L.control.zoom({ position: 'bottomright' }).addTo(map);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    // Echo Config
    try {
        const reverbKey = "{{ config('reverb.apps.0.key') }}";
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: window.location.hostname,
            wsPort: window.location.protocol === 'https:' ? 443 : 8080,
            wssPort: window.location.protocol === 'https:' ? 443 : 8080,
            forceTLS: (window.location.protocol === 'https:'),
            enabledTransports: ['ws', 'wss'],
        });

        window.Echo.channel('esp32-fleet')
            .listen('.TelemetryReceived', (e) => {
                console.log('[WS] Telemetria ESP32:', e.telemetria.dispositivo.identificador);
                atualizarNoMapa(e.telemetria);
            });
    } catch (e) { console.warn('Echo fail', e); }

    function atualizarNoMapa(t) {
        const d = t.dispositivo;
        const lat = parseFloat(t.latitude);
        const lon = parseFloat(t.longitude);
        if (!lat || !lon) return;

        let marker = markers[d.identificador];
        const icon = L.divIcon({
            className: 'custom-div-icon',
            html: `<div class="marker-pin"></div><i class="fas fa-microchip"></i>`,
            iconSize: [40, 40], iconAnchor: [20, 40]
        });

        const popup = `
            <div style="color:#fff">
                <b style="color:#a78bfa">${d.nome}</b><br>
                <small>${d.identificador}</small><hr style="margin:5px 0">
                Lat: ${lat}<br>Lon: ${lon}<br>
                Bateria: ${t.bateria_vcc || '-'} V<br>
                Temp: ${t.temperatura || '-'} °C<br>
                <small>🕒 ${new Date(t.data_hora).toLocaleString()}</small>
            </div>
        `;

        if (marker) {
            marker.setLatLng([lat, lon]);
            marker.getPopup().setContent(popup);
        } else {
            marker = L.marker([lat, lon], { icon: icon }).addTo(map);
            marker.bindPopup(popup);
            marker.bindTooltip(`<b>${d.nome}</b>`, { permanent: true, direction: 'top', offset: [0, -35] });
            markers[d.identificador] = marker;
        }
    }

    window.sincronizar = async function() {
        const res = await fetch('/api/v1/esp32/fleet');
        const dispositivos = await res.json();
        dispositivos.forEach(d => {
            if (d.ultima_telemetria) atualizarNoMapa(d.ultima_telemetria);
        });
    };

    window.centrarMapa = function() {
        const m = Object.values(markers);
        if (m.length) map.fitBounds(L.featureGroup(m).getBounds().pad(0.1));
    };

    sincronizar();
    setInterval(sincronizar, 30000);
})();
</script>
@endpush
@endsection
