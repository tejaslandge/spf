<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Paid => 'Paid',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'purple',
            self::Paid => 'emerald',
            self::Rejected => 'rose',
            self::Cancelled => 'zinc',
        };
    }

    public function canBeReviewed(): bool
    {
        return $this === self::Pending;
    }

    public function canBeProcessed(): bool
    {
        return $this === self::Approved;
    }
}
