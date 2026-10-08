<?php

namespace App\Services;

use App\Enums\FinancialCategory;
use App\Enums\PaymentMethod;
use App\Enums\ServiceOrderStatus;
use App\Enums\UnitEntryType;
use App\Exceptions\AturanBisnisException;
use App\Models\Checkup;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderCondition;
use App\Models\ServiceOrderPart;
use App\Models\ServiceOrderService as ItemJasa;
use App\Models\Sparepart;
use App\Models\UnitEntry;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Form SA (Service Order). SELURUH angka dihitung di server — nilai dari klien
 * hanya dipakai untuk memilih item dan jumlahnya.
 */
class ServiceOrderService
{
    public function __construct(
        private readonly DocumentNumberService $nomor,
        private readonly SettingService $setting,
        private readonly StockService $stok,
        private readonly FinanceService $keuangan,
        private readonly MembershipService $membership,
        private readonly PointService $poin,
    ) {}

    /* ------------------------------------------------------------------ CRUD */

    public function buat(array $data, ?int $userId = null): ServiceOrder
    {
        // Idempotensi: kiriman ganda dengan kunci sama (klik dobel / retry) → kembalikan record lama.
        if (! empty($data['idempotency_key'])) {
            $existing = ServiceOrder::where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing->fresh($this->relasiLengkap());
            }
        }

        return DB::transaction(function () use ($data, $userId) {
            $customer = Customer::findOrFail($data['customer_id']);
            $vehicle = Vehicle::findOrFail($data['vehicle_id']);

            if ((int) $vehicle->customer_id !== (int) $customer->id) {
                throw new AturanBisnisException('Kendaraan yang dipilih bukan milik pelanggan ini.');
            }

            $unitEntry = ! empty($data['unit_entry_id'])
                ? UnitEntry::findOrFail($data['unit_entry_id'])
                : UnitEntry::create([
                    'entry_no' => $this->nomor->buat('UE'),
                    'entry_date' => today()->toDateString(),
                    'customer_id' => $customer->id,
                    'vehicle_id' => $vehicle->id,
                    'type' => UnitEntryType::Service->value,
                    'has_checkup' => false,
                    'created_by' => $userId ?? auth()->id(),
                ]);

            $membership = $customer->membership;
            $isMember = $membership?->aktif() === true;

            $sa = ServiceOrder::create([
                'sa_no' => $this->nomor->buat('SA'),
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'unit_entry_id' => $unitEntry->id,
                'checkup_id' => $data['checkup_id'] ?? null,
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'customer_name' => $customer->name,
                'plate_number' => $vehicle->plate_number,
                'phone' => $customer->phone,
                'vehicle_name' => $vehicle->namaLengkap(),
                'mechanic_id' => $data['mechanic_id'] ?? null,
                'odometer' => $data['odometer'] ?? null,
                'fuel_level' => $data['fuel_level'] ?? 0,
                'complaint' => $data['complaint'] ?? null,
                'vehicle_condition_notes' => $data['vehicle_condition_notes'] ?? null,
                'is_member_at_entry' => $isMember,
                'member_discount_percent' => $this->membership->persenDiskon($membership),
                'status' => ServiceOrderStatus::Draft->value,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId ?? auth()->id(),
            ]);

            $unitEntry->update(['service_order_id' => $sa->id]);

            $this->susunIsi($sa, $data['services'] ?? [], $data['parts'] ?? []);
            $this->salinKondisi($sa, $data['conditions'] ?? []);
            $this->perbaruiOdometer($vehicle, $data['odometer'] ?? null);

            return $sa->fresh($this->relasiLengkap());
        });
    }

    public function ubah(ServiceOrder $sa, array $data): ServiceOrder
    {
        if (! $sa->status->bolehDiubah()) {
            throw new AturanBisnisException('Form SA yang sudah '.$sa->status->label().' tidak dapat diubah.');
        }

        return DB::transaction(function () use ($sa, $data) {
            if (array_key_exists('mechanic_id', $data)) {
                $sa->mechanic_id = $data['mechanic_id'] ?: null;
            }

            foreach (['odometer', 'fuel_level', 'complaint', 'vehicle_condition_notes', 'notes'] as $kolom) {
                if (array_key_exists($kolom, $data)) {
                    $sa->{$kolom} = $data[$kolom];
                }
            }

            // Diskon mengikuti status member SAAT SA DIBUAT (snapshot), bukan saat diubah.
            if (! $sa->is_member_at_entry) {
                $sa->member_discount_percent = 0;
            }

            $sa->save();

            if (array_key_exists('services', $data) || array_key_exists('parts', $data)) {
                $this->susunIsi($sa, $data['services'] ?? [], $data['parts'] ?? []);
            }

            if (array_key_exists('conditions', $data)) {
                $this->salinKondisi($sa, $data['conditions']);
            }

            $this->perbaruiOdometer($sa->vehicle, $data['odometer'] ?? $sa->odometer);

            return $sa->fresh($this->relasiLengkap());
        });
    }

    /** Hapus Form SA (admin/owner) beserta isi + unit entry yang menjadi yatim. */
    public function hapus(ServiceOrder $serviceOrder): void
    {
        if ($serviceOrder->status->sudahKeluarStok()) {
            throw new AturanBisnisException('Form SA yang sudah selesai/dibayar tidak dapat dihapus. Gunakan Batalkan.');
        }

        DB::transaction(function () use ($serviceOrder) {
            $unitEntryId = $serviceOrder->unit_entry_id;
            // services/parts/conditions terhapus otomatis via cascade; unit_entry.service_order_id jadi null.
            $serviceOrder->delete();

            // Hapus unit entry yatim (kunjungan hanya milik SA ini, tanpa check up).
            if ($unitEntryId) {
                UnitEntry::whereKey($unitEntryId)->whereNull('checkup_id')->delete();
            }
        });
    }

    /* -------------------------------------------------------- alur status */

    public function mulai(ServiceOrder $sa): ServiceOrder
    {
        if ($sa->status !== ServiceOrderStatus::Draft) {
            throw new AturanBisnisException('Hanya Form SA berstatus Draft yang dapat mulai dikerjakan.');
        }

        if ($sa->services()->count() === 0 && $sa->parts()->count() === 0) {
            throw new AturanBisnisException('Form SA masih kosong. Tambahkan pekerjaan atau sparepart terlebih dahulu.');
        }

        $sa->update(['status' => ServiceOrderStatus::InProgress->value]);

        return $sa->fresh($this->relasiLengkap());
    }

    /** Selesai: stok keluar (semua part dikurangi dalam satu transaksi). */
    public function selesaikan(ServiceOrder $sa): ServiceOrder
    {
        if ($sa->status !== ServiceOrderStatus::InProgress) {
            throw new AturanBisnisException('Hanya Form SA yang sedang dikerjakan dapat diselesaikan.');
        }

        return DB::transaction(function () use ($sa) {
            foreach ($sa->parts()->with('sparepart')->get() as $part) {
                // Part hadiah tukar poin sudah dipotong saat penukaran, jangan dobel.
                if ($part->is_free_reward) {
                    continue;
                }

                if (! $part->sparepart) {
                    throw new AturanBisnisException("Sparepart {$part->name} tidak ditemukan di master, periksa kembali isi SA.");
                }

                $this->stok->decrease($part->sparepart, $part->qty, [
                    'reference_type' => ServiceOrder::class,
                    'reference_id' => $sa->id,
                    'notes' => "Dipakai pada {$sa->sa_no}",
                ]);
            }

            $sa->update([
                'status' => ServiceOrderStatus::Finished->value,
                'finished_at' => now(),
            ]);

            return $sa->fresh($this->relasiLengkap());
        });
    }

    /** Dibayar: pemasukan tercatat, poin & masa member diperbarui. */
    public function bayar(ServiceOrder $sa, string $metode, ?int $dibayar = null): ServiceOrder
    {
        if ($sa->status !== ServiceOrderStatus::Finished) {
            throw new AturanBisnisException('Hanya Form SA yang sudah selesai dapat dibayar.');
        }

        if (! in_array($metode, array_column(PaymentMethod::cases(), 'value'), true)) {
            throw new AturanBisnisException('Metode pembayaran tidak dikenal.');
        }

        $dibayar ??= $sa->grand_total;

        if ($dibayar < $sa->grand_total) {
            throw new AturanBisnisException('Jumlah pembayaran kurang dari total tagihan.');
        }

        return DB::transaction(function () use ($sa, $metode, $dibayar) {
            $sa->update([
                'status' => ServiceOrderStatus::Paid->value,
                'paid_at' => now(),
                'payment_method' => $metode,
                'paid_amount' => $dibayar,
            ]);

            $this->keuangan->income(today(), FinancialCategory::PendapatanJasa, $sa->total_services, [
                'reference_type' => ServiceOrder::class,
                'reference_id' => $sa->id,
                'description' => "Pendapatan jasa {$sa->sa_no} - {$sa->customer_name}",
            ]);

            $this->keuangan->income(today(), FinancialCategory::PenjualanSparepart, $sa->total_parts, [
                'reference_type' => ServiceOrder::class,
                'reference_id' => $sa->id,
                'description' => "Penjualan sparepart {$sa->sa_no} - {$sa->customer_name}",
            ]);

            $this->perbaruiPoinDanMembership($sa->fresh());

            return $sa->fresh($this->relasiLengkap());
        });
    }

    /** Batalkan SA: stok dikembalikan + keuangan dibalik (bukan dihapus). */
    public function batalkan(ServiceOrder $sa, string $alasan): ServiceOrder
    {
        if ($sa->status === ServiceOrderStatus::Cancelled) {
            throw new AturanBisnisException('Form SA ini sudah dibatalkan.');
        }

        if (trim($alasan) === '') {
            throw new AturanBisnisException('Alasan pembatalan wajib diisi.');
        }

        return DB::transaction(function () use ($sa, $alasan) {
            if ($sa->status->sudahKeluarStok()) {
                foreach ($sa->parts()->with('sparepart')->get() as $part) {
                    if ($part->is_free_reward || ! $part->sparepart) {
                        continue;
                    }

                    $this->stok->returnStock($part->sparepart, $part->qty, [
                        'reference_type' => ServiceOrder::class,
                        'reference_id' => $sa->id,
                        'notes' => "Pembatalan {$sa->sa_no}: {$alasan}",
                    ]);
                }

                foreach ($this->keuangan->untukReferensi(ServiceOrder::class, $sa->id) as $trx) {
                    $this->keuangan->balik($trx, "Pembatalan {$sa->sa_no}");
                }

                $this->tarikPoin($sa, $alasan);
            }

            $sa->update([
                'status' => ServiceOrderStatus::Cancelled->value,
                'cancel_reason' => $alasan,
            ]);

            return $sa->fresh($this->relasiLengkap());
        });
    }

    /* ------------------------------------------------ lanjutan dari check up */

    public function buatDariCheckup(Checkup $checkup, ?int $userId = null): ServiceOrder
    {
        $checkup->loadMissing(['results', 'customer', 'vehicle', 'unitEntry']);
        $customer = $checkup->customer;
        $vehicle = $checkup->vehicle;
        $membership = $customer->membership;
        $isMember = $membership?->aktif() === true;

        $sa = ServiceOrder::create([
            'sa_no' => $this->nomor->buat('SA'),
            'unit_entry_id' => $checkup->unit_entry_id,
            'checkup_id' => $checkup->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'customer_name' => $customer->name,
            'plate_number' => $vehicle->plate_number,
            'phone' => $customer->phone,
            'vehicle_name' => $vehicle->namaLengkap(),
            'odometer' => $checkup->odometer,
            'fuel_level' => 0,
            'complaint' => $checkup->complaint,
            'vehicle_condition_notes' => $checkup->general_notes,
            'is_member_at_entry' => $isMember,
            'member_discount_percent' => $this->membership->persenDiskon($membership),
            'status' => ServiceOrderStatus::Draft->value,
            'created_by' => $userId ?? auth()->id(),
        ]);

        // Salin kondisi kendaraan dari hasil check up (snapshot).
        foreach ($checkup->results as $hasil) {
            ServiceOrderCondition::create([
                'service_order_id' => $sa->id,
                'category' => $hasil->category,
                'item_name' => $hasil->item_name,
                'status' => $hasil->status,
                'note' => $hasil->note,
                'sort_order' => $hasil->sort_order,
            ]);
        }

        return $sa->fresh($this->relasiLengkap());
    }

    /* -------------------------------------------------------------- helper */

    /**
     * Hitung ulang seluruh isi SA dari master + simpan sebagai snapshot.
     * Diskon HANYA untuk jasa. Sparepart tidak pernah didiskon.
     *
     * @param  array<int, array<string, mixed>>  $jasa
     * @param  array<int, array<string, mixed>>  $part
     */
    private function susunIsi(ServiceOrder $sa, array $jasa, array $part): void
    {
        $sa->services()->delete();
        $sa->parts()->delete();

        $subtotalJasa = 0;
        $diskonJasa = 0;
        $totalPart = 0;

        foreach ($jasa as $baris) {
            $qty = max(1, (int) ($baris['qty'] ?? 1));
            $master = ! empty($baris['service_id']) ? Service::find($baris['service_id']) : null;

            $nama = $master?->name ?? ($baris['name'] ?? 'Jasa');
            $harga = (int) ($master?->price ?? ($baris['price'] ?? 0));

            $persen = $sa->is_member_at_entry
                ? (int) ($master?->member_discount_percent
                    ?? $sa->customer?->membership?->discount_percent_override
                    ?? $this->setting->int('member_discount_percent', 10))
                : 0;

            $kotor = $harga * $qty;
            $potongan = (int) round($kotor * $persen / 100);

            ItemJasa::create([
                'service_order_id' => $sa->id,
                'service_id' => $master?->id,
                'name' => $nama,
                'price' => $harga,
                'qty' => $qty,
                'discount_percent' => $persen,
                'discount_amount' => $potongan,
                'subtotal' => $kotor - $potongan,
            ]);

            $subtotalJasa += $kotor;
            $diskonJasa += $potongan;
        }

        foreach ($part as $baris) {
            $qty = max(1, (int) ($baris['qty'] ?? 1));
            $master = Sparepart::findOrFail($baris['sparepart_id']);
            $gratis = (bool) ($baris['is_free_reward'] ?? false);

            if (! $sa->status->sudahKeluarStok() && ! $gratis && $master->stock < $qty) {
                throw new AturanBisnisException(
                    "Stok {$master->name} tidak cukup. Tersedia {$master->stock} {$master->unit}, diminta {$qty}."
                );
            }

            $hargaJual = $gratis ? 0 : $master->sell_price;

            ServiceOrderPart::create([
                'service_order_id' => $sa->id,
                'sparepart_id' => $master->id,
                'name' => $master->name,
                'qty' => $qty,
                'buy_price' => $master->buy_price,
                'sell_price' => $hargaJual,
                'subtotal' => $hargaJual * $qty,
                'is_free_reward' => $gratis,
            ]);

            $totalPart += $hargaJual * $qty;
        }

        $totalJasa = $subtotalJasa - $diskonJasa;

        $sa->update([
            'subtotal_services' => $subtotalJasa,
            'discount_services' => $diskonJasa,
            'total_services' => $totalJasa,
            'total_parts' => $totalPart,
            'grand_total' => $totalJasa + $totalPart,
            'estimate_total' => $totalJasa + $totalPart,
        ]);
    }

    /** @param array<int, array<string, mixed>> $kondisi */
    private function salinKondisi(ServiceOrder $sa, array $kondisi): void
    {
        if ($kondisi === []) {
            return;
        }

        $sa->conditions()->delete();

        foreach (array_values($kondisi) as $i => $baris) {
            ServiceOrderCondition::create([
                'service_order_id' => $sa->id,
                'category' => $baris['category'] ?? 'Umum',
                'item_name' => $baris['item_name'] ?? 'Item',
                'status' => $baris['status'] ?? 'tidak_diperiksa',
                'note' => $baris['note'] ?? null,
                'sort_order' => $baris['sort_order'] ?? $i + 1,
            ]);
        }
    }

    private function perbaruiPoinDanMembership(ServiceOrder $sa): void
    {
        $customer = $sa->customer;

        if (! $customer) {
            return;
        }

        $membership = $customer->membership;

        if ($sa->is_member_at_entry && $membership && $membership->status === 'active') {
            $perNominal = max(1, $this->setting->int('points_per_amount', 10000));
            $poin = intdiv($sa->total_services, $perNominal);

            if ($poin > 0) {
                $this->poin->tambah($membership, $poin, [
                    'service_order_id' => $sa->id,
                    'description' => "Poin dari servis {$sa->sa_no}",
                ]);
            }

            $this->membership->perpanjangDariServis($customer, today());

            return;
        }

        // Opsional: otomatis jadi member setelah servis pertama.
        if (! $membership && $this->setting->bool('auto_member_after_first_service', false)) {
            $this->membership->aktifkan($customer, today());
        }
    }

    /** Tarik poin yang sudah diberikan bila SA dibatalkan (sebatas saldo tersedia). */
    private function tarikPoin(ServiceOrder $sa, string $alasan): void
    {
        $membership = $sa->customer?->membership;

        if (! $membership) {
            return;
        }

        $diperoleh = (int) $membership->pointTransactions()
            ->where('service_order_id', $sa->id)
            ->where('type', 'earn')
            ->sum('points');

        if ($diperoleh <= 0) {
            return;
        }

        $tarik = min($diperoleh, $membership->points_balance);

        try {
            if ($tarik > 0) {
                $this->poin->sesuaikan($membership, -$tarik, [
                    'service_order_id' => $sa->id,
                    'description' => "Penarikan poin pembatalan {$sa->sa_no}: {$alasan}",
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal menarik poin pembatalan SA', ['sa' => $sa->sa_no, 'error' => $e->getMessage()]);
        }
    }

    private function perbaruiOdometer(?Vehicle $vehicle, mixed $odometer): void
    {
        if (! $vehicle || ! $odometer) {
            return;
        }

        if ((int) $odometer > (int) $vehicle->last_odometer) {
            $vehicle->update(['last_odometer' => (int) $odometer]);
        }
    }

    /** @return array<int, string> */
    private function relasiLengkap(): array
    {
        return ['services', 'parts.sparepart', 'conditions', 'mechanic', 'customer', 'vehicle', 'unitEntry'];
    }
}
