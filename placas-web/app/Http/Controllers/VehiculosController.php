<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace App\Http\Controllers;

use App\Models\Vehiculo;
use Illuminate\Http\Request;

class VehiculosController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');
        $vehiculos = Vehiculo::when($q, fn($query) => $query->where('placa', 'like', "%$q%")
            ->orWhere('propietario', 'like', "%$q%"))
            ->orderBy('placa')
            ->paginate(20);

        if ($request->expectsJson()) {
            return response()->json($vehiculos);
        }
        return view('vehiculos.index', compact('vehiculos', 'q'));
    }

    public function create()
    {
        return view('vehiculos.form', ['vehiculo' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'placa'       => 'required|string|max:20|unique:vehiculos,placa',
            'propietario' => 'nullable|string|max:255',
            'categoria'   => 'required|in:moto,auto,minibus,camion,bus',
            'ejes'        => 'required|integer|min:1|max:10',
            'saldo'       => 'required|numeric|min:0',
            'activo'      => 'boolean',
        ]);
        $data['placa'] = Vehiculo::normalizar($data['placa']);

        $vehiculo = Vehiculo::create($data);

        if ($request->expectsJson()) {
            return response()->json($vehiculo, 201);
        }
        return redirect()->route('vehiculos.index')->with('success', 'Vehículo creado.');
    }

    public function edit(Vehiculo $vehiculo)
    {
        return view('vehiculos.form', compact('vehiculo'));
    }

    public function update(Request $request, Vehiculo $vehiculo)
    {
        $data = $request->validate([
            'propietario' => 'nullable|string|max:255',
            'categoria'   => 'required|in:moto,auto,minibus,camion,bus',
            'ejes'        => 'required|integer|min:1|max:10',
            'saldo'       => 'required|numeric|min:0',
            'activo'      => 'boolean',
        ]);
        $vehiculo->update($data);

        if ($request->expectsJson()) {
            return response()->json($vehiculo);
        }
        return redirect()->route('vehiculos.index')->with('success', 'Vehículo actualizado.');
    }

    public function destroy(Vehiculo $vehiculo)
    {
        $vehiculo->delete();
        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return redirect()->route('vehiculos.index')->with('success', 'Vehículo eliminado.');
    }
}
