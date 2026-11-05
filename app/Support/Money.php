<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Display formatting for monetary amounts. Amounts are stored as integer
 * cents (fees, totals) or exact decimal strings (savings, raw fees).
 */
class Money
{
    public static function cents(int $cents, bool $signed = false): string
    {
        $sign = $cents < 0 ? '-' : ($signed && $cents > 0 ? '+' : '');

        return $sign.'$'.number_format(abs($cents) / 100, 2);
    }

    /**
     * Format an exact decimal string, showing cents plus any extra precision
     * the value actually carries (so $69.375 is shown as such).
     */
    public static function decimal(string|BigDecimal $amount, int $maxScale = 6): string
    {
        $value = BigDecimal::of($amount)->toScale($maxScale, RoundingMode::HalfUp)->strippedOfTrailingZeros();
        $scale = max(2, $value->getScale());
        $value = $value->toScale($scale);

        $sign = $value->isNegative() ? '-' : '';
        [$whole, $fraction] = explode('.', (string) $value->abs());

        return $sign.'$'.number_format((float) $whole).'.'.$fraction;
    }
}
