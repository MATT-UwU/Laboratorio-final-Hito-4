<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alerta extends Model
{
    protected $fillable = ['paso_id','tipo','atendida'];
    protected $casts = ['atendida' => 'boolean'];

    public function paso()
    {
        return $this->belongsTo(Paso::class);
    }
}
