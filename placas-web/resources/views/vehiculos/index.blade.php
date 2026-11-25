@extends('layouts.app')
@section('title','Vehículos')
@section('page-title','Gestión de Vehículos')

@section('content')
<div class="flex justify-between items-center mb-4">
    <form method="GET" class="flex gap-2">
        <input type="text" name="q" value="{{ $q }}" placeholder="Buscar placa o propietario..."
               class="bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500 w-64">
        <button class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg text-sm transition-colors">
            <i class="fas fa-search"></i>
        </button>
    </form>
    <a href="{{ route('vehiculos.create') }}"
       class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
        <i class="fas fa-plus"></i> Nuevo vehículo
    </a>
</div>

<div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-700/50 text-gray-400 text-xs uppercase">
                <tr>
                    <th class="text-left p-3">Placa</th>
                    <th class="text-left p-3">Propietario</th>
                    <th class="text-left p-3">Categoría</th>
                    <th class="text-left p-3">Ejes</th>
                    <th class="text-left p-3">Saldo</th>
                    <th class="text-left p-3">Estado</th>
                    <th class="text-left p-3">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vehiculos as $v)
                <tr class="border-b border-gray-700 hover:bg-gray-700/20">
                    <td class="p-3 font-mono font-bold text-white">{{ $v->placa }}</td>
                    <td class="p-3 text-gray-300">{{ $v->propietario ?? '—' }}</td>
                    <td class="p-3 capitalize text-gray-300">{{ $v->categoria }}</td>
                    <td class="p-3 text-gray-300">{{ $v->ejes }}</td>
                    <td class="p-3 font-semibold {{ $v->saldo > 0 ? 'text-green-400' : 'text-red-400' }}">
                        Bs {{ number_format($v->saldo, 2) }}
                    </td>
                    <td class="p-3">
                        <span class="{{ $v->activo ? 'badge-ok' : 'badge-no_registrado' }}">
                            {{ $v->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="p-3 flex gap-2">
                        <a href="{{ route('vehiculos.edit', $v) }}"
                           class="text-indigo-400 hover:text-indigo-300 text-xs"><i class="fas fa-edit"></i> Editar</a>
                        <form method="POST" action="{{ route('vehiculos.destroy', $v) }}"
                              onsubmit="return confirm('¿Eliminar {{ $v->placa }}?')">
                            @csrf @method('DELETE')
                            <button class="text-red-400 hover:text-red-300 text-xs"><i class="fas fa-trash"></i> Borrar</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center p-8 text-gray-500">No hay vehículos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-gray-700">{{ $vehiculos->withQueryString()->links() }}</div>
</div>
@endsection
