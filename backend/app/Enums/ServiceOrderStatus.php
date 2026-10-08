<?php

namespace App\Enums;

enum ServiceOrderStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InProgress => 'Dikerjakan',
            self::Finished => 'Selesai',
            self::Paid => 'Dibayar',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Boleh diubah isinya (item jasa/part) hanya pada dua status ini. */
    public function bolehDiubah(): bool
    {
        return in_array($this, [self::Draft, self::InProgress], true);
    }

    public function sudahKeluarStok(): bool
    {
        return in_array($this, [self::Finished, self::Paid], true);
    }
}
