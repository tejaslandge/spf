<?php

namespace App\Enums;

enum LoanFundingStatus: string
{
    case Unfunded = 'unfunded';
    case PartiallyFunded = 'partially_funded';
    case FullyFunded = 'fully_funded';

    public function label(): string
    {
        return match ($this) {
            self::Unfunded => 'Awaiting funding',
            self::PartiallyFunded => 'Partially funded',
            self::FullyFunded => 'Fully funded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unfunded => 'amber',
            self::PartiallyFunded => 'blue',
            self::FullyFunded => 'emerald',
        };
    }
}
