<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Paso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Bonificación "alerta en un dispositivo de hardware".
 * Un ESP32 consulta este endpoint cada ~1 s y enciende LED/buzzer según el estado.
 * Se autentica con un token propio del dispositivo (DEVICE_TOKEN), distinto del de los usuarios.
 */
class DispositivoController extends Controller
{
    /** GET /api/v1/dispositivo/estado  (cabecera X-Device-Token) */
    public function estado(Request $request)
    {
        $token = (string) config('services.dispositivo.token', '');
        $enviado = (string) $request->header('X-Device-Token', '');
        if ($token === '' || !hash_equals($token, $enviado)) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $ultimoPaso = Paso::latest('id')->first(['id', 'placa_normalizada', 'estado', 'categoria_detectada', 'pasajeros', 'created_at']);
        $ultimaAlerta = Alerta::latest('id')->first(['id', 'tipo', 'atendida', 'created_at']);

        // Estado de la inferencia (cacheado 15 s para no golpear Colab/RunPod en cada consulta).
        $inferenciaOk = Cache::remember('dispositivo_inferencia_ok', 15, function () {
            $url = config('services.inference.url', '');
            if ($url === '') {
                return false;
            }
            try {
                return Http::timeout(4)->get(rtrim($url, '/') . '/')->successful();
            } catch (\Throwable $e) {
                return false;
            }
        });

        return response()->json([
            'inferencia_ok'       => $inferenciaOk,
            'ultimo_paso_id'      => $ultimoPaso?->id,
            'ultimo_paso_estado'  => $ultimoPaso?->estado,
            'ultima_placa'        => $ultimoPaso?->placa_normalizada,
            'ultima_categoria'    => $ultimoPaso?->categoria_detectada,
            'ultimos_pasajeros'   => $ultimoPaso?->pasajeros,
            'ultima_alerta_id'    => $ultimaAlerta?->id,
            'ultima_alerta_tipo'  => $ultimaAlerta?->tipo,
            'alertas_pendientes'  => Alerta::where('atendida', false)->count(),
            'hora'                => now()->format('H:i:s'),
        ]);
    }
}
