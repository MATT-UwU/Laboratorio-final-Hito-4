<?php

namespace App\Models;

use App\Support\FiltroPlaca;
use Illuminate\Database\Eloquent\Model;

class PlacaFiltrada extends Model
{
    protected $table = 'placas_filtradas';

    protected $fillable = [
        'placa', 'numeros', 'letras', 'confianza_ocr', 'origen',
        'paso_id', 'caseta_id', 'usuario_id', 'imagen_url',
    ];

    protected $casts = [
        'confianza_ocr' => 'decimal:3',
    ];

    public function paso()
    {
        return $this->belongsTo(Paso::class);
    }

    public function caseta()
    {
        return $this->belongsTo(Caseta::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** Guarda la placa si cumple el filtro; devuelve el registro o null. */
    public static function registrarSiCumple(?string $placa, array $datos = []): ?self
    {
        if (!FiltroPlaca::cumple($placa)) {
            return null;
        }

        return self::create(array_merge(FiltroPlaca::partes($placa), ['placa' => $placa], $datos));
    }
}
