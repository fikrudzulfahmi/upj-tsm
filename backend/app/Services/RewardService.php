<?php

namespace App\Services;

use App\Enums\FinancialCategory;
use App\Exceptions\AturanBisnisException;
use App\Models\Customer;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\ServiceOrder;
use Illuminate\Support\Facades\DB;

/**
 * Tukar poin (D7): poin berkurang, stok part reward keluar,
 * dan (opsional) biaya promosi dicatat sebesar HPP.
 */
class RewardService
{
    public function __construct(
        private readonly PointService $poin,
        private readonly StockService $stok,
        private readonly FinanceService $keuangan,
        private readonly SettingService $setting,
        private readonly DocumentNumberService $nomor,
    ) {}

    public function tukar(Customer $customer, Reward $reward, ?ServiceOrder $serviceOrder = null): RewardRedemption
    {
        return DB::transaction(function () use ($customer, $reward, $serviceOrder) {
            $membership = $customer->membership;

            if (! $membership || ! $membership->aktif()) {
                throw new AturanBisnisException('Hanya member aktif yang dapat menukar poin.');
            }

            if (! $reward->is_active) {
                throw new AturanBisnisException("Reward {$reward->name} sedang tidak aktif.");
            }

            if ($membership->points_balance < $reward->points_required) {
                throw new AturanBisnisException(
                    "Saldo poin tidak cukup. Tersedia {$membership->points_balance} poin, dibutuhkan {$reward->points_required} poin."
                );
            }

            $sparepart = $reward->sparepart;
            $qty = max(1, (int) $reward->qty);
            $hpp = $sparepart ? $sparepart->buy_price * $qty : 0;

            $redemption = RewardRedemption::create([
                'customer_id' => $customer->id,
                'reward_id' => $reward->id,
                'points_used' => $reward->points_required,
                'service_order_id' => $serviceOrder?->id,
                'sparepart_id' => $sparepart?->id,
                'qty' => $qty,
                'cost_amount' => $hpp,
                'redeemed_by' => auth()->id(),
                'redeemed_at' => now(),
            ]);

            // Stok part hadiah langsung keluar di sini (SIAP-nya tidak memotong ulang).
            if ($sparepart) {
                $this->stok->decrease($sparepart, $qty, [
                    'reference_type' => RewardRedemption::class,
                    'reference_id' => $redemption->id,
                    'notes' => "Hadiah tukar poin {$reward->name} oleh {$customer->name}",
                ]);
            }

            $this->poin->pakai($membership, $reward->points_required, [
                'reward_id' => $reward->id,
                'service_order_id' => $serviceOrder?->id,
                'description' => "Tukar poin: {$reward->name}",
            ]);

            if ($sparepart && $hpp > 0 && $this->setting->bool('record_redeem_cost', true)) {
                $this->keuangan->expense(today(), FinancialCategory::PromosiPoin, $hpp, [
                    'reference_type' => RewardRedemption::class,
                    'reference_id' => $redemption->id,
                    'description' => "Biaya promosi poin: {$reward->name} ({$customer->name})",
                ]);
            }

            return $redemption->fresh(['reward', 'sparepart']);
        });
    }
}
