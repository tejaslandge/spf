<?php

namespace App\Enums;

enum InterestMethod: string
{
    case Flat = 'flat';
    case Reducing = 'reducing';

    public function label(): string
    {
        return match ($this) {
            self::Flat => 'Flat Interest',
            self::Reducing => 'Reducing Balance',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $method) => [$method->value => $method->label()])->all();
    }
}
