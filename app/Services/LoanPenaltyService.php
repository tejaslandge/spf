<?php

namespace App\Services;

use App\Models\EmiSchedule;
use App\Services\Settings\SettingsService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calculates the late-payment penalty for an overdue EMI schedule using the
 * configured loan penalty settings. The result is a suggestion that the
 * collection desk can adjust before posting.
 */
final class LoanPenaltyService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function isEnabled(): bool
    {
        return $this->settings->getBool('loan.penalty_enabled', false);
    }

    public function method(): string
    {
        return (string) $this->settings->get('loan.penalty_method', 'per_day_percent');
    }

    public function penaltyFor(EmiSchedule $schedule, ?CarbonInterface $asOf = null): string
    {
        if (! $this->isEnabled()) {
            return '0.00';
        }

        $outstanding = Money::sub((string) $schedule->total_amount, (string) $schedule->paid_amount);

        if (Money::compare($outstanding, '0') <= 0) {
            return '0.00';
        }

        $asOf ??= now();
        $graceDays = max(0, $this->settings->getInt('loan.penalty_grace_days', 0));
        $dueDate = CarbonImmutable::parse($schedule->due_date)->addDays($graceDays);

        if ($asOf->lessThanOrEqualTo($dueDate)) {
            return '0.00';
        }

        $rate = $this->settings->getDecimal('loan.penalty_rate', '0');
        $overdueDays = (string) max(1, (int) $dueDate->startOfDay()->diffInDays($asOf->startOfDay()));

        $penalty = match ($this->method()) {
            'flat_percent' => Money::div(Money::mul($outstanding, $rate), '100'),
            default => Money::div(Money::mul(Money::mul($outstanding, $rate), $overdueDays), '100'),
        };

        $penalty = Money::round($penalty);

        $maximum = $this->settings->get('loan.penalty_max_amount');

        if ($maximum !== null && $maximum !== '' && Money::compare($penalty, (string) $maximum) > 0) {
            $penalty = Money::round((string) $maximum);
        }

        return $penalty;
    }
}
