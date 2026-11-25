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
        <div class="overflow-auto max-h-[500px]">
            <table class="w-full text-sm">
                <thead class="bg-gray-700/50 sticky top-0">
                    <tr class="text-gray-400 text-xs uppercase">
                        <th class="text-left p-3">Placa</th>
                        <th class="text-left p-3">Categoría</th>
                        <th class="text-left p-3">Ejes</th>
                        <th class="text-left p-3">Monto</th>
                        <th class="text-left p-3">Estado</th>
                        <th class="text-left p-3">Caseta</th>
                        <th class="text-left p-3">Hora</th>
                    </tr>
                </thead>
                <tbody id="pasos-tabla">
                    @foreach($ultimosPasos as $paso)
                    <tr class="border-b border-gray-700 hover:bg-gray-700/30 transition-colors">
                        <td class="p-3 font-mono font-bold text-white">{{ $paso->placa_normalizada ?? $paso->placa_leida ?? '—' }}</td>
                        <td class="p-3 capitalize text-gray-300">{{ $paso->categoria_detectada ?? '—' }}</td>
                        <td class="p-3 text-gray-300">{{ $paso->ejes_detectados ?? '—' }}</td>
                        <td class="p-3 text-green-400 font-semibold">{{ $paso->monto ? 'Bs '.$paso->monto : '—' }}</td>
                        <td class="p-3"><span class="badge-{{ $paso->estado }}">{{ $paso->estado }}</span></td>
                        <td class="p-3 text-gray-400">{{ $paso->caseta?->nombre ?? '—' }}</td>
                        <td class="p-3 text-gray-500 text-xs">{{ $paso->created_at->format('H:i:s') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
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

    // Insertar fila en la tabla
    const badgeClasses = {
        ok: 'badge-ok', alerta: 'badge-alerta',
        sin_saldo: 'badge-sin_saldo', no_registrado: 'badge-no_registrado'
    };
    const badge = badgeClasses[p.estado] || 'badge-ok';

    const fila = `<tr class="paso-row border-b border-gray-700 hover:bg-gray-700/30 transition-colors">
        <td class="p-3 font-mono font-bold text-white">${p.placa || '—'}</td>
        <td class="p-3 capitalize text-gray-300">${p.categoria || '—'}</td>
        <td class="p-3 text-gray-300">${p.ejes || '—'}</td>
        <td class="p-3 text-green-400 font-semibold">${p.monto ? 'Bs ' + p.monto : '—'}</td>
        <td class="p-3"><span class="${badge}">${p.estado}</span></td>
        <td class="p-3 text-gray-400">${p.caseta || '—'}</td>
        <td class="p-3 text-gray-500 text-xs">${p.hora}</td>
    </tr>`;

    const tabla = document.getElementById('pasos-tabla');
    tabla.insertAdjacentHTML('afterbegin', fila);

    // Mantener máximo 50 filas
    while (tabla.children.length > 50) tabla.removeChild(tabla.lastChild);

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
