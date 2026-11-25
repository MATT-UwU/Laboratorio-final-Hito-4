<?php

namespace App\Support;

/**
 * Filtro de placas asignado al estudiante (lista del docente, fila 12):
 * la parte numérica empieza por 2 y termina en 2, y la parte alfabética contiene una M.
 *
 * Ejemplos que cumplen: 2M12 (números 212, letras M), 2IM62 (262, IM), 2345MBC... no (termina en 5).
 */
class FiltroPlaca
{
    public const EMPIEZA_POR = '2';
    public const TERMINA_EN = '2';
    public const CONTIENE_LETRA = 'M';

    public static function descripcion(): string
    {
        return 'Números empiezan por ' . self::EMPIEZA_POR . ' y terminan en ' . self::TERMINA_EN
            . '; letras contienen ' . self::CONTIENE_LETRA;
    }

    /** Separa la placa normalizada en su parte numérica y su parte alfabética. */
    public static function partes(string $placa): array
    {
        $placa = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa));

        return [
            'numeros' => preg_replace('/[^0-9]/', '', $placa),
            'letras' => preg_replace('/[^A-Z]/', '', $placa),
        ];
    }

    public static function cumple(?string $placa): bool
    {
        if (!$placa) {
            return false;
        }

        ['numeros' => $numeros, 'letras' => $letras] = self::partes($placa);

        return $numeros !== ''
            && str_starts_with($numeros, self::EMPIEZA_POR)
            && str_ends_with($numeros, self::TERMINA_EN)
            && str_contains($letras, self::CONTIENE_LETRA);
    }
}
