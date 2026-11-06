<?php

namespace App\Enums;

enum KycDocumentType: string
{
    case Aadhaar = 'aadhaar';
    case Pan = 'pan';
    case ElectricityBill = 'electricity_bill';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Aadhaar => 'Aadhaar Card',
            self::Pan => 'PAN Card',
            self::ElectricityBill => 'Electricity Bill',
            self::Other => 'Other document',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
