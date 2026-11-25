@extends('layouts.app')
@section('title','Estado del Endpoint')
@section('page-title','Estado del Endpoint de Inferencia')

@section('content')
<div class="max-w-lg mx-auto space-y-4">
    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-white">Endpoint de Inferencia</h3>
            <button onclick="verificar()" id="btn-verificar"
                    class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm px-4 py-2 rounded-lg transition-colors">
                <i class="fas fa-sync-alt mr-1"></i> Verificar
            </button>
        </div>

        <p class="text-xs text-gray-400 mb-4 font-mono break-all" id="url-inferencia">
            {{ env('INFERENCE_URL', '(no configurado)') }}
        </p>

        <div id="estado-resultado" class="hidden">
            <div id="estado-ok" class="hidden p-4 bg-green-900/40 border border-green-700 rounded-lg text-green-300">
                <i class="fas fa-check-circle mr-2"></i>
                <strong>Activo</strong> — El endpoint responde correctamente.
                <p class="text-xs mt-1" id="latencia-info"></p>
            </div>
            <div id="estado-error" class="hidden p-4 bg-red-900/40 border border-red-700 rounded-lg text-red-300">
                <i class="fas fa-times-circle mr-2"></i>
                <strong>Sin respuesta</strong> — El endpoint no responde.
                <p class="text-xs mt-1" id="error-info"></p>
            </div>
        </div>

        <div id="cargando" class="hidden text-center py-4 text-gray-400 text-sm">
            <i class="fas fa-spinner fa-spin mr-2"></i>Verificando...
        </div>
    </div>

    <div class="bg-gray-800 rounded-xl border border-gray-700 p-5 text-sm text-gray-300 space-y-2">
        <p class="font-semibold text-white mb-3">Configuración</p>
        <div class="flex justify-between">
            <span class="text-gray-400">Variable de entorno</span>
            <span class="font-mono">INFERENCE_URL</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-400">Valor actual</span>
            <span class="font-mono text-xs">{{ env('INFERENCE_URL') ?: '—' }}</span>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
async function verificar() {
    const btn      = document.getElementById('btn-verificar');
    const cargando = document.getElementById('cargando');
    const resultado= document.getElementById('estado-resultado');
    const ok       = document.getElementById('estado-ok');
    const err      = document.getElementById('estado-error');

    btn.disabled = true;
    cargando.classList.remove('hidden');
    resultado.classList.add('hidden');
    ok.classList.add('hidden');
    err.classList.add('hidden');

    const t0 = Date.now();
    try {
        const res = await fetch('/api/v1/casetas/1/resumen', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        });
        const ms = Date.now() - t0;

        // Si llegó acá la API interna responde; probar inferencia externamente
        const infUrl = document.getElementById('url-inferencia').textContent.trim();
        if (infUrl === '(no configurado)') {
            throw new Error('INFERENCE_URL no configurado en .env');
        }

        const infRes = await fetch(infUrl + '/', { signal: AbortSignal.timeout(10000) });
        const latencia = Date.now() - t0;

        document.getElementById('latencia-info').textContent = `Latencia: ${latencia} ms`;
        ok.classList.remove('hidden');
    } catch(e) {
        document.getElementById('error-info').textContent = e.message;
        err.classList.remove('hidden');
    }

    cargando.classList.add('hidden');
    resultado.classList.remove('hidden');
    btn.disabled = false;
}
</script>
@endpush
