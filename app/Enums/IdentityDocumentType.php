<?php

namespace App\Enums;

enum IdentityDocumentType: string
{
    case Aadhaar = 'aadhaar';
    case Pan = 'pan';
    case Passport = 'passport';
    case VoterId = 'voter_id';
    case DrivingLicense = 'driving_license';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Aadhaar => 'Aadhaar',
            self::Pan => 'PAN',
            self::Passport => 'Passport',
            self::VoterId => 'Voter ID',
            self::DrivingLicense => 'Driving licence',
            self::Other => 'Other',
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
