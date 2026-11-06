<?php

namespace App\Enums;

enum AllocationStatus: string
{
    case Active = 'active';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Released => 'Released',
        };
    }
}
