<?php

namespace App\Services;

use Carbon\CarbonInterface;

final class ForeclosureCalculation
{
    public function __construct(
        public readonly string $outstanding_principal,
        public readonly string $accrued_interest,
        public readonly string $other_outstanding_dues,
        public readonly string $foreclosure_charges,
        public readonly string $eligible_adjustments,
        public readonly string $total_settlement,
        public readonly CarbonInterface $calculation_date,
        public readonly CarbonInterface $effective_date,
        public readonly bool $charges_enabled,
        public readonly string $charge_type,
        public readonly string $waived_interest = '0.00',
    ) {}
}
