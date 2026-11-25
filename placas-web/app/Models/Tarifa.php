<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tarifa extends Model
{
    protected $fillable = ['categoria','ejes','monto','activa'];
    protected $casts = ['activa' => 'boolean', 'monto' => 'decimal:2'];

    public static function montoPara(string $categoria, int $ejes): float
    {
        return (float) (self::where('categoria', $categoria)
            ->where('ejes', $ejes)
            ->where('activa', true)
            ->value('monto') ?? 0);
    }
}
