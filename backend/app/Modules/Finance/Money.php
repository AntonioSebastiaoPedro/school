<?php

namespace App\Modules\Finance;

use InvalidArgumentException;

/**
 * Valores em kwanzas guardados e calculados em cêntimos inteiros: nunca em vírgula flutuante (RN-025).
 * Na API os valores são texto decimal com 2 casas ("70000.00").
 */
final class Money
{
    public static function cents(string|int|float $amount): int
    {
        $text = is_float($amount) ? number_format($amount, 2, '.', '') : trim((string) $amount);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $text, $m)) {
            throw new InvalidArgumentException("Valor monetário inválido: {$text}");
        }

        $cents = ((int) $m[2]) * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$cents : $cents;
    }

    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }
}
