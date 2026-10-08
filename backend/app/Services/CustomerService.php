<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    /**
     * Simpan pelanggan (baru/ubah) sekaligus kendaraannya.
     * Nomor polisi dinormalisasi (uppercase tanpa spasi) dan nomor HP diseragamkan.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $kendaraan
     */
    public function simpan(array $data, array $kendaraan = [], ?Customer $customer = null): Customer
    {
        return DB::transaction(function () use ($data, $kendaraan, $customer) {
            $muatan = [
                'name' => trim($data['name']),
                'gender' => $data['gender'] ?? null,
                'phone' => $this->normalisasiHp($data['phone'] ?? null),
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            if ($customer) {
                $customer->update($muatan);
            } else {
                $customer = Customer::create($muatan);
            }

            foreach ($kendaraan as $k) {
                $atribut = [
                    'plate_number' => $this->normalisasiNopol($k['plate_number']),
                    'type' => $k['type'] ?? 'motor',
                    'brand' => $k['brand'] ?? null,
                    'model' => $k['model'] ?? null,
                    'year' => isset($k['year']) && $k['year'] !== '' ? (int) $k['year'] : null,
                    'color' => $k['color'] ?? null,
                    'engine_number' => $k['engine_number'] ?? null,
                    'frame_number' => $k['frame_number'] ?? null,
                    'last_odometer' => isset($k['last_odometer']) && $k['last_odometer'] !== '' ? (int) $k['last_odometer'] : null,
                ];

                if (! empty($k['id'])) {
                    $customer->vehicles()->whereKey($k['id'])->update($atribut);
                } else {
                    $customer->vehicles()->create($atribut);
                }
            }

            return $customer->fresh(['vehicles', 'membership']);
        });
    }

    public function normalisasiNopol(?string $nopol): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $nopol));
    }

    /** 0812… / 62812… / +62 812… → 0812… (konsisten untuk login & pencarian). */
    public function normalisasiHp(?string $hp): ?string
    {
        if ($hp === null || trim($hp) === '') {
            return null;
        }

        $angka = preg_replace('/\D/', '', $hp);

        if (str_starts_with($angka, '62')) {
            $angka = '0'.substr($angka, 2);
        } elseif (str_starts_with($angka, '8')) {
            $angka = '0'.$angka;
        }

        return $angka;
    }
}
