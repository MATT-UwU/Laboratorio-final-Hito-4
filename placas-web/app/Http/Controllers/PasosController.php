<?php

namespace App\Http\Controllers;

use App\Models\Alerta;
use App\Models\Caseta;
use App\Models\Paso;
use App\Models\PlacaFiltrada;
use App\Models\Tarifa;
use App\Models\Vehiculo;
use App\Support\FiltroPlaca;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PasosController extends Controller
{
    private const FRAME_PRESENCE_TTL_MINUTES = 10;
    private const FRAME_MISSES_TO_RELEASE = 8;   // frames seguidos sin placa para dar por ido al vehículo
    private const PLACA_MAX_DIFERENCIA = 2;      // caracteres distintos tolerados (el OCR alterna M/H, 0/O…)
    private const PLACA_MIN_CARACTERES = 5;
    private const PLACA_MIN_CONFIANZA = 0.75;          // lecturas sin patrón de placa boliviana
    private const PLACA_MIN_CONFIANZA_PATRON = 0.10;   // lecturas con patrón 3–4 dígitos + 3 letras

    /**
     * POST /api/v1/pasos/frame
     * Recibe un frame desde la app, llama a la inferencia, registra el paso
     * y emite SSE para el panel en tiempo real.
     */
    public function recibirFrame(Request $request)
    {
        $request->validate([
            'file'      => 'required|file|mimes:jpeg,jpg,png,webp',
            'caseta_id' => 'required|exists:casetas,id',
        ]);

        $usuario  = $request->user();
        $casetaId = $request->caseta_id;

        // 1. Llamar al endpoint de inferencia (RunPod/Colab)
        $inferenceUrl = config('services.inference.url', '');
        if (empty($inferenceUrl)) {
            return response()->json(['error' => 'INFERENCE_URL no configurado'], 503);
        }
        $inferenceToken = config('services.inference.token', '');
        if (empty($inferenceToken)) {
            return response()->json(['error' => 'INFERENCE_API_TOKEN no configurado'], 503);
        }

        $file = $request->file('file');

        try {
            $resp = Http::withHeaders(['X-API-Token' => $inferenceToken])
                ->connectTimeout(5)
                ->timeout(30)
                ->attach('file', file_get_contents($file->getRealPath()), 'frame.jpg')
                ->post(rtrim($inferenceUrl, '/') . '/predict');
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'Error al contactar inferencia'], 503);
        }

        if (!$resp->successful()) {
            logger()->warning('El endpoint de inferencia rechazó el frame.', [
                'status' => $resp->status(),
            ]);
            return response()->json(['error' => 'Inferencia devolvió error'], 502);
        }

        $data = $resp->json();
        if (!is_array($data)) {
            return response()->json(['error' => 'La respuesta de inferencia tiene un formato inválido'], 502);
        }

        // Extraer campos del JSON de inferencia
        $placaLeida       = $data['placa']       ?? null;
        $confianza        = $data['confianza']    ?? null;
        $categoria        = $data['categoria']    ?? 'auto';
        $ejes             = (int)($data['ejes']   ?? 2);
        $pasajeros        = isset($data['pasajeros']) && is_numeric($data['pasajeros']) ? max(0, min(99, (int) $data['pasajeros'])) : null;

        if (($placaLeida !== null && !is_string($placaLeida))
            || ($confianza !== null && !is_numeric($confianza))
            || !in_array($categoria, ['moto', 'auto', 'minibus', 'camion', 'bus'], true)
            || $ejes < 1
            || $ejes > 255) {
            return response()->json(['error' => 'La respuesta de inferencia tiene datos inválidos'], 502);
        }

        $placaNorm = $placaLeida ? Vehiculo::normalizar($placaLeida) : null;

        // Diagnóstico de lo que devuelve la inferencia para cada frame de la app.
        logger()->channel('single')->info('frame', [
            'placa' => $placaLeida, 'conf' => $confianza, 'cat' => $categoria, 'ejes' => $ejes,
            'bbox_placa' => $data['placa_detalle']['bbox'] ?? null,
            'img' => @getimagesize($file->getRealPath())[0] . 'x' . @getimagesize($file->getRealPath())[1],
            'ms' => $data['latencia_ms'] ?? null,
        ]);

        // Si el texto contiene una placa boliviana (3–4 dígitos + 3 letras) se usa solo esa parte:
        // el OCR suele leer también "BOLIVIA" del marco (p. ej. "BOLIVIA6811YPF" → 6811YPF).
        // El patrón valida la lectura, así que basta una confianza baja. Sin patrón, se exige más.
        $placaBoliviana = $placaLeida ? Vehiculo::extraerPlacaBoliviana($placaLeida) : null;
        $conf = $confianza !== null ? (float) $confianza : 0.0;
        if ($placaBoliviana !== null && $conf >= self::PLACA_MIN_CONFIANZA_PATRON) {
            $placaNorm = $placaBoliviana;
        } elseif ($placaNorm !== null
            && (strlen($placaNorm) < self::PLACA_MIN_CARACTERES || $conf < self::PLACA_MIN_CONFIANZA)) {
            // Lecturas basura del OCR en video no generan pasos ni alertas.
            $placaNorm = null;
        }
        $estadoFrameKey = "caseta_frame_activo_{$casetaId}";
        $estadoFrame = Cache::get($estadoFrameKey);

        if (!$placaNorm) {
            if (is_array($estadoFrame) && !empty($estadoFrame['placa'])) {
                $faltas = ($estadoFrame['faltas'] ?? 0) + 1;
                if ($faltas >= self::FRAME_MISSES_TO_RELEASE) {
                    Cache::forget($estadoFrameKey);
                } else {
                    $estadoFrame['faltas'] = $faltas;
                    Cache::put(
                        $estadoFrameKey,
                        $estadoFrame,
                        now()->addMinutes(self::FRAME_PRESENCE_TTL_MINUTES)
                    );
                }
            }

            return response()->json([
                'estado'  => 'sin_placa',
                'mensaje' => 'No se detectó una placa en este frame.',
            ]);
        }

        // 2. Buscar vehículo
        $vehiculo = Vehiculo::where('placa', $placaNorm)->first();

        // Mantener un solo cobro mientras la misma placa (o una lectura casi igual) siga presente en la caseta.
        if (is_array($estadoFrame) && !empty($estadoFrame['placa'])
            && levenshtein($estadoFrame['placa'], $placaNorm) <= self::PLACA_MAX_DIFERENCIA) {
            $pasoReciente = Paso::with(['caseta', 'usuario', 'alerta'])
                ->find($estadoFrame['paso_id'] ?? null);
            if ($pasoReciente) {
                $estadoFrame['faltas'] = 0;
                Cache::put(
                    $estadoFrameKey,
                    $estadoFrame,
                    now()->addMinutes(self::FRAME_PRESENCE_TTL_MINUTES)
                );
                return response()->json([
                    'paso'      => $pasoReciente,
                    'vehiculo'  => $vehiculo,
                    'tarifa'    => $pasoReciente->monto,
                    'duplicado' => true,
                    'cumple_filtro' => FiltroPlaca::cumple($placaNorm),
                ]);
            }
        }

        // 3. Calcular tarifa y estado
        $monto  = Tarifa::montoPara($categoria, $ejes);
        $estado = 'ok';

        if (!$vehiculo) {
            $estado = 'no_registrado';
        } elseif ($vehiculo->saldo < $monto) {
            $estado = 'sin_saldo';
        }

        // 4. Guardar imagen de evidencia
        $imagenPath = $file->store('evidencias', 'public');
        $imagenUrl  = Storage::url($imagenPath);

        // 5. Registrar paso
        $paso = Paso::create([
            'placa_leida'         => $placaLeida,
            'placa_normalizada'   => $placaNorm,
            'confianza_ocr'       => $confianza,
            'categoria_detectada' => $categoria,
            'ejes_detectados'     => $ejes,
            'pasajeros'           => $pasajeros,
            'monto'               => $monto,
            'caseta_id'           => $casetaId,
            'usuario_id'          => $usuario->id,
            'imagen_url'          => $imagenUrl,
            'estado'              => $estado,
            'inferencia_raw'      => $data,
        ]);

        // 6. Descontar saldo si corresponde
        if ($vehiculo && $estado === 'ok') {
            $vehiculo->decrement('saldo', $monto);
        }

        Cache::put(
            $estadoFrameKey,
            ['placa' => $placaNorm, 'paso_id' => $paso->id, 'faltas' => 0],
            now()->addMinutes(self::FRAME_PRESENCE_TTL_MINUTES)
        );

        // 7. Crear alerta si es necesario
        if (in_array($estado, ['no_registrado', 'sin_saldo'])) {
            Alerta::create([
                'paso_id' => $paso->id,
                'tipo'    => $estado === 'no_registrado' ? 'no_registrado' : 'sin_saldo',
                'atendida' => false,
            ]);
        }

        // 8. Filtro de placas del estudiante (números 2…2 y letras con M): se guarda aparte
        $placaFiltrada = PlacaFiltrada::registrarSiCumple($placaNorm, [
            'confianza_ocr' => $confianza,
            'origen'        => 'caseta',
            'paso_id'       => $paso->id,
            'caseta_id'     => $casetaId,
            'usuario_id'    => $usuario->id,
            'imagen_url'    => $imagenUrl,
        ]);

        // 9. Escribir evento SSE al archivo de stream
        $this->emitirSSE($paso->load(['caseta', 'alerta']), $placaFiltrada !== null);

        $paso->load(['caseta', 'usuario', 'alerta']);

        return response()->json([
            'paso'          => $paso,
            'vehiculo'      => $vehiculo,
            'tarifa'        => $monto,
            'cumple_filtro' => $placaFiltrada !== null,
        ], 201);
    }

    /** Historial de pasos de una placa */
    public function historial(string $placa)
    {
        $norm = Vehiculo::normalizar($placa);
        $pasos = Paso::with(['caseta'])
            ->where('placa_normalizada', $norm)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($pasos);
    }

    /** Panel web: listado de pasos recientes */
    public function index()
    {
        $pasos = Paso::with(['caseta','usuario','alerta'])
            ->orderByDesc('id')
            ->paginate(30);

        return view('pasos.index', compact('pasos'));
    }

    // ─── SSE helpers ─────────────────────────────────────────────────────────

    /**
     * Escribe el evento en un archivo que el endpoint SSE lee.
     * Cada línea es un JSON con los datos del paso.
     */
    private function emitirSSE(Paso $paso, bool $cumpleFiltro = false): void
    {
        $evento = json_encode([
            'cumple_filtro' => $cumpleFiltro,
            'overlay'       => $paso->overlay,
            'id'          => $paso->id,
            'placa'       => $paso->placa_normalizada ?? $paso->placa_leida,
            'categoria'   => $paso->categoria_detectada,
            'ejes'        => $paso->ejes_detectados,
            'pasajeros'   => $paso->pasajeros,
            'monto'       => $paso->monto,
            'estado'      => $paso->estado,
            'caseta'      => $paso->caseta?->nombre,
            'imagen_url'  => $paso->imagen_url,
            'hora'        => $paso->created_at->format('H:i:s'),
            'tiene_alerta'=> $paso->alerta !== null,
        ]);

        $archivoSse = storage_path('app/sse_eventos.jsonl');
        file_put_contents($archivoSse, $evento . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * GET /pasos/stream — Server-Sent Events (panel en tiempo real)
     * Optimizado para alta concurrencia y respuesta inmediata (< 30ms) sin bloquear PHP.
     */
    public function stream(Request $request)
    {
        // 1. Cerrar y liberar la sesión INMEDIATAMENTE para evitar lock de archivos
        // y permitir que el usuario navegue o refresque sin esperar.
        if (session()->isStarted()) {
            session()->save();
        }
        if (function_exists('session_write_close')) {
            session_write_close();
        }

        $archivoSse = storage_path('app/sse_eventos.jsonl');

        // Reanudar desde el último byte entregado (Last-Event-ID lo reenvía el navegador al reconectar).
        // En la primera conexión se empieza al final del archivo: solo se emiten eventos nuevos,
        // así las reconexiones no repiten pasos antiguos en el panel.
        clearstatcache(true, $archivoSse);
        $tamanoActual = file_exists($archivoSse) ? filesize($archivoSse) : 0;
        $ultimoId = $request->header('Last-Event-ID');
        $posicionInicial = (is_numeric($ultimoId) && (int) $ultimoId >= 0 && (int) $ultimoId <= $tamanoActual)
            ? (int) $ultimoId
            : $tamanoActual;

        return response()->stream(function () use ($archivoSse, $posicionInicial) {
            // Indicar al navegador el intervalo de reconexión automática (2 segundos)
            echo "retry: 2000\n\n";
            if (ob_get_level() > 0) { ob_flush(); }
            flush();

            // En local limitamos los ciclos a 2 iteraciones (aprox 1 segundo)
            // para liberar inmediatamente el worker de PHP y atender otras peticiones sin demora.
            // En producción con PHP-FPM o Nginx puede extenderse.
            $maxCiclos = app()->isProduction() ? 30 : 2;
            $ciclo = 0;
            $posicion = $posicionInicial;

            while ($ciclo < $maxCiclos) {
                if (connection_aborted()) {
                    break;
                }

                clearstatcache(true, $archivoSse);
                if (file_exists($archivoSse) && filesize($archivoSse) > $posicion) {
                    $nuevas = file_get_contents($archivoSse, false, null, $posicion);
                    // Solo líneas completas; una línea a medio escribir se lee en el siguiente ciclo.
                    $fin = strrpos($nuevas, "\n");
                    if ($fin !== false) {
                        $bloque = substr($nuevas, 0, $fin + 1);
                        $posicion += strlen($bloque);
                        foreach (explode("\n", trim($bloque)) as $linea) {
                            if ($linea !== '') {
                                echo "id: {$posicion}\ndata: {$linea}\n\n";
                            }
                        }
                        if (ob_get_level() > 0) { ob_flush(); }
                        flush();
                    }
                }

                $ciclo++;
                if ($ciclo < $maxCiclos) {
                    usleep(500000); // 0.5s
                }
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-transform',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Nginx: desactivar buffer
        ]);
    }
}
