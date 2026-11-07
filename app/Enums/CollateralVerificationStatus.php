<?php

namespace App\Enums;

enum CollateralVerificationStatus: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Unverified',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unverified => 'amber',
            self::Verified => 'emerald',
            self::Rejected => 'rose',
        };
    }
}
