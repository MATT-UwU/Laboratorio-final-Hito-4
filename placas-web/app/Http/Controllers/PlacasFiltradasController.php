<?php

namespace App\Http\Controllers;

use App\Models\PlacaFiltrada;
use App\Support\FiltroPlaca;
use Illuminate\Http\Request;

/**
 * Placas que cumplen el filtro del estudiante: números empiezan y terminan en 2, letras contienen M.
 * Se guardan aparte del cobro de peaje (categoría, ejes y tarifa siguen su flujo normal).
 */
class PlacasFiltradasController extends Controller
{
    /** GET /placas-filtradas — página web */
    public function index()
    {
        $placas = PlacaFiltrada::with(['caseta', 'usuario'])->latest()->paginate(25);

        return view('placas_filtradas.index', [
            'placas'      => $placas,
            'regla'       => FiltroPlaca::descripcion(),
            'total'       => PlacaFiltrada::count(),
            'unicas'      => PlacaFiltrada::distinct('placa')->count('placa'),
        ]);
    }

    /** GET /api/v1/placas-filtradas — misma lista en JSON para la app */
    public function apiIndex(Request $request)
    {
        return response()->json([
            'regla'  => FiltroPlaca::descripcion(),
            'placas' => PlacaFiltrada::with('caseta:id,nombre')
                ->latest()
                ->limit(min((int) $request->query('limite', 50), 200))
                ->get(['id', 'placa', 'numeros', 'letras', 'confianza_ocr', 'origen', 'caseta_id', 'imagen_url', 'created_at']),
        ]);
    }
}
