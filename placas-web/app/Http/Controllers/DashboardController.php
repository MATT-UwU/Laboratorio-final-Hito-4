<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Vehiculo;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $inicioHoy = today()->startOfDay();

        $totalPasosHoy     = Paso::where('created_at', '>=', $inicioHoy)->count();
        $recaudacionHoy    = Paso::where('created_at', '>=', $inicioHoy)->sum('monto') ?? 0;
        $alertasSinAtender = Alerta::where('atendida', false)->count();
        $totalVehiculos    = Vehiculo::where('activo', true)->count();

        $ultimosPasos = Paso::with(['caseta'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $ultimasAlertas = Alerta::with(['paso.caseta'])
            ->where('atendida', false)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $casetas = Caseta::where('activa', true)->get()->map(fn($c) => [
            'id'          => $c->id,
            'nombre'      => $c->nombre,
            'resumen'     => $c->resumenHoy(),
        ]);

        return view('dashboard', compact(
            'totalPasosHoy', 'recaudacionHoy', 'alertasSinAtender',
            'totalVehiculos', 'ultimosPasos', 'ultimasAlertas', 'casetas'
        ));
    }
}
