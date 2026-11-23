<?php

namespace App\Billing;

use InvalidArgumentException;

/**
 * Money is stored and calculated as integer nanodollars (1 USD = 1,000,000,000 nanos).
 *
 * Pixelforge reports costs as exact 9-decimal strings, so nanodollars represent every amount it
 * can bill without rounding and without floating-point drift.
 */
final class Money
{
    public const NANOS_PER_DOLLAR = 1_000_000_000;

    /** Parse an exact decimal dollar string such as "0.046125000" or "12.50" into nanos. */
    public static function nanosFromDecimal(string $decimal): int
    {
        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,9}))?$/', trim($decimal), $m)) {
            throw new InvalidArgumentException("Not an exact dollar amount with at most 9 decimals: [{$decimal}]");
        }

        $nanos = (int) $m[2] * self::NANOS_PER_DOLLAR + (int) str_pad($m[3] ?? '', 9, '0');

        return $m[1] === '-' ? -$nanos : $nanos;
    }

    /** Format nanos as an exact 9-decimal string, the same shape Pixelforge uses ("0.046125000"). */
    public static function toDecimal(int $nanos): string
    {
        $sign = $nanos < 0 ? '-' : '';
        $abs = abs($nanos);

        return sprintf('%s%d.%09d', $sign, intdiv($abs, self::NANOS_PER_DOLLAR), $abs % self::NANOS_PER_DOLLAR);
    }

    /** Human format: "$0.046125", "$2.00". Exact (no rounding), trailing zeros trimmed to at least cents. */
    public static function format(int $nanos): string
    {
        $decimal = self::toDecimal($nanos);
        [$whole, $fraction] = explode('.', $decimal);
        $fraction = str_pad(rtrim($fraction, '0'), 2, '0');

        return str_starts_with($whole, '-') ? '-$'.substr($whole, 1).'.'.$fraction : '$'.$whole.'.'.$fraction;
    }

    /** Integer division that rounds up (towards positive infinity) for non-negative operands. */
    public static function ceilDiv(int $numerator, int $denominator): int
    {
        return intdiv($numerator, $denominator) + ($numerator % $denominator > 0 ? 1 : 0);
    }
}
