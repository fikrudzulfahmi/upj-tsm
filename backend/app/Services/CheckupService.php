<?php

namespace App\Services;

use App\Enums\CheckupResult;
use App\Enums\CheckupStatus;
use App\Enums\UnitEntryType;
use App\Exceptions\AturanBisnisException;
use App\Models\Checkup;
use App\Models\CheckupResult as HasilCheckup;
use App\Models\Customer;
use App\Models\UnitEntry;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class CheckupService
{
    public function __construct(
        private readonly DocumentNumberService $nomor,
        private readonly ServiceOrderService $serviceOrder,
    ) {}

    /**
     * Simpan check up baru/ubah. SATU kunjungan = SATU unit entry.
     *
     * @param  array<string, mixed>  $data
     */
    public function simpan(array $data, ?Checkup $checkup = null, ?int $userId = null): Checkup
    {
        return DB::transaction(function () use ($data, $checkup, $userId) {
            $customer = Customer::findOrFail($data['customer_id']);
            $vehicle = Vehicle::findOrFail($data['vehicle_id']);

            if ((int) $vehicle->customer_id !== (int) $customer->id) {
                throw new AturanBisnisException('Kendaraan yang dipilih bukan milik pelanggan ini.');
            }

            if ($checkup && $checkup->status === CheckupStatus::Completed) {
                throw new AturanBisnisException('Check up yang sudah selesai tidak dapat diubah.');
            }

            $atribut = [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'checkup_template_id' => $data['checkup_template_id'] ?? null,
                'checkup_date' => $data['checkup_date'] ?? today()->toDateString(),
                'odometer' => $data['odometer'] ?? null,
                'complaint' => $data['complaint'] ?? null,
                'general_notes' => $data['general_notes'] ?? null,
            ];

            if ($checkup) {
                $checkup->update($atribut);
            } else {
                $unitEntry = UnitEntry::create([
                    'entry_no' => $this->nomor->buat('UE'),
                    'entry_date' => $atribut['checkup_date'],
                    'customer_id' => $customer->id,
                    'vehicle_id' => $vehicle->id,
                    'type' => UnitEntryType::CheckupOnly->value,
                    'has_checkup' => true,
                    'created_by' => $userId ?? auth()->id(),
                ]);

                $checkup = Checkup::create($atribut + [
                    'checkup_no' => $this->nomor->buat('CU'),
                    'unit_entry_id' => $unitEntry->id,
                    'status' => CheckupStatus::Draft->value,
                    'created_by' => $userId ?? auth()->id(),
                ]);

                $unitEntry->update(['checkup_id' => $checkup->id]);
            }

            if (array_key_exists('results', $data) && is_array($data['results'])) {
                $this->simpanHasil($checkup, $data['results']);
            }

            // Catat odometer terakhir kendaraan bila lebih besar.
            if (! empty($atribut['odometer']) && (int) $atribut['odometer'] > (int) $vehicle->last_odometer) {
                $vehicle->update(['last_odometer' => (int) $atribut['odometer']]);
            }

            return $checkup->fresh(['results', 'customer', 'vehicle', 'template', 'unitEntry']);
        });
    }

    /**
     * Selesaikan check up: hanya check up, atau lanjut ke Form SA
     * (SA draft dibuat otomatis, kondisi & keluhan tersalin, unit entry sama).
     *
     * @return array{checkup: Checkup, service_order: ?\App\Models\ServiceOrder}
     */
    public function selesaikan(Checkup $checkup, string $hasil, ?int $userId = null): array
    {
        if (! in_array($hasil, [CheckupResult::CheckupOnly->value, CheckupResult::ContinueService->value], true)) {
            throw new AturanBisnisException('Pilihan hasil check up tidak dikenal.');
        }

        if ($checkup->status === CheckupStatus::Completed) {
            throw new AturanBisnisException('Check up ini sudah diselesaikan sebelumnya.');
        }

        return DB::transaction(function () use ($checkup, $hasil, $userId) {
            $checkup->update([
                'result' => $hasil,
                'status' => CheckupStatus::Completed->value,
            ]);

            $serviceOrder = null;

            if ($hasil === CheckupResult::ContinueService->value) {
                $serviceOrder = $this->serviceOrder->buatDariCheckup($checkup, $userId);

                $checkup->unitEntry?->update([
                    'type' => UnitEntryType::Service->value,
                    'service_order_id' => $serviceOrder->id,
                ]);
            }

            return ['checkup' => $checkup->fresh(['results', 'unitEntry']), 'service_order' => $serviceOrder];
        });
    }

    /** @param array<int, array<string, mixed>> $hasil */
    private function simpanHasil(Checkup $checkup, array $hasil): void
    {
        $checkup->results()->delete();

        foreach (array_values($hasil) as $i => $baris) {
            HasilCheckup::create([
                'checkup_id' => $checkup->id,
                'category' => $baris['category'] ?? 'Umum',
                'item_name' => $baris['item_name'] ?? 'Item',
                'status' => $baris['status'] ?? 'tidak_diperiksa',
                'note' => $baris['note'] ?? null,
                'sort_order' => $baris['sort_order'] ?? $i + 1,
            ]);
        }
    }
}
