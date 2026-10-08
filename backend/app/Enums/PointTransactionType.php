<?php

namespace App\Enums;

enum PointTransactionType: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case Expire = 'expire';
    case Adjust = 'adjust';

    public function label(): string
    {
        return match ($this) {
            self::Earn => 'Poin Masuk',
            self::Redeem => 'Tukar Poin',
            self::Expire => 'Poin Hangus',
            self::Adjust => 'Penyesuaian',
        };
    }
}
