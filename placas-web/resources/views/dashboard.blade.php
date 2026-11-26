{{-- © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4 --}}
@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Panel en Tiempo Real')

@push('head')
<style>
.stat-card {
    background-color: #1f2937;
    border-radius: 0.75rem;
    padding: 1.25rem;
    border: 1px solid #374151;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
}
.paso-row { animation: fadeIn 0.4s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: none; } }
</style>
@endpush

@section('content')
{{-- Estadísticas --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">Pasos hoy</p>
        <p class="text-3xl font-bold text-white" id="stat-pasos">{{ $totalPasosHoy }}</p>
    </div>
    <div class="stat-card">
        <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">Recaudación hoy</p>
        <p class="text-3xl font-bold text-green-400" id="stat-recaudacion">Bs {{ number_format($recaudacionHoy, 2) }}</p>
    </div>
    <div class="stat-card">
        <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">Alertas pendientes</p>
        <p class="text-3xl font-bold text-red-400" id="stat-alertas">{{ $alertasSinAtender }}</p>
    </div>
    <div class="stat-card">
        <p class="text-gray-400 text-xs uppercase tracking-wide mb-1">Vehículos activos</p>
        <p class="text-3xl font-bold text-indigo-400">{{ $totalVehiculos }}</p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    {{-- Feed en tiempo real --}}
    <div class="xl:col-span-2 bg-gray-800 rounded-xl border border-gray-700">
        <div class="p-4 border-b border-gray-700 flex items-center justify-between">
            <h3 class="font-semibold text-white flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                Vehículos recientes
            </h3>
            <span class="text-xs text-gray-400">Actualización en tiempo real</span>
        </div>
        <div class="px-4 pt-3 leyenda-inferencia text-xs text-gray-400">
            <span><i style="background:#22d3ee"></i>Vehículo</span>
            <span><i style="background:#facc15"></i>Placa</span>
            <span><i style="background:#e879f9"></i>Pasajeros</span>
            <span><i style="background:#fb923c;border-radius:50%"></i>Llantas</span>
        </div>
        <div class="overflow-auto max-h-[720px] p-4">
            <div id="pasos-tarjetas" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($ultimosPasos as $paso)
                    @include('pasos._tarjeta', ['paso' => $paso])
                @endforeach
            </div>
        </div>
    </div>

    {{-- Panel derecho --}}
    <div class="space-y-4">
        {{-- Alertas activas --}}
        <div class="bg-gray-800 rounded-xl border border-red-900/50">
            <div class="p-4 border-b border-gray-700">
                <h3 class="font-semibold text-white flex items-center gap-2">
                    <i class="fas fa-bell text-red-400"></i> Alertas activas
                </h3>
            </div>
            <div class="p-3 space-y-2 max-h-48 overflow-y-auto" id="alertas-panel">
                @forelse($ultimasAlertas as $alerta)
                <div class="bg-red-950/40 border border-red-800/40 rounded-lg p-3 text-sm">
                    <p class="font-mono font-bold text-red-300">{{ $alerta->paso?->placa_normalizada ?? '—' }}</p>
                    <p class="text-gray-400 text-xs mt-0.5">{{ ucfirst(str_replace('_',' ',$alerta->tipo)) }} · {{ $alerta->created_at->diffForHumans() }}</p>
                </div>
                @empty
                <p class="text-gray-500 text-sm text-center py-4">Sin alertas pendientes ✓</p>
                @endforelse
            </div>
        </div>

        {{-- Casetas --}}
        <div class="bg-gray-800 rounded-xl border border-gray-700">
            <div class="p-4 border-b border-gray-700">
                <h3 class="font-semibold text-white"><i class="fas fa-road mr-2 text-indigo-400"></i>Casetas hoy</h3>
            </div>
            <div class="p-3 space-y-2">
                @foreach($casetas as $c)
                <div class="flex items-center justify-between py-2 border-b border-gray-700 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-white">{{ $c['nombre'] }}</p>
                        <p class="text-xs text-gray-400">{{ $c['resumen']['total_pasos'] }} pasos</p>
                    </div>
                    <p class="text-green-400 font-semibold text-sm">Bs {{ number_format($c['resumen']['recaudacion'], 2) }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('pasos._tarjetas-js')
<script>
let totalPasos = {{ $totalPasosHoy }};
let recaudacion = {{ $recaudacionHoy }};
let totalAlertas = {{ $alertasSinAtender }};

document.addEventListener('nuevo-paso', function(e) {
    const p = e.detail;

    // Actualizar contadores
    totalPasos++;
    document.getElementById('stat-pasos').textContent = totalPasos;

    if (p.monto) {
        recaudacion += parseFloat(p.monto);
        document.getElementById('stat-recaudacion').textContent = 'Bs ' + recaudacion.toFixed(2);
    }

    if (p.tiene_alerta) {
        totalAlertas++;
        document.getElementById('stat-alertas').textContent = totalAlertas;
    }

    // Insertar la tarjeta del vehículo con la evidencia y las cajas de la inferencia
    TarjetaPaso.agregar(document.getElementById('pasos-tarjetas'), p, 20);

    // Alerta visual si hay problema
    if (p.tiene_alerta) {
        const alertaEl = `<div class="bg-red-950/40 border border-red-800/40 rounded-lg p-3 text-sm paso-row">
            <p class="font-mono font-bold text-red-300">${p.placa}</p>
            <p class="text-gray-400 text-xs mt-0.5">${p.estado.replace('_',' ')} · ahora</p>
        </div>`;
        document.getElementById('alertas-panel').insertAdjacentHTML('afterbegin', alertaEl);
    }
});
</script>
@endpush
