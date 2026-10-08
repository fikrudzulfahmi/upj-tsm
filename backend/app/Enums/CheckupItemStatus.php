<?php

namespace App\Enums;

enum CheckupItemStatus: string
{
    case Ok = 'ok';
    case PerluPerhatian = 'perlu_perhatian';
    case Rusak = 'rusak';
    case TidakDiperiksa = 'tidak_diperiksa';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::PerluPerhatian => 'Perlu Perhatian',
            self::Rusak => 'Rusak',
            self::TidakDiperiksa => 'Tidak Diperiksa',
        };
    }

    public static function nilaiValid(): array
    {
        return array_column(self::cases(), 'value');
    }
}
