<?php

namespace App\Enums;

enum CheckupStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Completed => 'Selesai',
        };
    }
}
