<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Rastreador') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        <!-- Ícones -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

        <!-- Scripts / estilos compilados (Tailwind + Alpine) -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            /* Paleta inspirada no owl-admin / amis (cxd) */
            :root { --owl-primary: #2468f2; }
            [x-cloak] { display: none !important; }
            body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
            .owl-navlink { transition: background .15s, color .15s; }
            .owl-navlink:hover { background: rgba(255,255,255,.07); color:#fff; }
            .owl-navlink.active { background: var(--owl-primary); color:#fff; }
            .owl-navlink.active i { color:#fff; }
        </style>
    </head>
    <body class="antialiased bg-slate-100 dark:bg-slate-900">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen">

            <!-- Overlay mobile -->
            <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
                 class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>

            <!-- Sidebar -->
            <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                   class="fixed inset-y-0 left-0 z-40 w-64 bg-slate-900 text-slate-300 flex flex-col
                          transition-transform lg:translate-x-0">
                <!-- Marca -->
                <div class="h-16 flex items-center gap-3 px-5 border-b border-white/10">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg"
                          style="background: var(--owl-primary)">
                        <i class="fas fa-satellite-dish text-white"></i>
                    </span>
                    <div class="leading-tight">
                        <div class="text-white font-semibold text-sm">Rastreador</div>
                        <div class="text-slate-400 text-xs">Painel administrativo</div>
                    </div>
                </div>

                <!-- Navegação -->
                <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1 text-sm">
                    <a href="{{ route('dashboard') }}"
                       class="owl-navlink flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="fas fa-gauge-high w-5 text-center text-slate-400"></i> Dashboard
                    </a>
                    <a href="{{ route('mapa') }}"
                       class="owl-navlink flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('mapa') ? 'active' : '' }}">
                        <i class="fas fa-map w-5 text-center text-slate-400"></i> Mapa ao Vivo
                    </a>
                    <a href="{{ route('mapa.esp32') }}"
                       class="owl-navlink flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('mapa.esp32') ? 'active' : '' }}">
                        <i class="fas fa-microchip w-5 text-center text-slate-400"></i> Mapa ESP32
                    </a>
                    <a href="{{ route('rastreadores.index') }}"
                       class="owl-navlink flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('rastreadores.*') ? 'active' : '' }}">
                        <i class="fas fa-truck w-5 text-center text-slate-400"></i> Rastreadores
                    </a>

                    <div class="pt-3 pb-1 px-3 text-xs uppercase tracking-wide text-slate-500">Administração</div>

                    @hasanyrole('super-admin|admin-empresa')
                    <a href="{{ route('usuarios.index') }}"
                       class="owl-navlink flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('usuarios.*') ? 'active' : '' }}">
                        <i class="fas fa-users w-5 text-center text-slate-400"></i> Usuários
                    </a>
                    @endhasanyrole

                    @role('super-admin')
                    <a href="{{ route('empresas.index') }}"
                       class="owl-navlink flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('empresas.*') ? 'active' : '' }}">
                        <i class="fas fa-building w-5 text-center text-slate-400"></i> Empresas
                    </a>
                    @endrole

                    <a href="{{ route('profile.edit') }}"
                       class="owl-navlink flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                        <i class="fas fa-user-gear w-5 text-center text-slate-400"></i> Perfil
                    </a>
                </nav>

                <!-- Rodapé: usuário + sair -->
                <div class="border-t border-white/10 p-3">
                    <div class="flex items-center gap-3 px-2 py-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-700 text-white text-xs">
                            {{ strtoupper(mb_substr(auth()->user()->name ?? '?', 0, 1)) }}
                        </span>
                        <div class="min-w-0">
                            <div class="text-white text-sm truncate">{{ auth()->user()->name ?? '' }}</div>
                            <div class="text-slate-400 text-xs truncate">{{ auth()->user()->email ?? '' }}</div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="owl-navlink w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm">
                            <i class="fas fa-right-from-bracket w-5 text-center text-slate-400"></i> Sair
                        </button>
                    </form>
                </div>
            </aside>

            <!-- Área principal -->
            <div class="lg:pl-64">
                <!-- Topbar -->
                <header class="sticky top-0 z-20 h-16 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700
                               flex items-center gap-4 px-4 sm:px-6">
                    <button @click="sidebarOpen = !sidebarOpen"
                            class="lg:hidden text-slate-600 dark:text-slate-300">
                        <i class="fas fa-bars text-lg"></i>
                    </button>

                    <div class="flex-1 min-w-0">
                        @isset($header)
                            <div class="text-slate-800 dark:text-slate-100 font-semibold truncate">{{ $header }}</div>
                        @endisset
                    </div>

                    <a href="{{ route('dashboard') }}" class="text-slate-500 hover:text-slate-800 dark:hover:text-white text-sm">
                        <i class="fas fa-house"></i>
                    </a>
                </header>

                <!-- Conteúdo -->
                <main class="p-4 sm:p-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
