<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Closed = 'closed';
    case Foreclosed = 'foreclosed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Closed => 'Closed',
            self::Foreclosed => 'Foreclosed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Active => 'blue',
            self::Closed => 'emerald',
            self::Foreclosed => 'rose',
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return match ($this) {
            self::Pending => $status === self::Active,
            self::Active => $status === self::Closed || $status === self::Foreclosed,
            self::Closed => false,
            self::Foreclosed => false,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status) => [$status->value => $status->label()])->all();
    }
}
