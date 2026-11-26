<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PoseController extends Controller
{
    private const KEYPOINTS_COCO = [
        'nose',
        'left_eye',
        'right_eye',
        'left_ear',
        'right_ear',
        'left_shoulder',
        'right_shoulder',
        'left_elbow',
        'right_elbow',
        'left_wrist',
        'right_wrist',
        'left_hip',
        'right_hip',
        'left_knee',
        'right_knee',
        'left_ankle',
        'right_ankle',
    ];

    public function analizar(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        $url = config('services.pose_colab.url', '');
        $token = config('services.pose_colab.token', '');
        if (empty($url) || empty($token)) {
            return response()->json([
                'error' => 'POSE_INFERENCE_URL y POSE_INFERENCE_API_TOKEN deben estar configurados.',
            ], 503);
        }

        $file = $request->file('file');
        $image = file_get_contents($file->getRealPath());
        if ($image === false) {
            return response()->json(['error' => 'No se pudo leer la imagen enviada.'], 500);
        }

        try {
            $upstream = Http::withHeaders([
                'X-API-Token' => $token,
                'Accept' => 'application/json',
            ])
                ->connectTimeout(5)
                ->timeout(45)
                ->withBody(
                    $image,
                    $file->getMimeType() ?: 'application/octet-stream'
                )
                ->post(rtrim($url, '/').'/predict');
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'No se pudo contactar el servicio de pose de Colab.'], 503);
        }

        if (! $upstream->successful()) {
            logger()->warning('El servicio de pose de Colab devolvió un error.', [
                'status' => $upstream->status(),
            ]);

            return response()->json(['error' => 'El servicio de pose rechazó la imagen.'], 502);
        }

        $data = $upstream->json();
        if (! is_array($data) || ! isset($data['personas']) || ! is_array($data['personas'])) {
            logger()->warning('El servicio de pose devolvió una respuesta incompatible.');

            return response()->json(['error' => 'La respuesta del servicio de pose tiene un formato inválido.'], 502);
        }

        $personas = [];
        foreach (array_values($data['personas']) as $index => $persona) {
            $procesada = $this->procesarPersona($persona, $index);
            if ($procesada === null) {
                logger()->warning('El servicio de pose devolvió una persona con datos inválidos.');

                return response()->json(['error' => 'La respuesta del servicio de pose contiene keypoints inválidos.'], 502);
            }
            $personas[] = $procesada;
        }

        $cantidad = count($personas);

        return response()->json([
            'estado' => $cantidad > 0 ? 'personas_detectadas' : 'sin_personas',
            'mensaje' => $cantidad > 0
                ? "Se detectaron {$cantidad} persona(s). El resultado muestra partes del cuerpo visibles; no determina quién conduce."
                : 'No se detectaron personas en la imagen.',
            'total_personas' => $cantidad,
            'umbral_parte_visible' => 0.5,
            'personas' => $personas,
            'latencia_ms' => is_numeric($data['inferencia_ms'] ?? null)
                ? (float) $data['inferencia_ms']
                : null,
        ]);
    }

    private function procesarPersona(mixed $persona, int $index): ?array
    {
        if (! is_array($persona)
            || ! is_numeric($persona['score'] ?? null)
            || ! $this->esVectorNumerico($persona['caja'] ?? null, 4)
            || ! is_array($persona['keypoints'] ?? null)
            || count($persona['keypoints']) !== count(self::KEYPOINTS_COCO)) {
            return null;
        }

        $keypoints = [];
        $partesVisibles = [];

        foreach (self::KEYPOINTS_COCO as $i => $nombre) {
            $punto = $persona['keypoints'][$i] ?? null;
            if (! $this->esVectorNumerico($punto, 3)) {
                return null;
            }

            [$x, $y, $confianza] = array_map('floatval', array_values($punto));
            $keypoints[$nombre] = [
                'x' => $x,
                'y' => $y,
                'confianza' => $confianza,
            ];
            if ($confianza >= 0.5) {
                $partesVisibles[] = $nombre;
            }
        }

        return [
            'id' => $index + 1,
            'confianza_persona' => (float) $persona['score'],
            'caja' => array_map('floatval', array_values($persona['caja'])),
            'partes_visibles' => $partesVisibles,
            'total_partes_visibles' => count($partesVisibles),
            'keypoints' => $keypoints,
        ];
    }

    private function esVectorNumerico(mixed $value, int $longitud): bool
    {
        if (! is_array($value) || count($value) !== $longitud) {
            return false;
        }

        foreach ($value as $elemento) {
            if (! is_numeric($elemento) || ! is_finite((float) $elemento)) {
                return false;
            }
        }

        return true;
    }
}
