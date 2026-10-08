<?php

namespace App\Enums;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjust = 'adjust';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Barang Masuk',
            self::Out => 'Keluar (Service)',
            self::Adjust => 'Penyesuaian',
            self::Return => 'Pengembalian',
        };
    }
}
