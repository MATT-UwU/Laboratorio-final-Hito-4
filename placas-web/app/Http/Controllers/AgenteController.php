<?php

namespace App\Http\Controllers;

use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AgenteController extends Controller
{
    /**
     * POST /api/v1/agente/chat  — recibe una placa en texto
     * POST /api/v1/placas/consultar
     */
    public function consultar(Request $request)
    {
        $request->validate(['placa' => 'required|string|max:30']);
        $placa = Vehiculo::normalizar($request->placa);
        return response()->json($this->estadoPlaca($placa));
    }

    /**
     * POST /api/v1/placas/consultar-imagen — recibe imagen, llama a M1 + OCR
     */
    public function consultarImagen(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:jpeg,jpg,png,webp']);

        $inferenceUrl = config('services.inference.url', '');
        if (empty($inferenceUrl)) {
            return response()->json(['error' => 'INFERENCE_URL no configurado'], 503);
        }
        $inferenceToken = config('services.inference.token', '');
        if (empty($inferenceToken)) {
            return response()->json(['error' => 'INFERENCE_API_TOKEN no configurado'], 503);
        }

        try {
            $file = $request->file('file');
            $resp = Http::withHeaders(['X-API-Token' => $inferenceToken])
                ->connectTimeout(5)
                ->timeout(30)
                ->attach('file', file_get_contents($file->getRealPath()), 'placa.jpg')
                ->post(rtrim($inferenceUrl, '/') . '/predict');
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'Error al contactar inferencia'], 503);
        }

        if (!$resp->successful()) {
            logger()->warning('El endpoint de inferencia rechazó la consulta por imagen.', [
                'status' => $resp->status(),
            ]);
            return response()->json(['error' => 'El endpoint de inferencia falló'], 502);
        }

        $data = $resp->json();
        if (!is_array($data) || !array_key_exists('placa', $data) || (!is_null($data['placa']) && !is_string($data['placa']))) {
            logger()->warning('El endpoint de inferencia devolvió una respuesta incompatible.');
            return response()->json(['error' => 'La respuesta de inferencia tiene un formato inválido'], 502);
        }

        $placa = isset($data['placa'])
            ? (Vehiculo::extraerPlacaBoliviana($data['placa']) ?? Vehiculo::normalizar($data['placa']))
            : null;

        if (!$placa) {
            return response()->json([
                'estado'   => 'sin_placa',
                'mensaje'  => 'No se detectó ninguna placa en la imagen. Intenta con otra foto con mejor iluminación.',
            ]);
        }

        // Filtro de placas del estudiante: también se guarda si llega por consulta con foto.
        $placaFiltrada = \App\Models\PlacaFiltrada::registrarSiCumple($placa, [
            'confianza_ocr' => is_numeric($data['confianza'] ?? null) ? $data['confianza'] : null,
            'origen'        => 'consulta',
            'usuario_id'    => $request->user()?->id,
        ]);

        return response()->json(array_merge(
            [
                'placa_detectada' => $placa,
                'confianza'       => $data['confianza'] ?? null,
                'cumple_filtro'   => $placaFiltrada !== null,
            ],
            $this->estadoPlaca($placa)
        ));
    }

    /**
     * POST /api/v1/agente/chat — para el chat web (texto libre)
     */
    public function chat(Request $request)
    {
        $request->validate(['mensaje' => 'required|string|max:500']);
        $mensaje = $request->mensaje;

        // Aceptar códigos alfanuméricos y evitar confundir palabras como "dime" con una placa.
        preg_match_all('/(?<![A-Z0-9])[A-Z0-9]{4,8}(?![A-Z0-9])/i', $mensaje, $matches);
        $placas = array_values(array_unique(array_filter(
            array_map(fn ($valor) => Vehiculo::normalizar($valor), $matches[0]),
            fn ($valor) => preg_match('/[A-Z]/', $valor) && preg_match('/[0-9]/', $valor)
        )));

        if (count($placas) !== 1) {
            return response()->json([
                'respuesta' => count($placas) > 1
                    ? 'Por favor consulta una placa a la vez.'
                    : 'Por favor escribe la placa que deseas consultar (ej: 2M12, ABC123).',
            ]);
        }

        $posiblePlaca = $placas[0];
        $estado = $this->estadoPlaca($posiblePlaca);

        return response()->json([
            'placa'     => $posiblePlaca,
            'respuesta' => $estado['mensaje'],
            'datos'     => $estado,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function estadoPlaca(string $placa): array
    {
        $vehiculo = Vehiculo::where('placa', $placa)->first();

        // Sugerir coincidencias con errores típicos de OCR (O↔0, I↔1, B↔8, S↔5)
        $sugerencias = [];
        if (!$vehiculo) {
            $sugerencias = $this->sugerencias($placa);
        }

        $pasos = \App\Models\Paso::where('placa_normalizada', $placa)
            ->orderByDesc('created_at')
            ->limit(5)
            ->with('caseta')
            ->get();

        // Determinar estado
        if ($vehiculo && $pasos->isNotEmpty()) {
            $ultimo = $pasos->first();
            return [
                'estado'      => 'registrada_con_paso',
                'placa'       => $placa,
                'vehiculo'    => $vehiculo,
                'ultimo_paso' => $ultimo,
                'mensaje'     => "✅ Placa {$placa} registrada. Último paso: {$ultimo->created_at->format('d/m/Y H:i')} · Caseta: {$ultimo->caseta?->nombre} · Categoría: {$ultimo->categoria_detectada} · Monto: Bs {$ultimo->monto}.",
                'historial'   => $pasos,
            ];
        }

        if ($vehiculo && $pasos->isEmpty()) {
            return [
                'estado'   => 'registrada_sin_paso',
                'placa'    => $placa,
                'vehiculo' => $vehiculo,
                'mensaje'  => "📋 Placa {$placa} está registrada en el sistema pero nunca ha pasado por el peaje.",
            ];
        }

        // Buscar si la cámara la detectó alguna vez (paso sin vehículo registrado)
        $detectado = $pasos->isNotEmpty();

        if ($detectado) {
            return [
                'estado'      => 'identificada_no_registrada',
                'placa'       => $placa,
                'sugerencias' => $sugerencias,
                'mensaje'     => "⚠️ Placa {$placa} fue detectada por la cámara pero NO está registrada en el sistema. Posible evasión." . ($sugerencias ? " ¿Quisiste decir: " . implode(', ', $sugerencias) . "?" : ""),
            ];
        }

        return [
            'estado'      => 'no_existe',
            'placa'       => $placa,
            'sugerencias' => $sugerencias,
            'mensaje'     => "❌ No existe ningún registro de la placa {$placa}." . ($sugerencias ? " ¿Quisiste decir: " . implode(', ', $sugerencias) . "?" : ""),
        ];
    }

    /** Genera variaciones de la placa por errores típicos de OCR */
    private function sugerencias(string $placa): array
    {
        $mapa = ['O' => '0', '0' => 'O', 'I' => '1', '1' => 'I', 'B' => '8', '8' => 'B', 'S' => '5', '5' => 'S'];
        $variantes = [];

        for ($i = 0; $i < strlen($placa); $i++) {
            $c = $placa[$i];
            if (isset($mapa[$c])) {
                $variante = substr($placa, 0, $i) . $mapa[$c] . substr($placa, $i + 1);
                if (Vehiculo::where('placa', $variante)->exists()) {
                    $variantes[] = $variante;
                }
            }
        }
        return array_values(array_unique($variantes));
    }
}
