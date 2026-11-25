@extends('layouts.app')
@section('title','Pasos')
@section('page-title','Historial de Pasos')

@section('content')
<div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
    <div class="p-4 border-b border-gray-700">
        <h3 class="font-semibold text-white">Todos los pasos registrados</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-700/50 text-gray-400 text-xs uppercase">
                <tr>
                    <th class="text-left p-3">#</th>
                    <th class="text-left p-3">Placa</th>
                    <th class="text-left p-3">Categoría</th>
                    <th class="text-left p-3">Ejes</th>
                    <th class="text-left p-3">Monto</th>
                    <th class="text-left p-3">Estado</th>
                    <th class="text-left p-3">Caseta</th>
                    <th class="text-left p-3">Evidencia</th>
                    <th class="text-left p-3">Fecha/Hora</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pasos as $paso)
                <tr class="border-b border-gray-700 hover:bg-gray-700/20 transition-colors">
                    <td class="p-3 text-gray-500 text-xs">{{ $paso->id }}</td>
                    <td class="p-3 font-mono font-bold text-white">{{ $paso->placa_normalizada ?? $paso->placa_leida ?? '—' }}</td>
                    <td class="p-3 capitalize text-gray-300">{{ $paso->categoria_detectada ?? '—' }}</td>
                    <td class="p-3 text-gray-300">{{ $paso->ejes_detectados ?? '—' }}</td>
                    <td class="p-3 text-green-400 font-semibold">{{ $paso->monto ? 'Bs '.$paso->monto : '—' }}</td>
                    <td class="p-3"><span class="badge-{{ $paso->estado }}">{{ $paso->estado }}</span></td>
                    <td class="p-3 text-gray-400">{{ $paso->caseta?->nombre ?? '—' }}</td>
                    <td class="p-3">
                        @if($paso->imagen_url)
                            <a href="{{ $paso->imagen_url }}" target="_blank"
                               class="text-indigo-400 hover:text-indigo-300 text-xs">
                                <i class="fas fa-image mr-1"></i>Ver
                            </a>
                        @else
                            <span class="text-gray-600 text-xs">—</span>
                        @endif
                    </td>
                    <td class="p-3 text-gray-500 text-xs">{{ $paso->created_at->format('d/m/Y H:i:s') }}</td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center p-8 text-gray-500">No hay pasos registrados aún.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-gray-700">
        {{ $pasos->links() }}
    </div>
</div>
@endsection
