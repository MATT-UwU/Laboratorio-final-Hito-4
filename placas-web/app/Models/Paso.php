<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paso extends Model
{
    protected $fillable = [
        'placa_leida','placa_normalizada','confianza_ocr',
        'categoria_detectada','ejes_detectados','monto',
        'caseta_id','usuario_id','imagen_url','estado','inferencia_raw',
    ];

    protected $casts = [
        'inferencia_raw' => 'array',
        'monto' => 'decimal:2',
    ];

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
