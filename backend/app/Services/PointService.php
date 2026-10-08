<?php

namespace App\Services;

use App\Enums\PointTransactionType;
use App\Exceptions\AturanBisnisException;
use App\Models\Membership;
use App\Models\PointTransaction;

/**
 * Semua perubahan saldo poin WAJIB lewat ledger `point_transactions`.
 * Saldo di `memberships.points_balance` hanya salinan cepat untuk tampilan.
 */
class PointService
{
    public function tambah(Membership $membership, int $poin, array $atribut = []): ?PointTransaction
    {
        if ($poin <= 0) {
            return null;
        }

        return $this->catat($membership, PointTransactionType::Earn, $poin, $atribut);
    }

    public function pakai(Membership $membership, int $poin, array $atribut = []): PointTransaction
    {
        if ($poin <= 0) {
            throw new AturanBisnisException('Jumlah poin yang ditukar harus lebih dari nol.');
        }

        if ($membership->points_balance < $poin) {
            throw new AturanBisnisException(
                "Saldo poin tidak cukup. Saldo saat ini {$membership->points_balance} poin, dibutuhkan {$poin} poin."
            );
        }

        return $this->catat($membership, PointTransactionType::Redeem, -$poin, $atribut);
    }

    /** Sesuaikan saldo (koreksi manual admin). */
    public function sesuaikan(Membership $membership, int $selisih, array $atribut = []): PointTransaction
    {
        if ($selisih === 0) {
            throw new AturanBisnisException('Nilai penyesuaian poin tidak boleh nol.');
        }

        if ($selisih < 0 && $membership->points_balance + $selisih < 0) {
            throw new AturanBisnisException('Penyesuaian membuat saldo poin menjadi negatif.');
        }

        return $this->catat($membership, PointTransactionType::Adjust, $selisih, $atribut);
    }

    /** Hanguskan seluruh poin (dipakai saat membership kedaluwarsa). */
    public function hanguskan(Membership $membership, array $atribut = []): ?PointTransaction
    {
        if ($membership->points_balance <= 0) {
            return null;
        }

        return $this->catat($membership, PointTransactionType::Expire, -$membership->points_balance, [
            'description' => $atribut['description'] ?? 'Poin hangus karena masa membership berakhir',
        ] + $atribut);
    }

    private function catat(Membership $membership, PointTransactionType $tipe, int $poin, array $atribut = []): PointTransaction
    {
        // Kunci baris membership agar dua permintaan bersamaan tidak menghitung saldo yang sama.
        $membership->refresh();
        $saldoBaru = $membership->points_balance + $poin;

        if ($saldoBaru < 0) {
            throw new AturanBisnisException('Saldo poin tidak boleh negatif.');
        }

        $trx = PointTransaction::create([
            'customer_id' => $membership->customer_id,
            'membership_id' => $membership->id,
            'type' => $tipe->value,
            'points' => $poin,
            'balance_after' => $saldoBaru,
            'service_order_id' => $atribut['service_order_id'] ?? null,
            'reward_id' => $atribut['reward_id'] ?? null,
            'description' => $atribut['description'] ?? $tipe->label(),
        ]);

        $membership->update(['points_balance' => $saldoBaru]);

        return $trx;
    }
}
