<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Caseta extends Model
{
    protected $fillable = ['nombre','ubicacion','activa'];
    protected $casts = ['activa' => 'boolean'];

    public function pasos()
    {
        return $this->hasMany(Paso::class);
    }

    public function resumenHoy(): array
    {
        $inicioHoy = today()->startOfDay();
        $query = $this->pasos()->where('created_at', '>=', $inicioHoy);

        return [
            'total_pasos'         => (clone $query)->count(),
            'recaudacion'         => (clone $query)->sum('monto') ?? 0,
            'alertas_sin_atender' => (clone $query)->whereIn('estado', ['alerta','no_registrado','sin_saldo'])->count(),
        ];
    }
}
