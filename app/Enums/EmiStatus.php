<?php

namespace App\Enums;

enum EmiStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Partial => 'Partially paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Waived => 'Waived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'zinc',
            self::Partial => 'amber',
            self::Paid => 'emerald',
            self::Overdue => 'rose',
            self::Waived => 'purple',
        };
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::Waived], true);
    }
}
