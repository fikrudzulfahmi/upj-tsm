<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Active = 'active';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Expired => 'Hangus',
        };
    }
}
