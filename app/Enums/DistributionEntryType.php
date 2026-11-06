<?php

namespace App\Enums;

enum DistributionEntryType: string
{
    case Earning = 'earning';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Earning => 'Earning',
            self::Reversal => 'Reversal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Earning => 'emerald',
            self::Reversal => 'rose',
        };
    }
}
