<?php

namespace App\Support;

use NumberFormatter;

final class Money
{
    public const SCALE = 2;

    public static function add(string|int|float ...$values): string
    {
        return array_reduce(
            $values,
            fn (string $carry, string|int|float $value): string => bcadd($carry, (string) $value, 10),
            '0',
        );
    }

    public static function sub(string|int|float $value, string|int|float ...$subtract): string
    {
        return array_reduce(
            $subtract,
            fn (string $carry, string|int|float $item): string => bcsub($carry, (string) $item, 10),
            (string) $value,
        );
    }

    public static function mul(string|int|float ...$values): string
    {
        return array_reduce(
            $values,
            fn (string $carry, string|int|float $value): string => bcmul($carry, (string) $value, 10),
            '1',
        );
    }

    public static function div(string|int|float $value, string|int|float $divisor): string
    {
        return bcdiv((string) $value, (string) $divisor, 10);
    }

    /**
     * Round half-up to the given scale (financial rounding).
     */
    public static function round(string|int|float $value, int $scale = self::SCALE): string
    {
        $increment = '0.'.str_repeat('0', max(0, $scale)).'5';

        return bcadd((string) $value, $increment, $scale);
    }

    public static function isZero(string|int|float $value): bool
    {
        return bccomp(self::round($value), '0', self::SCALE) === 0;
    }

    public static function compare(string|int|float $left, string|int|float $right): int
    {
        return bccomp((string) $left, (string) $right, self::SCALE);
    }

    public static function format(string|int|float|null $value, bool $withSymbol = true): string
    {
        $amount = (float) ($value ?? 0);

        $formatter = new NumberFormatter(config('finance.currency.locale', 'en_IN'), NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, 2);

        $formatted = $formatter->formatCurrency($amount, config('finance.currency.code', 'INR'));

        if (! $withSymbol) {
            return $formatter->format($amount);
        }

        return $formatted === false
            ? config('finance.currency.symbol', '₹').number_format($amount, 2)
            : $formatted;
    }
}
