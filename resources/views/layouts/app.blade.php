<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Rastreador') — TRX-16</title>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

    <style>
        :root {
            --primary:    #0ea5e9;
            --primary-d:  #0284c7;
            --accent:     #f59e0b;
            --danger:     #ef4444;
            --success:    #22c55e;
            --bg:         #0f172a;
            --surface:    #1e293b;
            --surface2:   #283548;
            --border:     #334155;
            --text:       #e2e8f0;
            --muted:      #94a3b8;
            --radius:     12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* ── Sidebar ── */
        .layout { display: flex; min-height: 100vh; }

        .sidebar {
            width: 240px;
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 1.5rem 1rem;
            gap: .5rem;
            position: fixed;
            top: 0; left: 0; bottom: 0;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .75rem .5rem 1.5rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: .5rem;
        }

        .sidebar-logo .icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-d));
            border-radius: 10px;
            display: grid; place-items: center;
            font-size: 1.1rem; color: #fff;
        }

        .sidebar-logo span { font-size: .95rem; font-weight: 700; line-height: 1.2; }
        .sidebar-logo small { color: var(--muted); font-size: .7rem; font-weight: 400; display: block; }

        .nav-item {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .65rem .9rem;
            border-radius: 8px;
            color: var(--muted);
            text-decoration: none;
            font-size: .85rem;
            font-weight: 500;
            transition: all .2s;
        }

        .nav-item:hover, .nav-item.active {
            background: var(--surface2);
            color: var(--text);
        }

        .nav-item.active { color: var(--primary); }
        .nav-item i { width: 16px; text-align: center; }

        /* ── Main ── */
        .main {
            margin-left: 240px;
            flex: 1;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 1.75rem;
        }

        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--text), var(--muted));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .page-header p { color: var(--muted); font-size: .875rem; margin-top: .25rem; }

        /* ── Cards ── */
        .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.75rem; }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
        }

        .card-stat {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }

        .card-stat .icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            display: grid; place-items: center;
            font-size: 1.1rem;
        }

        .card-stat .icon.blue   { background: rgba(14,165,233,.15); color: var(--primary); }
        .card-stat .icon.amber  { background: rgba(245,158,11,.15); color: var(--accent); }
        .card-stat .icon.green  { background: rgba(34,197,94,.15);  color: var(--success); }
        .card-stat .icon.red    { background: rgba(239,68,68,.15);  color: var(--danger); }

        .card-stat .value { font-size: 1.75rem; font-weight: 700; line-height: 1; }
        .card-stat .label { color: var(--muted); font-size: .78rem; margin-top: .3rem; }

        /* ── Table ── */
        .table-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .table-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .75rem;
        }

        .table-header h2 { font-size: 1rem; font-weight: 600; }

        table { width: 100%; border-collapse: collapse; }
        th { padding: .75rem 1.25rem; text-align: left; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); border-bottom: 1px solid var(--border); white-space: nowrap; }
        td { padding: .85rem 1.25rem; font-size: .85rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tbody tr { transition: background .15s; }
        tbody tr:hover { background: var(--surface2); }

        /* ── Badges ── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .25rem .6rem;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 600;
        }

        .badge.green  { background: rgba(34,197,94,.15);  color: var(--success); }
        .badge.red    { background: rgba(239,68,68,.15);  color: var(--danger); }
        .badge.amber  { background: rgba(245,158,11,.15); color: var(--accent); }
        .badge.blue   { background: rgba(14,165,233,.15); color: var(--primary); }

        /* ── Buttons ── */
        .btn {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .55rem 1.1rem;
            border-radius: 8px;
            font-size: .83rem; font-weight: 600;
            text-decoration: none;
            border: none; cursor: pointer;
            transition: all .2s;
        }

        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-d); }
        .btn-ghost { background: var(--surface2); color: var(--text); border: 1px solid var(--border); }
        .btn-ghost:hover { background: var(--border); }
        .btn-sm { padding: .35rem .75rem; font-size: .78rem; }

        /* ── Form filters ── */
        .filters {
            display: flex;
            gap: .75rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .form-group { display: flex; flex-direction: column; gap: .3rem; }
        .form-group label { font-size: .75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }

        input[type="date"], select {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text);
            padding: .55rem .85rem;
            border-radius: 8px;
            font-size: .85rem;
            font-family: inherit;
            outline: none;
            transition: border .2s;
        }

        input[type="date"]:focus, select:focus { border-color: var(--primary); }

        /* ── Pagination ── */
        .pagination { display: flex; align-items: center; gap: .4rem; padding: 1rem 1.25rem; }
        .pagination a, .pagination span {
            padding: .4rem .75rem;
            border-radius: 7px;
            font-size: .82rem;
            text-decoration: none;
            color: var(--muted);
            border: 1px solid var(--border);
            transition: all .15s;
        }

        .pagination a:hover { background: var(--surface2); color: var(--text); }
        .pagination span.active { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* ── Map ── */
        #map { height: calc(100vh - 180px); border-radius: var(--radius); overflow: hidden; }
        .leaflet-popup-content-wrapper { background: var(--surface); color: var(--text); border: 1px solid var(--border); border-radius: 10px; }
        .leaflet-popup-tip { background: var(--surface); }

        /* ── Status indicator ── */
        .status-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
        }

        .status-dot.online { background: var(--success); }
        .status-dot.offline { background: var(--danger); animation: none; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: .4; }
        }
    </style>

    @stack('styles')
</head>
<body>
<div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="icon"><i class="fas fa-satellite-dish"></i></div>
            <div>
                <span>Rastreador</span>
                <small>TRX-16 / Arqia</small>
            </div>
        </div>

        <a href="{{ route('dashboard') }}"       class="nav-item {{ request()->routeIs('dashboard')              ? 'active' : '' }}">
            <i class="fas fa-gauge-high"></i> Dashboard
        </a>
        <a href="{{ route('mapa') }}"            class="nav-item {{ request()->routeIs('mapa')                   ? 'active' : '' }}">
            <i class="fas fa-map"></i> Mapa ao Vivo
        </a>
        <a href="{{ route('rastreadores.index') }}" class="nav-item {{ request()->routeIs('rastreadores.*')      ? 'active' : '' }}">
            <i class="fas fa-truck"></i> Rastreadores
        </a>
    </aside>

    <!-- Main Content -->
    <main class="main">
        @yield('content')
    </main>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

@stack('scripts')
</body>
</html>
