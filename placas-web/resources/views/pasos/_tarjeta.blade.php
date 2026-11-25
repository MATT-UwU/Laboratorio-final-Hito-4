{{-- Tarjeta de un paso con la evidencia visible y las cajas de la inferencia dibujadas encima. --}}
@php
    $overlay = $paso->overlay;
    $estadoColor = [
        'ok' => 'border-green-600/60', 'sin_saldo' => 'border-amber-500/60',
        'no_registrado' => 'border-red-600/60', 'alerta' => 'border-red-600/60',
    ][$paso->estado] ?? 'border-gray-700';
@endphp
<div class="tarjeta-paso bg-gray-800 rounded-xl border {{ $estadoColor }} overflow-hidden flex flex-col">
    <div class="relative bg-black aspect-video">
        @if($paso->imagen_url)
            <a href="{{ $paso->imagen_url }}" target="_blank" title="Abrir imagen original">
                <img src="{{ $paso->imagen_url }}" alt="Evidencia {{ $paso->placa_normalizada }}" loading="lazy"
                     class="w-full h-full object-contain" data-overlay='@json($overlay)'>
                <svg class="overlay-inferencia absolute inset-0 w-full h-full pointer-events-none"
                     preserveAspectRatio="xMidYMid meet"></svg>
            </a>
        @else
            <div class="w-full h-full flex items-center justify-center text-gray-600 text-sm">
                <i class="fas fa-image mr-2"></i>Sin evidencia
            </div>
        @endif
    </div>
    <div class="p-3 flex-1 flex flex-col gap-2">
        <div class="flex items-center justify-between gap-2">
            <span class="font-mono font-bold text-lg text-white tracking-wider">{{ $paso->placa_normalizada ?? $paso->placa_leida ?? '—' }}</span>
            <span class="badge-{{ $paso->estado }}">{{ str_replace('_', ' ', $paso->estado) }}</span>
        </div>
        <div class="grid grid-cols-4 gap-1 text-center text-xs">
            <div class="bg-gray-700/40 rounded p-1"><p class="text-gray-500">Categoría</p><p class="text-gray-200 capitalize">{{ $paso->categoria_detectada ?? '—' }}</p></div>
            <div class="bg-gray-700/40 rounded p-1"><p class="text-gray-500">Ejes</p><p class="text-gray-200">{{ $paso->ejes_detectados ?? '—' }}</p></div>
            <div class="bg-gray-700/40 rounded p-1"><p class="text-gray-500">Pasajeros</p><p class="text-gray-200">{{ $paso->pasajeros ?? '—' }}</p></div>
            <div class="bg-gray-700/40 rounded p-1"><p class="text-gray-500">Monto</p><p class="text-green-400 font-semibold">{{ $paso->monto ? 'Bs '.$paso->monto : '—' }}</p></div>
        </div>
        <div class="flex items-center justify-between text-xs text-gray-500 mt-auto">
            <span><i class="fas fa-road mr-1"></i>{{ $paso->caseta?->nombre ?? '—' }}</span>
            <span>#{{ $paso->id }} · {{ $paso->created_at->format('d/m/Y H:i:s') }}</span>
        </div>
    </div>
</div>
