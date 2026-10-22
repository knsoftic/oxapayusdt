<?php

namespace App\Support;

/**
 * USD amounts are calculated in integer cents to avoid floating point drift.
 */
final class Money
{
    public static function cents(int|float|string|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * "$1,234.50" style label for the UI.
     */
    public static function usd(int|float|string|null $amount): string
    {
        $cents = self::cents($amount);

        return ($cents < 0 ? '-' : '').'$'.number_format(abs($cents) / 100, 2);
    }
}
