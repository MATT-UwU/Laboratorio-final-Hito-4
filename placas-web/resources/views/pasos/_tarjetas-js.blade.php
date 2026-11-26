{{-- © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4 --}}
{{-- Dibuja sobre cada evidencia las cajas de la inferencia y arma tarjetas nuevas en tiempo real. --}}
<style>
    .leyenda-inferencia span { display: inline-flex; align-items: center; gap: .3rem; margin-right: .8rem; }
    .leyenda-inferencia i { width: .7rem; height: .7rem; border-radius: 2px; display: inline-block; }
</style>
<script>
window.TarjetaPaso = (function () {
    const COLORES = { vehiculo: '#22d3ee', placa: '#facc15', persona: '#e879f9', llanta: '#fb923c' };
    const NS = 'http://www.w3.org/2000/svg';
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    function etiqueta(svg, x, y, texto, color, escala) {
        const fs = 20 * escala, pad = 4 * escala;
        const t = document.createElementNS(NS, 'text');
        t.setAttribute('x', x + pad); t.setAttribute('y', Math.max(fs, y - pad));
        t.setAttribute('font-size', fs); t.setAttribute('font-family', 'monospace'); t.setAttribute('font-weight', 'bold');
        t.setAttribute('fill', '#111827'); t.textContent = texto;
        const bg = document.createElementNS(NS, 'rect');
        svg.appendChild(bg); svg.appendChild(t);
        const b = t.getBBox();
        bg.setAttribute('x', b.x - pad); bg.setAttribute('y', b.y - pad / 2);
        bg.setAttribute('width', b.width + 2 * pad); bg.setAttribute('height', b.height + pad);
        bg.setAttribute('fill', color);
    }

    function caja(svg, b, color, texto, escala) {
        const r = document.createElementNS(NS, 'rect');
        r.setAttribute('x', b[0]); r.setAttribute('y', b[1]);
        r.setAttribute('width', Math.max(1, b[2] - b[0])); r.setAttribute('height', Math.max(1, b[3] - b[1]));
        r.setAttribute('fill', color); r.setAttribute('fill-opacity', '0.08');
        r.setAttribute('stroke', color); r.setAttribute('stroke-width', 3 * escala);
        svg.appendChild(r);
        if (texto) etiqueta(svg, b[0], b[1], texto, color, escala);
    }

    function dibujar(img) {
        const svg = img.parentElement.querySelector('svg.overlay-inferencia');
        if (!svg || !img.naturalWidth) return;
        let o = {};
        try { o = JSON.parse(img.dataset.overlay || '{}') || {}; } catch (e) { o = {}; }
        const W = img.naturalWidth, H = img.naturalHeight, escala = Math.max(W, H) / 720;
        svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
        svg.innerHTML = '';
        if (o.vehiculo) caja(svg, o.vehiculo, COLORES.vehiculo, (o.categoria || 'vehículo').toUpperCase(), escala);
        (o.personas || []).forEach((p, i) => caja(svg, p, COLORES.persona, 'PASAJERO ' + (i + 1), escala));
        if (o.placa) caja(svg, o.placa, COLORES.placa, o.placa_texto || 'PLACA', escala);
        (o.llantas || []).forEach((k) => {
            const c = document.createElementNS(NS, 'circle');
            c.setAttribute('cx', k[0]); c.setAttribute('cy', k[1]); c.setAttribute('r', 7 * escala);
            c.setAttribute('fill', COLORES.llanta); c.setAttribute('stroke', '#111827'); c.setAttribute('stroke-width', 2 * escala);
            svg.appendChild(c);
        });
    }

    function iniciar(raiz) {
        (raiz || document).querySelectorAll('img[data-overlay]').forEach((img) => {
            if (img.complete) dibujar(img); else img.addEventListener('load', () => dibujar(img), { once: true });
        });
    }

    const BORDES = { ok: 'border-green-600/60', sin_saldo: 'border-amber-500/60', no_registrado: 'border-red-600/60', alerta: 'border-red-600/60' };

    // Tarjeta para un paso recibido por SSE (mismos datos que la vista Blade).
    function html(p) {
        const img = p.imagen_url
            ? `<a href="${esc(p.imagen_url)}" target="_blank"><img src="${esc(p.imagen_url)}" class="w-full h-full object-contain" data-overlay='${esc(JSON.stringify(p.overlay || {}))}'>
               <svg class="overlay-inferencia absolute inset-0 w-full h-full pointer-events-none" preserveAspectRatio="xMidYMid meet"></svg></a>`
            : '<div class="w-full h-full flex items-center justify-center text-gray-600 text-sm">Sin evidencia</div>';
        return `<div class="tarjeta-paso paso-row bg-gray-800 rounded-xl border ${BORDES[p.estado] || 'border-gray-700'} overflow-hidden flex flex-col">
            <div class="relative bg-black aspect-video">${img}</div>
            <div class="p-3 flex-1 flex flex-col gap-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="font-mono font-bold text-lg text-white tracking-wider">${esc(p.placa || '—')}</span>
                    <span class="badge-${esc(p.estado)}">${esc((p.estado || '').replace('_', ' '))}</span>
                </div>
                <div class="grid grid-cols-4 gap-1 text-center text-xs">
                    <div class="bg-gray-700/40 rounded p-1"><p class="text-gray-500">Categoría</p><p class="text-gray-200 capitalize">${esc(p.categoria || '—')}</p></div>
                    <div class="bg-gray-700/40 rounded p-1"><p class="text-gray-500">Ejes</p><p class="text-gray-200">${esc(p.ejes ?? '—')}</p></div>
                    <div class="bg-gray-700/40 rounded p-1"><p class="text-gray-500">Pasajeros</p><p class="text-gray-200">${esc(p.pasajeros ?? '—')}</p></div>
                    <div class="bg-gray-700/40 rounded p-1"><p class="text-gray-500">Monto</p><p class="text-green-400 font-semibold">${p.monto ? 'Bs ' + esc(p.monto) : '—'}</p></div>
                </div>
                <div class="flex items-center justify-between text-xs text-gray-500 mt-auto">
                    <span>${esc(p.caseta || '—')}</span><span>#${esc(p.id)} · ${esc(p.hora)}</span>
                </div>
            </div>
        </div>`;
    }

    function agregar(contenedor, p, maximo) {
        contenedor.insertAdjacentHTML('afterbegin', html(p));
        iniciar(contenedor.firstElementChild);
        while (maximo && contenedor.children.length > maximo) contenedor.removeChild(contenedor.lastElementChild);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => iniciar()); else iniciar();
    return { iniciar, dibujar, html, agregar };
})();
</script>
