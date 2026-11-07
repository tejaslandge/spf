<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Closed = 'closed';
    case Foreclosed = 'foreclosed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Closed => 'Closed',
            self::Foreclosed => 'Foreclosed',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Active => 'blue',
            self::Closed => 'emerald',
            self::Foreclosed => 'rose',
            self::Rejected => 'rose',
        };
    }

    public function isRejected(): bool
    {
        return $this === self::Rejected;
    }

    public function canTransitionTo(self $status): bool
    {
        return match ($this) {
            self::Pending => $status === self::Active || $status === self::Rejected,
            self::Active => $status === self::Closed || $status === self::Foreclosed,
            self::Closed => false,
            self::Foreclosed => false,
            self::Rejected => false,
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
