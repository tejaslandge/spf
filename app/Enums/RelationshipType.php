<?php

namespace App\Enums;

enum RelationshipType: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Brother = 'brother';
    case Sister = 'sister';
    case Spouse = 'spouse';
    case Relative = 'relative';
    case Friend = 'friend';
    case BusinessPartner = 'business_partner';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Father',
            self::Mother => 'Mother',
            self::Brother => 'Brother',
            self::Sister => 'Sister',
            self::Spouse => 'Spouse',
            self::Relative => 'Relative',
            self::Friend => 'Friend',
            self::BusinessPartner => 'Business partner',
            self::Other => 'Other',
        };
    }

    public function requiresCustomLabel(): bool
    {
        return $this === self::Other;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $relationship) => [$relationship->value => $relationship->label()])->all();
    }
}
