<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehiculo extends Model
{
    protected $fillable = ['placa','propietario','categoria','ejes','saldo','activo'];

    protected $casts = ['activo' => 'boolean', 'saldo' => 'decimal:2'];

    public function pasos()
    {
        return $this->hasMany(Paso::class, 'placa_normalizada', 'placa');
    }

    /** Normaliza una placa: mayúsculas, sin espacios ni guiones */
    /**
     * Extrae una placa boliviana (3–4 dígitos + 3 letras, p. ej. 6811YPF) del texto del OCR,
     * que suele traer además "BOLIVIA" u otros textos del marco. Devuelve null si no hay patrón.
     */
    public static function extraerPlacaBoliviana(string $texto): ?string
    {
        $norm = self::normalizar($texto);
        if (preg_match_all('/(\d{3,4})([A-Z]{3})(?![A-Z])/', $norm, $m) && !empty($m[0])) {
            return end($m[0]);
        }

        return null;
    }

    public static function normalizar(string $texto): string
    {
        // Mayúsculas, sin acentos/diacríticos del OCR (Š→S) y solo letras y dígitos.
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($ascii === false) {
            $ascii = $texto;
        }

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $ascii));
    }
}
