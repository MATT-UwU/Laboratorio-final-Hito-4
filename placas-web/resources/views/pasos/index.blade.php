{{-- © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4 --}}
@extends('layouts.app')
@section('title','Pasos')
@section('page-title','Historial de Pasos')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <h3 class="font-semibold text-white">Todos los pasos registrados</h3>
    <div class="leyenda-inferencia text-xs text-gray-400">
        <span><i style="background:#22d3ee"></i>Vehículo (M2)</span>
        <span><i style="background:#facc15"></i>Placa (M1)</span>
        <span><i style="background:#e879f9"></i>Pasajeros</span>
        <span><i style="background:#fb923c;border-radius:50%"></i>Llantas (M3)</span>
    </div>
</div>

<div id="pasos-tarjetas" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
    @forelse($pasos as $paso)
        @include('pasos._tarjeta', ['paso' => $paso])
    @empty
        <p id="sin-pasos" class="col-span-full text-center p-8 text-gray-500">No hay pasos registrados aún.</p>
    @endforelse
</div>

<div class="mt-4">{{ $pasos->links() }}</div>
@endsection

@push('scripts')
@include('pasos._tarjetas-js')
<script>
// Pasos nuevos en tiempo real (solo en la primera página).
@if($pasos->onFirstPage())
document.addEventListener('nuevo-paso', function (e) {
    document.getElementById('sin-pasos')?.remove();
    TarjetaPaso.agregar(document.getElementById('pasos-tarjetas'), e.detail, {{ $pasos->perPage() }});
});
@endif
</script>
@endpush
