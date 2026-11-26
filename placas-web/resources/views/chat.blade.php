{{-- © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4 --}}
@extends('layouts.app')
@section('title','Chat — Agente')
@section('page-title','Agente Conversacional')

@section('content')
<div class="max-w-2xl mx-auto flex flex-col h-[calc(100vh-10rem)]">
    <div class="bg-gray-800 rounded-xl border border-gray-700 flex flex-col flex-1 overflow-hidden">

        {{-- Cabecera --}}
        <div class="p-4 border-b border-gray-700 flex items-center gap-3">
            <div class="w-10 h-10 bg-indigo-600 rounded-full flex items-center justify-center">
                <i class="fas fa-robot text-white"></i>
            </div>
            <div>
                <p class="font-semibold text-white">Agente de Peaje</p>
                <p class="text-xs text-green-400 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 bg-green-400 rounded-full"></span> En línea
                </p>
            </div>
        </div>

        {{-- Mensajes --}}
        <div id="chat-mensajes" class="flex-1 overflow-y-auto p-4 space-y-4">
            <div class="flex gap-3">
                <div class="w-8 h-8 bg-indigo-600 rounded-full flex-shrink-0 flex items-center justify-center text-xs">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="bg-gray-700 rounded-2xl rounded-tl-none px-4 py-3 max-w-sm text-sm text-gray-200">
                    Hola, soy el agente del sistema de peaje. Escribe una placa o envía una foto y te diré su estado en el sistema.
                </div>
            </div>
        </div>

        {{-- Input --}}
        <div class="p-4 border-t border-gray-700">
            {{-- Subir imagen --}}
            <div class="flex gap-2 mb-2">
                <label class="cursor-pointer text-xs text-indigo-400 hover:text-indigo-300 flex items-center gap-1">
                    <i class="fas fa-image"></i> Enviar imagen de placa
                    <input type="file" id="chat-imagen" accept="image/*" class="hidden">
                </label>
            </div>
            <form id="chat-form" class="flex gap-2">
                @csrf
                <input type="text" id="chat-input" name="mensaje"
                       placeholder="Escribe una placa ej: 2M12..."
                       class="flex-1 bg-gray-700 border border-gray-600 rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500">
                <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const contenedor = document.getElementById('chat-mensajes');
const form       = document.getElementById('chat-form');
const input      = document.getElementById('chat-input');
const imgInput   = document.getElementById('chat-imagen');
const token      = document.querySelector('meta[name="csrf-token"]').content;
const chatUrl    = @json(route('chat.send'));
const imageUrl   = @json(route('chat.image'));

function agregarMensaje(texto, esUsuario, placaDetectada = null) {
    const fila = document.createElement('div');
    fila.className = esUsuario ? 'flex gap-3 justify-end' : 'flex gap-3';

    const avatar = document.createElement('div');
    avatar.className = `w-8 h-8 ${esUsuario ? 'bg-gray-600' : 'bg-indigo-600'} rounded-full flex-shrink-0 flex items-center justify-center text-xs`;
    const icono = document.createElement('i');
    icono.className = esUsuario ? 'fas fa-user' : 'fas fa-robot';
    avatar.appendChild(icono);

    const burbuja = document.createElement('div');
    burbuja.className = esUsuario
        ? 'bg-indigo-600 rounded-2xl rounded-tr-none px-4 py-3 max-w-sm text-sm text-white'
        : 'bg-gray-700 rounded-2xl rounded-tl-none px-4 py-3 max-w-sm text-sm text-gray-200';
    burbuja.textContent = texto;

    if (placaDetectada) {
        const detalle = document.createElement('p');
        detalle.className = 'text-xs text-indigo-300 mt-1';
        detalle.append('Placa detectada: ');
        const placa = document.createElement('strong');
        placa.textContent = placaDetectada;
        detalle.appendChild(placa);
        burbuja.appendChild(detalle);
    }

    fila.append(esUsuario ? burbuja : avatar, esUsuario ? avatar : burbuja);
    contenedor.appendChild(fila);
    contenedor.scrollTop = contenedor.scrollHeight;
}

function agregarTyping() {
    const el = document.createElement('div');
    el.className = 'flex gap-3';
    const avatar = document.createElement('div');
    avatar.className = 'w-8 h-8 bg-indigo-600 rounded-full flex-shrink-0 flex items-center justify-center text-xs';
    const icono = document.createElement('i');
    icono.className = 'fas fa-robot';
    avatar.appendChild(icono);
    const burbuja = document.createElement('div');
    burbuja.className = 'bg-gray-700 rounded-2xl rounded-tl-none px-4 py-3 text-gray-400 text-sm';
    const texto = document.createElement('span');
    texto.className = 'animate-pulse';
    texto.textContent = 'Consultando...';
    burbuja.appendChild(texto);
    el.append(avatar, burbuja);
    contenedor.appendChild(el);
    contenedor.scrollTop = contenedor.scrollHeight;
    return el;
}

async function leerRespuesta(res) {
    let data;
    try {
        data = await res.json();
    } catch {
        throw new Error(`El servidor devolvió una respuesta inválida (${res.status}).`);
    }

    if (!data || typeof data !== 'object') {
        throw new Error('El servidor devolvió una respuesta inválida.');
    }

    if (!res.ok) {
        throw new Error(data.message || data.error || 'No se pudo completar la consulta.');
    }
    return data;
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    agregarMensaje(msg, true);
    const typing = agregarTyping();

    try {
        const res = await fetch(chatUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: JSON.stringify({ mensaje: msg }),
        });
        const data = await leerRespuesta(res);
        typing.remove();
        agregarMensaje(data.respuesta || 'Sin respuesta.', false);
    } catch (error) {
        typing.remove();
        agregarMensaje(error.message || 'Error de conexión. Intenta de nuevo.', false);
    }
});

imgInput.addEventListener('change', async () => {
    const file = imgInput.files[0];
    if (!file) return;
    agregarMensaje(`Imagen enviada: ${file.name}`, true);
    const typing = agregarTyping();

    const fd = new FormData();
    fd.append('file', file);

    try {
        const res = await fetch(imageUrl, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: fd,
        });
        const data = await leerRespuesta(res);
        typing.remove();
        agregarMensaje(data.mensaje || data.respuesta || 'No se pudo interpretar la respuesta del servicio.', false, data.placa_detectada);
    } catch (error) {
        typing.remove();
        agregarMensaje(error.message || 'Error al procesar la imagen.', false);
    }

    imgInput.value = '';
});
</script>
@endpush
