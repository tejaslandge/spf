<?php

namespace App\Enums;

enum MessageChannel: string
{
    case Whatsapp = 'whatsapp';
    case Sms = 'sms';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Sms => 'SMS',
            self::Email => 'Email',
        };
    }
}
