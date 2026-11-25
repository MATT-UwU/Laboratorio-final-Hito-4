<!DOCTYPE html>
<html lang="es" class="h-full bg-gray-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistema de Peaje') — Peaje Inteligente</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Estilos CSS nativos para máxima compatibilidad con Tailwind CDN */
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 0.875rem;
            border-radius: 0.75rem;
            color: #9ca3af;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
        }
        .sidebar-link:hover {
            background-color: rgba(55, 65, 81, 0.7);
            color: #ffffff;
            transform: translateX(2px);
        }
        .sidebar-link.active {
            background-color: #4f46e5;
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 4px 14px 0 rgba(79, 70, 229, 0.35);
        }
        .sidebar-link.active i {
            color: #ffffff !important;
        }
        .badge-ok {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 600;
            background-color: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .badge-alerta {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 600;
            background-color: rgba(234, 179, 8, 0.15);
            color: #facc15;
            border: 1px solid rgba(234, 179, 8, 0.3);
        }
        .badge-sin_saldo {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 600;
            background-color: rgba(249, 115, 22, 0.15);
            color: #fb923c;
            border: 1px solid rgba(249, 115, 22, 0.3);
        }
        .badge-no_registrado, .badge-evasion {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.65rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 600;
            background-color: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .stat-card {
            background-color: #1f2937;
            border-radius: 0.875rem;
            padding: 1.25rem;
            border: 1px solid rgba(55, 65, 81, 0.8);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }
    </style>
    @stack('head')
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen flex antialiased selection:bg-indigo-500 selection:text-white">

    {{-- Sidebar Moderno y Estructurado --}}
    <aside class="w-64 bg-gray-900 border-r border-gray-800 flex flex-col flex-shrink-0 min-h-screen shadow-2xl z-20">
        
        {{-- Brand Header --}}
        <div class="p-5 border-b border-gray-800/80 bg-gray-900/50 backdrop-blur-sm">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 bg-gradient-to-tr from-indigo-600 to-violet-500 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-600/30 flex-shrink-0">
                    <i class="fas fa-car-tunnel text-white text-xl"></i>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <h1 class="font-bold text-white text-sm tracking-tight leading-tight">Peaje Inteligente</h1>
                        <span class="px-1.5 py-0.5 text-[10px] font-bold bg-indigo-500/20 text-indigo-300 rounded border border-indigo-500/30">HITO</span>
                    </div>
                    <p class="text-xs text-gray-400 truncate mt-0.5">Control & Telepeaje</p>
                </div>
            </div>
        </div>

        {{-- Navegación Estructurada por Secciones --}}
        <nav class="flex-1 px-3.5 py-4 space-y-6 overflow-y-auto">
            
            {{-- Sección: Operación & Tráfico --}}
            <div>
                <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2">
                    Operación y Tráfico
                </p>
                <div class="space-y-1">
                    <a href="{{ route('dashboard') }}" 
                       class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="fas fa-gauge-high w-5 text-center text-indigo-400"></i>
                        <span>Dashboard en Vivo</span>
                    </a>

                    <a href="{{ route('pasos.index') }}" 
                       class="sidebar-link {{ request()->routeIs('pasos.*') ? 'active' : '' }}">
                        <i class="fas fa-road w-5 text-center text-emerald-400"></i>
                        <span>Registro de Pasos</span>
                    </a>

                    <a href="{{ route('placas-filtradas.index') }}"
                       class="sidebar-link {{ request()->routeIs('placas-filtradas.*') ? 'active' : '' }}">
                        <i class="fas fa-filter w-5 text-center text-lime-400"></i>
                        <span>Placas filtradas (2…2 · M)</span>
                    </a>

                    <a href="{{ route('alertas.index') }}" 
                       class="sidebar-link {{ request()->routeIs('alertas.*') ? 'active' : '' }} justify-between">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-triangle-exclamation w-5 text-center text-amber-400"></i>
                            <span>Incidencias & Alertas</span>
                        </div>
                        @php $pendientes = \App\Models\Alerta::where('atendida',false)->count(); @endphp
                        @if($pendientes > 0)
                            <span class="bg-red-500/20 text-red-400 border border-red-500/40 text-[11px] font-bold rounded-full px-2 py-0.5 animate-pulse">
                                {{ $pendientes }}
                            </span>
                        @endif
                    </a>
                </div>
            </div>

            {{-- Sección: Administración (Solo Admins) --}}
            @if(auth()->user()->esAdmin())
            <div>
                <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2">
                    Gestión Vehicular
                </p>
                <div class="space-y-1">
                    <a href="{{ route('vehiculos.index') }}" 
                       class="sidebar-link {{ request()->routeIs('vehiculos.*') ? 'active' : '' }}">
                        <i class="fas fa-car-rear w-5 text-center text-cyan-400"></i>
                        <span>Parque Automotor</span>
                    </a>
                </div>
            </div>
            @endif

            {{-- Sección: Inteligencia Artificial & Sistema --}}
            <div>
                <p class="px-3 text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2">
                    Inteligencia Artificial
                </p>
                <div class="space-y-1">
                    <a href="{{ route('chat') }}" 
                       class="sidebar-link {{ request()->routeIs('chat') ? 'active' : '' }}">
                        <i class="fas fa-robot w-5 text-center text-violet-400"></i>
                        <span>Agente Consultor IA</span>
                    </a>

                    <a href="{{ route('estado') }}" 
                       class="sidebar-link {{ request()->routeIs('estado') ? 'active' : '' }}">
                        <i class="fas fa-server w-5 text-center text-pink-400"></i>
                        <span>Inferencia (M1/M2/M3)</span>
                    </a>
                </div>
            </div>

        </nav>

        {{-- Footer de Usuario con Avatar y Logout --}}
        <div class="p-3.5 border-t border-gray-800 bg-gray-900/80">
            <div class="bg-gray-800/70 border border-gray-700/60 rounded-xl p-3 shadow-inner">
                <div class="flex items-center gap-3">
                    <div class="relative flex-shrink-0">
                        @if(auth()->user()->avatar)
                            <img src="{{ auth()->user()->avatar }}" 
                                 class="w-9 h-9 rounded-full object-cover ring-2 ring-indigo-500/40"
                                 alt="Avatar">
                        @else
                            <div class="w-9 h-9 bg-indigo-600 rounded-full flex items-center justify-center text-xs font-bold text-white ring-2 ring-indigo-500/40">
                                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                            </div>
                        @endif
                        <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-gray-900"></span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-white truncate" title="{{ auth()->user()->name }}">
                            {{ auth()->user()->name }}
                        </p>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="inline-block px-1.5 py-0.2 rounded text-[10px] font-bold uppercase tracking-wider {{ auth()->user()->esAdmin() ? 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' }}">
                                {{ auth()->user()->rol }}
                            </span>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="mt-2.5 pt-2.5 border-t border-gray-700/50">
                    @csrf
                    <button type="submit" 
                            class="w-full flex items-center justify-center gap-2 px-3 py-1.5 text-xs font-medium text-rose-300 hover:text-white bg-rose-500/10 hover:bg-rose-600/30 rounded-lg border border-rose-500/20 hover:border-rose-500/40 transition-colors">
                        <i class="fas fa-sign-out-alt text-xs"></i>
                        <span>Cerrar sesión</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Área Principal de Contenido --}}
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-gray-950">
        
        {{-- Barra Superior (Header) --}}
        <header class="bg-gray-900/80 backdrop-blur-md border-b border-gray-800 px-6 py-3.5 flex items-center justify-between sticky top-0 z-10 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-2 h-2 rounded-full bg-indigo-500"></div>
                <h2 class="text-base font-semibold text-white tracking-wide">
                    @yield('page-title', 'Dashboard')
                </h2>
            </div>

            <div class="flex items-center gap-4">
                {{-- Indicador SSE en tiempo real --}}
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-800/80 border border-gray-700/70 text-xs shadow-inner">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" id="sse-ping"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" id="sse-dot"></span>
                    </span>
                    <span id="sse-label" class="text-gray-300 font-medium">Conectando...</span>
                </div>
            </div>
        </header>

        {{-- Contenedor de Vistas --}}
        <div class="flex-1 overflow-y-auto p-6 lg:p-8 space-y-6">
            @if(session('success'))
                <div class="p-4 bg-emerald-950/60 border border-emerald-600/40 rounded-xl text-emerald-300 text-sm flex items-center gap-3 shadow-lg">
                    <i class="fas fa-circle-check text-emerald-400 text-base"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 bg-rose-950/60 border border-rose-600/40 rounded-xl text-rose-300 text-sm flex items-center gap-3 shadow-lg">
                    <i class="fas fa-circle-exclamation text-rose-400 text-base"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @yield('content')
        </div>
    </main>

    {{-- Script SSE Global Optimizado --}}
    <script>
    (function() {
        const dot   = document.getElementById('sse-dot');
        const ping  = document.getElementById('sse-ping');
        const label = document.getElementById('sse-label');
        let es = null;

        function conectar() {
            if (es) {
                try { es.close(); } catch(e) {}
            }

            es = new EventSource('/pasos/stream');

            es.onopen = () => {
                if (dot) dot.className = 'relative inline-flex rounded-full h-2 w-2 bg-emerald-500';
                if (ping) ping.className = 'animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75';
                if (label) {
                    label.textContent = 'En Vivo';
                    label.className = 'text-emerald-400 font-semibold';
                }
            };

            es.onmessage = (e) => {
                try {
                    const paso = JSON.parse(e.data);
                    document.dispatchEvent(new CustomEvent('nuevo-paso', { detail: paso }));
                } catch (err) {}
            };

            es.onerror = () => {
                // Si el stream completó su ciclo y el navegador está reconectando automáticamente (readyState === 0),
                // mantenemos el indicador en verde sin alarmar al usuario.
                if (es.readyState === 0) {
                    return;
                }
                if (dot) dot.className = 'relative inline-flex rounded-full h-2 w-2 bg-amber-500';
                if (ping) ping.className = 'hidden';
                if (label) {
                    label.textContent = 'Sincronizando...';
                    label.className = 'text-amber-400 font-medium';
                }
            };
        }

        conectar();
    })();
    </script>

    @stack('scripts')
</body>
</html>
