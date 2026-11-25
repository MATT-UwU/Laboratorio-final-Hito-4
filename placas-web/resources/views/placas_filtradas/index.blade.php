@extends('layouts.app')
@section('title','Placas filtradas')
@section('page-title','Placas filtradas (2…2 · M)')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-4 md:col-span-1">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-1">Regla del filtro</p>
        <p class="text-sm text-gray-200">{{ $regla }}</p>
        <p class="text-xs text-gray-500 mt-2">Ejemplos: <span class="font-mono text-emerald-400">2M12</span>, <span class="font-mono text-emerald-400">2IM62</span>, <span class="font-mono text-emerald-400">2345MAB2</span></p>
    </div>
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-1">Detecciones guardadas</p>
        <p id="contador-total" class="text-3xl font-bold text-white">{{ $total }}</p>
    </div>
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-4">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-1">Placas distintas</p>
        <p class="text-3xl font-bold text-white">{{ $unicas }}</p>
    </div>
</div>

<div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-700/50 text-gray-400 text-xs uppercase">
                <tr>
                    <th class="text-left p-3">Evidencia</th>
                    <th class="text-left p-3">Placa</th>
                    <th class="text-left p-3">Números</th>
                    <th class="text-left p-3">Letras</th>
                    <th class="text-left p-3">Confianza OCR</th>
                    <th class="text-left p-3">Origen</th>
                    <th class="text-left p-3">Caseta</th>
                    <th class="text-left p-3">Fecha</th>
                </tr>
            </thead>
            <tbody id="tabla-filtradas">
                @forelse($placas as $p)
                <tr class="border-b border-gray-700 hover:bg-gray-700/20">
                    <td class="p-3">
                        @if($p->imagen_url)
                            <a href="{{ $p->imagen_url }}" target="_blank"><img src="{{ $p->imagen_url }}" class="w-16 h-10 object-cover rounded" alt="Evidencia"></a>
                        @else
                            <span class="text-gray-600">—</span>
                        @endif
                    </td>
                    <td class="p-3 font-mono font-bold text-emerald-400">{{ $p->placa }}</td>
                    <td class="p-3 font-mono text-gray-300">{{ $p->numeros }}</td>
                    <td class="p-3 font-mono text-gray-300">{{ $p->letras }}</td>
                    <td class="p-3 text-gray-400">{{ $p->confianza_ocr !== null ? number_format((float) $p->confianza_ocr * 100, 1) . ' %' : '—' }}</td>
                    <td class="p-3 capitalize text-gray-400">{{ $p->origen }}</td>
                    <td class="p-3 text-gray-400">{{ $p->caseta?->nombre ?? '—' }}</td>
                    <td class="p-3 text-gray-500 text-xs">{{ $p->created_at->format('d/m/Y H:i:s') }}</td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center p-8 text-gray-500">Todavía no se detectó ninguna placa que cumpla el filtro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-gray-700">{{ $placas->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
// Tiempo real: cada paso de la caseta que cumple el filtro aparece arriba sin recargar.
document.addEventListener('nuevo-paso', function (e) {
    const p = e.detail;
    if (!p.cumple_filtro) return;

    const tbody = document.getElementById('tabla-filtradas');
    const vacio = tbody.querySelector('td[colspan]');
    if (vacio) vacio.closest('tr').remove();

    const numeros = (p.placa || '').replace(/[^0-9]/g, '');
    const letras = (p.placa || '').replace(/[^A-Z]/g, '');
    const img = p.imagen_url
        ? `<a href="${p.imagen_url}" target="_blank"><img src="${p.imagen_url}" class="w-16 h-10 object-cover rounded" alt="Evidencia"></a>`
        : '<span class="text-gray-600">—</span>';

    tbody.insertAdjacentHTML('afterbegin', `
    <tr class="border-b border-gray-700 bg-emerald-950/20 paso-row">
        <td class="p-3">${img}</td>
        <td class="p-3 font-mono font-bold text-emerald-400">${p.placa || '—'}</td>
        <td class="p-3 font-mono text-gray-300">${numeros}</td>
        <td class="p-3 font-mono text-gray-300">${letras}</td>
        <td class="p-3 text-gray-400">—</td>
        <td class="p-3 text-gray-400">caseta</td>
        <td class="p-3 text-gray-400">${p.caseta || '—'}</td>
        <td class="p-3 text-gray-500 text-xs">${p.hora}</td>
    </tr>`);

    const total = document.getElementById('contador-total');
    if (total) total.textContent = parseInt(total.textContent || '0', 10) + 1;
});
</script>
@endpush
