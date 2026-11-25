@extends('layouts.app')
@section('title','Alertas')
@section('page-title','Alertas del Sistema')

@section('content')
<div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-700/50 text-gray-400 text-xs uppercase">
                <tr>
                    <th class="text-left p-3">Placa</th>
                    <th class="text-left p-3">Tipo</th>
                    <th class="text-left p-3">Caseta</th>
                    <th class="text-left p-3">Estado</th>
                    <th class="text-left p-3">Fecha</th>
                    <th class="text-left p-3">Acción</th>
                </tr>
            </thead>
            <tbody>
                @forelse($alertas as $alerta)
                <tr class="border-b border-gray-700 hover:bg-gray-700/20 {{ !$alerta->atendida ? 'bg-red-950/10' : '' }}">
                    <td class="p-3 font-mono font-bold text-white">
                        {{ $alerta->paso?->placa_normalizada ?? $alerta->paso?->placa_leida ?? '—' }}
                    </td>
                    <td class="p-3 capitalize text-gray-300">{{ str_replace('_',' ',$alerta->tipo) }}</td>
                    <td class="p-3 text-gray-400">{{ $alerta->paso?->caseta?->nombre ?? '—' }}</td>
                    <td class="p-3">
                        <span class="{{ $alerta->atendida ? 'badge-ok' : 'badge-alerta' }}">
                            {{ $alerta->atendida ? 'Atendida' : 'Pendiente' }}
                        </span>
                    </td>
                    <td class="p-3 text-gray-500 text-xs">{{ $alerta->created_at->format('d/m/Y H:i') }}</td>
                    <td class="p-3">
                        @unless($alerta->atendida)
                        <form method="POST" action="{{ route('alertas.atender', $alerta) }}">
                            @csrf
                            <button class="text-xs bg-green-700 hover:bg-green-600 text-white px-3 py-1 rounded-lg transition-colors">
                                <i class="fas fa-check mr-1"></i>Atender
                            </button>
                        </form>
                        @endunless
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center p-8 text-gray-500">No hay alertas registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-gray-700">{{ $alertas->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
// Alertas en tiempo real: agregar fila al principio
document.addEventListener('nuevo-paso', function(e) {
    const p = e.detail;
    if (!p.tiene_alerta) return;

    const tbody = document.querySelector('tbody');
    const sinAlertas = tbody.querySelector('td[colspan]');
    if (sinAlertas) sinAlertas.closest('tr').remove();

    tbody.insertAdjacentHTML('afterbegin', `
    <tr class="border-b border-gray-700 bg-red-950/10 paso-row">
        <td class="p-3 font-mono font-bold text-white">${p.placa || '—'}</td>
        <td class="p-3 capitalize text-gray-300">${p.estado.replace('_',' ')}</td>
        <td class="p-3 text-gray-400">${p.caseta || '—'}</td>
        <td class="p-3"><span class="badge-alerta">Pendiente</span></td>
        <td class="p-3 text-gray-500 text-xs">${p.hora}</td>
        <td class="p-3"><span class="text-xs text-gray-500">Recarga para atender</span></td>
    </tr>`);
});
</script>
@endpush
