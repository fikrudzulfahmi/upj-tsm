<?php

namespace App\Enums;

enum UnitEntryType: string
{
    case CheckupOnly = 'checkup_only';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::CheckupOnly => 'Check Up',
            self::Service => 'Service',
        };
    }
}
