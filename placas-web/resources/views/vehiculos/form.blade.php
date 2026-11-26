{{-- © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4 --}}
@extends('layouts.app')
@section('title', $vehiculo ? 'Editar Vehículo' : 'Nuevo Vehículo')
@section('page-title', $vehiculo ? 'Editar Vehículo' : 'Nuevo Vehículo')

@section('content')
<div class="max-w-lg mx-auto bg-gray-800 rounded-xl border border-gray-700 p-6">
    <form method="POST" action="{{ $vehiculo ? route('vehiculos.update', $vehiculo) : route('vehiculos.store') }}">
        @csrf
        @if($vehiculo) @method('PUT') @endif

        <div class="space-y-4">
            @unless($vehiculo)
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Placa *</label>
                <input type="text" name="placa" value="{{ old('placa') }}" required
                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 uppercase"
                       placeholder="Ej: 2M12">
                @error('placa') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            @endunless

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Propietario</label>
                <input type="text" name="propietario" value="{{ old('propietario', $vehiculo?->propietario) }}"
                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500"
                       placeholder="Nombre del propietario">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Categoría *</label>
                    <select name="categoria" required
                            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500">
                        @foreach(['moto','auto','minibus','camion','bus'] as $cat)
                        <option value="{{ $cat }}" {{ old('categoria', $vehiculo?->categoria) === $cat ? 'selected' : '' }}>
                            {{ ucfirst($cat) }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Ejes *</label>
                    <input type="number" name="ejes" value="{{ old('ejes', $vehiculo?->ejes ?? 2) }}" min="1" max="10" required
                           class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Saldo (Bs) *</label>
                <input type="number" name="saldo" value="{{ old('saldo', $vehiculo?->saldo ?? 0) }}" step="0.01" min="0" required
                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500">
            </div>

            @if($vehiculo)
            <div class="flex items-center gap-3">
                <input type="checkbox" name="activo" value="1" id="activo"
                       {{ old('activo', $vehiculo->activo) ? 'checked' : '' }}
                       class="w-4 h-4 text-indigo-600 rounded">
                <label for="activo" class="text-sm text-gray-300">Vehículo activo</label>
            </div>
            @endif
        </div>

        <div class="flex gap-3 mt-6">
            <button type="submit"
                    class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white py-2.5 rounded-lg font-medium transition-colors">
                {{ $vehiculo ? 'Guardar cambios' : 'Crear vehículo' }}
            </button>
            <a href="{{ route('vehiculos.index') }}"
               class="flex-1 bg-gray-700 hover:bg-gray-600 text-center text-white py-2.5 rounded-lg font-medium transition-colors">
                Cancelar
            </a>
        </div>
    </form>
</div>
@endsection
