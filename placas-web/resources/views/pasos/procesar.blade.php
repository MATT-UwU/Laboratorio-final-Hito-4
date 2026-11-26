@extends('layouts.app')
@section('title','Procesar imagen')
@section('page-title','Procesar imágenes desde el sistema')

@section('content')
<div class="bg-gray-800 rounded-xl border border-gray-700 p-5 mb-6">
    <p class="text-sm text-gray-400 mb-4">
        Sube una o varias fotos de vehículos. Cada foto pasa por el mismo flujo que la caseta de la app:
        inferencia en la nube (placa + OCR, tipo de vehículo, ejes y pasajeros), tarifa, alertas, filtro de placas
        y registro del paso en tiempo real en el panel.
    </p>
    <form id="form-procesar" class="flex flex-col md:flex-row gap-4 items-stretch md:items-end">
        @csrf
        <label class="flex-1 border-2 border-dashed border-gray-600 hover:border-indigo-500 rounded-xl p-6 text-center cursor-pointer transition-colors" id="zona-archivos">
            <i class="fas fa-cloud-arrow-up text-3xl text-indigo-400 mb-2"></i>
            <p class="text-gray-300 text-sm">Arrastra las fotos aquí o haz clic para elegirlas</p>
            <p class="text-gray-500 text-xs mt-1" id="archivos-elegidos">JPG, PNG o WEBP · hasta 20 MB cada una</p>
            <input type="file" id="archivos" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
        </label>
        <div class="md:w-56">
            <label class="text-xs text-gray-400 block mb-1">Caseta</label>
            <select id="caseta" class="w-full bg-gray-700 border border-gray-600 rounded-lg p-2 text-white text-sm">
                @foreach($casetas as $c)
                    <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                @endforeach
            </select>
            <button type="submit" id="btn-procesar"
                    class="mt-3 w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-semibold rounded-lg py-2 text-sm">
                <i class="fas fa-microchip mr-1"></i> Procesar
            </button>
        </div>
    </form>
</div>

<div class="flex flex-wrap items-center justify-between gap-3 mb-3">
    <h3 class="font-semibold text-white">Resultados</h3>
    <div class="leyenda-inferencia text-xs text-gray-400">
        <span><i style="background:#22d3ee"></i>Vehículo</span>
        <span><i style="background:#facc15"></i>Placa</span>
        <span><i style="background:#e879f9"></i>Pasajeros</span>
        <span><i style="background:#fb923c;border-radius:50%"></i>Llantas</span>
    </div>
</div>
<div id="resultados" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4"></div>
@endsection

@push('scripts')
@include('pasos._tarjetas-js')
<script>
(function () {
    const input = document.getElementById('archivos');
    const zona = document.getElementById('zona-archivos');
    const resultados = document.getElementById('resultados');
    const btn = document.getElementById('btn-procesar');
    const token = document.querySelector('#form-procesar input[name=_token]').value;
    let archivos = [];

    function mostrarElegidos() {
        document.getElementById('archivos-elegidos').textContent = archivos.length
            ? archivos.length + ' foto(s): ' + archivos.map(f => f.name).join(', ')
            : 'JPG, PNG o WEBP · hasta 20 MB cada una';
    }
    input.addEventListener('change', () => { archivos = Array.from(input.files); mostrarElegidos(); });
    ['dragover', 'dragenter'].forEach(ev => zona.addEventListener(ev, e => { e.preventDefault(); zona.classList.add('border-indigo-500'); }));
    ['dragleave', 'drop'].forEach(ev => zona.addEventListener(ev, e => { e.preventDefault(); zona.classList.remove('border-indigo-500'); }));
    zona.addEventListener('drop', e => { archivos = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/')); mostrarElegidos(); });

    function tarjetaMensaje(archivo, titulo, detalle, color) {
        const url = URL.createObjectURL(archivo);
        resultados.insertAdjacentHTML('afterbegin', `
        <div class="bg-gray-800 rounded-xl border ${color} overflow-hidden flex flex-col">
            <div class="relative bg-black aspect-video"><img src="${url}" class="w-full h-full object-contain opacity-70"></div>
            <div class="p-3"><p class="font-semibold text-white">${titulo}</p><p class="text-xs text-gray-400 mt-1">${detalle}</p></div>
        </div>`);
    }

    document.getElementById('form-procesar').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!archivos.length) { input.click(); return; }
        btn.disabled = true;
        for (let i = 0; i < archivos.length; i++) {
            const f = archivos[i];
            btn.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i> ${i + 1}/${archivos.length}`;
            const fd = new FormData();
            fd.append('file', f);
            fd.append('caseta_id', document.getElementById('caseta').value);
            try {
                const r = await fetch('{{ route('procesar.enviar') }}', {
                    method: 'POST', body: fd,
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                });
                const d = await r.json().catch(() => ({}));
                if (d.tarjeta) {
                    TarjetaPaso.agregar(resultados, d.tarjeta);
                } else if (d.estado === 'sin_placa') {
                    tarjetaMensaje(f, 'Sin placa legible', 'La inferencia no encontró una placa en esta foto. Prueba con otra más cercana o mejor iluminada.', 'border-gray-600');
                } else {
                    tarjetaMensaje(f, 'Error al procesar', (d.error || d.message || ('HTTP ' + r.status)) + (r.status === 503 ? ' — ¿está encendido el servidor de inferencia en Colab?' : ''), 'border-red-700');
                }
            } catch (err) {
                tarjetaMensaje(f, 'Sin conexión', String(err), 'border-red-700');
            }
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-microchip mr-1"></i> Procesar';
        archivos = []; input.value = ''; mostrarElegidos();
    });
})();
</script>
@endpush
