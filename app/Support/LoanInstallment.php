<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class LoanInstallment
{
    public function __construct(
        public readonly int $installmentNo,
        public readonly CarbonImmutable $dueDate,
        public readonly string $principal,
        public readonly string $interest,
        public readonly string $total,
        public readonly string $outstandingPrincipal,
    ) {}
}
