<?php

namespace App\Enums;

enum CommitmentStatus: string
{
    case Confirmed = 'confirmed';
    case Withdrawn = 'withdrawn';
    case Allocated = 'allocated';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::Withdrawn => 'Withdrawn',
            self::Allocated => 'Allocated',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Confirmed => 'blue',
            self::Withdrawn => 'zinc',
            self::Allocated => 'emerald',
        };
    }
}
