<?php

namespace App\Enums;

enum FinancialCategory: string
{
    case PendapatanJasa = 'pendapatan_jasa';
    case PenjualanSparepart = 'penjualan_sparepart';
    case PembelianSparepart = 'pembelian_sparepart';
    case Operasional = 'operasional';
    case PromosiPoin = 'promosi_poin';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::PendapatanJasa => 'Pendapatan Jasa',
            self::PenjualanSparepart => 'Penjualan Sparepart',
            self::PembelianSparepart => 'Pembelian Sparepart',
            self::Operasional => 'Operasional',
            self::PromosiPoin => 'Promosi Poin',
            self::Lainnya => 'Lainnya',
        };
    }

    /** Kategori yang dihitung sebagai pengeluaran (untuk validasi input manual). */
    public static function untukPengeluaranManual(): array
    {
        return [self::Operasional->value, self::PembelianSparepart->value, self::Lainnya->value];
    }
}
