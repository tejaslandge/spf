<?php

namespace App\Support;

use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Regional presentation helpers driven by the General settings screen
 * (timezone, date/time formats and the financial year boundary).
 */
final class Regional
{
    public static function dateFormat(): string
    {
        return (string) self::setting('company.date_format', 'd M Y');
    }

    public static function timeFormat(): string
    {
        return (string) self::setting('company.time_format', 'H:i');
    }

    public static function timezone(): string
    {
        return (string) self::setting('company.timezone', 'Asia/Kolkata');
    }

    public static function financialYearStartMonth(): int
    {
        return max(1, min(12, (int) self::setting('company.financial_year_start_month', 4)));
    }

    public static function formatDate(\DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        return CarbonImmutable::parse($date)->format(self::dateFormat());
    }

    public static function formatDateTime(\DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        return CarbonImmutable::parse($date)->format(self::dateFormat().' '.self::timeFormat());
    }

    /**
     * Start of the current financial year according to the configured
     * financial year start month.
     */
    public static function financialYearStart(?CarbonImmutable $asOf = null): CarbonImmutable
    {
        $asOf ??= CarbonImmutable::today();
        $start = $asOf->setMonth(self::financialYearStartMonth())->startOfMonth();

        return $start->greaterThan($asOf) ? $start->subYear() : $start;
    }

    /**
     * Resolve a user date-filter preference to a concrete start date.
     */
    public static function filterStartDate(string $filter, ?CarbonImmutable $asOf = null): CarbonImmutable
    {
        $asOf ??= CarbonImmutable::today();

        return match ($filter) {
            'this_week' => $asOf->startOfWeek(),
            'this_month' => $asOf->startOfMonth(),
            'this_financial_year' => self::financialYearStart($asOf),
            default => $asOf,
        };
    }

    private static function setting(string $key, mixed $default): mixed
    {
        try {
            return app(SettingsService::class)->get($key, $default) ?? $default;
        } catch (Throwable) {
            return $default;
        }
    }
}
