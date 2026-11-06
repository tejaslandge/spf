<?php

namespace App\Enums;

enum DistributionStatus: string
{
    case Earned = 'earned';
    case Payable = 'payable';
    case Approved = 'approved';
    case Paid = 'paid';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Earned => 'Earned',
            self::Payable => 'Payable',
            self::Approved => 'Approved',
            self::Paid => 'Paid',
            self::Reversed => 'Reversed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Earned => 'blue',
            self::Payable => 'amber',
            self::Approved => 'purple',
            self::Paid => 'emerald',
            self::Reversed => 'rose',
        };
    }
}
