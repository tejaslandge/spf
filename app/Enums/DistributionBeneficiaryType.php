<?php

namespace App\Enums;

enum DistributionBeneficiaryType: string
{
    case Investor = 'investor';
    case Admin = 'admin';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Investor => 'Investor',
            self::Admin => 'Admin',
            self::Company => 'Company',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Investor => 'blue',
            self::Admin => 'purple',
            self::Company => 'emerald',
        };
    }
}
