<?php

namespace App\Enums;

enum CheckupResult: string
{
    case CheckupOnly = 'checkup_only';
    case ContinueService = 'continue_service';

    public function label(): string
    {
        return match ($this) {
            self::CheckupOnly => 'Hanya Check Up',
            self::ContinueService => 'Lanjut Service',
        };
    }
}
