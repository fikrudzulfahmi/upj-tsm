<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\AturanBisnisException;
use App\Models\Sparepart;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perubahan stok. Setiap perubahan menulis `stock_movements`
 * (ledger) + snapshot harga, di dalam transaksi dan dengan lockForUpdate().
 */
class StockService
{
    public function __construct(private readonly SettingService $setting) {}

    /** Barang masuk (pembelian). */
    public function increase(Sparepart $sparepart, int $qty, array $meta = []): StockMovement
    {
        if ($qty <= 0) {
            throw new AturanBisnisException('Jumlah barang masuk harus lebih dari nol.');
        }

        return $this->catat($sparepart, StockMovementType::In, $qty, $meta);
    }

    /** Barang keluar (dipakai pada SA selesai). Menolak bila stok tidak cukup. */
    public function decrease(Sparepart $sparepart, int $qty, array $meta = []): StockMovement
    {
        if ($qty <= 0) {
            throw new AturanBisnisException('Jumlah barang keluar harus lebih dari nol.');
        }

        return $this->catat($sparepart, StockMovementType::Out, $qty, $meta, kurangi: true);
    }

    /** Penyesuaian stok (stock opname) — wajib alasan, tidak menyentuh keuangan. */
    public function adjust(Sparepart $sparepart, int $stokBaru, string $alasan, array $meta = []): StockMovement
    {
        if ($stokBaru < 0) {
            throw new AturanBisnisException('Stok hasil penyesuaian tidak boleh negatif.');
        }

        if (trim($alasan) === '') {
            throw new AturanBisnisException('Alasan penyesuaian stok wajib diisi.');
        }

        return DB::transaction(function () use ($sparepart, $stokBaru, $alasan, $meta) {
            $baris = Sparepart::query()->whereKey($sparepart->id)->lockForUpdate()->firstOrFail();
            $sebelum = $baris->stock;
            $selisih = $stokBaru - $sebelum;

            if ($selisih === 0) {
                throw new AturanBisnisException('Stok baru sama dengan stok saat ini, tidak ada yang diubah.');
            }

            $baris->update(['stock' => $stokBaru]);

            return StockMovement::create([
                'sparepart_id' => $baris->id,
                'type' => StockMovementType::Adjust->value,
                'qty' => abs($selisih),
                'stock_before' => $sebelum,
                'stock_after' => $stokBaru,
                'buy_price' => $baris->buy_price,
                'sell_price' => $baris->sell_price,
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'notes' => $alasan.($selisih > 0 ? ' (tambah '.$selisih.')' : ' (kurang '.abs($selisih).')'),
                'created_by' => $meta['created_by'] ?? auth()->id(),
            ]);
        });
    }

    /** Pengembalian stok (pembatalan SA / retur). */
    public function returnStock(Sparepart $sparepart, int $qty, array $meta = []): StockMovement
    {
        if ($qty <= 0) {
            throw new AturanBisnisException('Jumlah pengembalian harus lebih dari nol.');
        }

        return $this->catat($sparepart, StockMovementType::Return, $qty, $meta);
    }

    private function catat(
        Sparepart $sparepart,
        StockMovementType $tipe,
        int $qty,
        array $meta = [],
        bool $kurangi = false,
    ): StockMovement {
        return DB::transaction(function () use ($sparepart, $tipe, $qty, $meta, $kurangi) {
            $baris = Sparepart::query()->whereKey($sparepart->id)->lockForUpdate()->firstOrFail();
            $sebelum = $baris->stock;
            $sesudah = $kurangi ? $sebelum - $qty : $sebelum + $qty;

            if ($sesudah < 0) {
                throw new AturanBisnisException(
                    "Stok {$baris->name} tidak cukup. Tersedia {$sebelum} {$baris->unit}, diminta {$qty}."
                );
            }

            $perubahan = ['stock' => $sesudah];

            // Harga beli master mengikuti pembelian terakhir (bisa dimatikan lewat setting).
            if ($tipe === StockMovementType::In
                && isset($meta['buy_price'])
                && $this->setting->bool('update_buy_price_on_purchase', true)) {
                $perubahan['buy_price'] = (int) $meta['buy_price'];
            }

            $baris->update($perubahan);

            return StockMovement::create([
                'sparepart_id' => $baris->id,
                'type' => $tipe->value,
                'qty' => $qty,
                'stock_before' => $sebelum,
                'stock_after' => $sesudah,
                'buy_price' => (int) ($meta['buy_price'] ?? $baris->buy_price),
                'sell_price' => (int) ($meta['sell_price'] ?? $baris->sell_price),
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'notes' => $meta['notes'] ?? $tipe->label(),
                'created_by' => $meta['created_by'] ?? auth()->id(),
            ]);
        });
    }
}
