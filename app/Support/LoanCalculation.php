<?php

namespace App\Support;

use App\Enums\InterestMethod;

final class LoanCalculation
{
    /**
     * @param  array<int, LoanInstallment>  $installments
     */
    public function __construct(
        public readonly InterestMethod $method,
        public readonly string $principal,
        public readonly string $annualRate,
        public readonly int $tenureMonths,
        public readonly string $emiAmount,
        public readonly string $totalInterest,
        public readonly string $totalPayable,
        public readonly string $totalPrincipal,
        public readonly array $installments,
    ) {}
}
