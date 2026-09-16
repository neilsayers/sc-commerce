<?php

namespace SCCommerce\Support;

/**
 * Amounts are stored as floats in pounds/dollars (not integer minor
 * units) throughout this plugin — simpler for a v1 with a single
 * currency per site and no split-tender/multi-currency needs. If that
 * ever changes, this is the one place formatting/rounding happens.
 */
final class Money
{
    private const SYMBOLS = [
        'GBP' => '£',
        'USD' => '$',
        'EUR' => '€',
    ];

    public static function format(float $amount, string $currency): string
    {
        $symbol = self::SYMBOLS[$currency] ?? $currency.' ';

        return $symbol.\number_format($amount, 2);
    }

    public static function round(float $amount): float
    {
        return \round($amount, 2);
    }
}
