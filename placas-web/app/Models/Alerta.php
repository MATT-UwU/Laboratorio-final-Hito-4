<?php

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
