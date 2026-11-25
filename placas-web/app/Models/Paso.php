<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paso extends Model
{
    protected $fillable = [
        'placa_leida','placa_normalizada','confianza_ocr',
        'categoria_detectada','ejes_detectados','pasajeros','monto',
        'caseta_id','usuario_id','imagen_url','estado','inferencia_raw',
    ];

    protected $casts = [
        'inferencia_raw' => 'array',
        'monto' => 'decimal:2',
    ];

    /**
     * Cajas de la inferencia para dibujar sobre la imagen de evidencia (mismas coordenadas que el frame):
     * vehículo (M2), placa (M1), pasajeros (segmentación de personas) y llantas (keypoints M3).
     */
    public function getOverlayAttribute(): array
    {
        $raw = is_array($this->inferencia_raw) ? $this->inferencia_raw : [];
        $det = $raw['detalles'] ?? [];
        $caja = fn ($b) => is_array($b) && count($b) === 4 ? array_map(fn ($v) => round((float) $v, 1), array_values($b)) : null;

        return array_filter([
            'vehiculo' => $caja($det['m2']['box'] ?? null),
            'categoria' => $det['m2']['categoria'] ?? ($raw['categoria'] ?? null),
            'placa' => $caja($raw['placa_detalle']['bbox'] ?? ($det['m1']['box'] ?? null)),
            'placa_texto' => $this->placa_normalizada ?? $this->placa_leida,
            'personas' => array_values(array_filter(array_map($caja, $det['pasajeros']['cajas'] ?? []))),
            'llantas' => array_values(array_filter(
                $raw['keypoints'] ?? ($det['m3']['keypoints'] ?? []),
                fn ($k) => is_array($k) && count($k) >= 2
            )),
        ], fn ($v) => $v !== null && $v !== []);
    }

    public function caseta()
    {
        return $this->belongsTo(Caseta::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function alerta()
    {
        return $this->hasOne(Alerta::class);
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'placa_normalizada', 'placa');
    }
}
