<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace App\Http\Controllers;

use App\Models\Alerta;
use Illuminate\Http\Request;

class AlertasController extends Controller
{
    public function index()
    {
        $alertas = Alerta::with(['paso.caseta'])
            ->orderByDesc('id')
            ->paginate(30);

        return view('alertas.index', compact('alertas'));
    }

    public function atender(Alerta $alerta)
    {
        $alerta->update(['atendida' => true]);

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'Alerta marcada como atendida.');
    }

    /** GET /api/v1/casetas/{id}/resumen */
    public function resumenCaseta(int $id)
    {
        $caseta = \App\Models\Caseta::findOrFail($id);
        return response()->json($caseta->resumenHoy());
    }
}
