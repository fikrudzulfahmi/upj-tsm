<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Mechanic;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Sparepart;
use App\Services\CheckupService;
use App\Services\MembershipService;
use App\Services\ServiceOrderService;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk pengembangan lokal (JANGAN dijalankan di production).
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $mekanik = collect(['Budi Santoso', 'Agus Riyadi', 'Dedi Kurniawan'])
            ->map(fn ($nama) => Mechanic::query()->create(['name' => $nama, 'is_active' => true]));

        $jasa = collect([
            ['Servis Ringan + Ganti Oli', 50000],
            ['Servis Besar (Turun Mesin)', 750000],
            ['Ganti Kampas Rem', 40000],
            ['Tune Up Mesin', 120000],
            ['Servis CVT / Rantai', 65000],
            ['Ganti Ban Luar + Dalam', 35000],
            ['Perbaikan Kelistrikan', 80000],
            ['Cuci Motor + Poles', 25000],
        ])->map(fn ($j) => Service::query()->create([
            'name' => $j[0], 'price' => $j[1], 'is_active' => true,
        ]));

        $spareparts = [
            ['OLI-001', 'Oli Mesin 10W-40', 'liter', 45000, 65000, 24, 6],
            ['OLI-002', 'Oli Gardan Matic', 'botol', 22000, 35000, 18, 6],
            ['SPR-001', 'Kampas Rem Depan', 'set', 35000, 60000, 12, 4],
            ['SPR-002', 'Kampas Rem Belakang', 'set', 30000, 55000, 10, 4],
            ['SPR-003', 'Busi Iridium', 'pcs', 45000, 75000, 16, 5],
            ['SPR-004', 'Filter Udara', 'pcs', 30000, 50000, 9, 4],
            ['SPR-005', 'V-Belt CVT', 'pcs', 85000, 135000, 6, 3],
            ['SPR-006', 'Roller CVT Set', 'set', 60000, 95000, 5, 3],
            ['SPR-007', 'Aki Kering 5Ah', 'pcs', 210000, 285000, 4, 2],
            ['SPR-008', 'Rantai + Gir Set', 'set', 145000, 210000, 3, 2],
            ['SPR-009', 'Ban Luar 80/90', 'pcs', 155000, 215000, 6, 2],
            ['SPR-010', 'Lampu LED Depan', 'pcs', 75000, 120000, 8, 3],
        ];

        foreach ($spareparts as $s) {
            Sparepart::query()->create([
                'sku' => $s[0], 'name' => $s[1], 'unit' => $s[2],
                'buy_price' => $s[3], 'sell_price' => $s[4],
                'stock' => $s[5], 'min_stock' => $s[6], 'is_active' => true,
            ]);
        }

        $this->call(RewardSeeder::class); // "Oli Gratis" 100 poin → Oli Mesin

        $pelangganData = [
            ['Andi Pratama', 'L', '081211110001', 'AB 1234 CD', 'motor', 'Honda', 'Beat', 2021, 'Hitam'],
            ['Siti Rahayu', 'P', '081211110002', 'AB 5678 EF', 'motor', 'Yamaha', 'Mio', 2019, 'Merah'],
            ['Budi Hartono', 'L', '081211110003', 'AB 9012 GH', 'motor', 'Honda', 'Vario 125', 2022, 'Putih'],
            ['Dewi Lestari', 'P', '081211110004', 'AB 3456 IJ', 'mobil', 'Toyota', 'Avanza', 2018, 'Silver'],
            ['Eko Prasetyo', 'L', '081211110005', 'AB 7890 KL', 'motor', 'Suzuki', 'Satria F150', 2020, 'Biru'],
        ];

        $pelanggan = collect($pelangganData)->map(function ($p) {
            $customer = Customer::query()->create([
                'name' => $p[0], 'gender' => $p[1], 'phone' => $p[2],
                'address' => 'Jl. Contoh No. '.random_int(1, 100).', Kota Contoh',
            ]);

            $customer->vehicles()->create([
                'plate_number' => str_replace(' ', '', $p[3]),
                'type' => $p[4], 'brand' => $p[5], 'model' => $p[6],
                'year' => $p[7], 'color' => $p[8],
            ]);

            return $customer->fresh(['vehicles', 'membership']);
        });

        $checkupService = app(CheckupService::class);
        $saService = app(ServiceOrderService::class);
        $membershipService = app(MembershipService::class);

        $passwordAwal = null;

        // Pelanggan 1 → member aktif
        $hasil = $membershipService->aktifkan($pelanggan[0]);
        $passwordAwal = $hasil['password_awal'];

        // Riwayat 1: check up saja
        $template = \App\Models\CheckupTemplate::query()->where('vehicle_type', 'motor')->first();
        $hasilCheckup = $template->items->map(fn ($i) => [
            'category' => $i->category, 'item_name' => $i->name, 'status' => 'ok', 'sort_order' => $i->sort_order,
        ])->all();
        $hasilCheckup[3]['status'] = 'perlu_perhatian';
        $hasilCheckup[3]['note'] = 'Kampas rem mulai tipis';

        $checkupSaja = $checkupService->simpan([
            'customer_id' => $pelanggan[1]->id,
            'vehicle_id' => $pelanggan[1]->vehicles->first()->id,
            'checkup_template_id' => $template->id,
            'checkup_date' => today()->toDateString(),
            'odometer' => 24500,
            'complaint' => 'Motor terasa bergetar saat kecepatan tinggi',
            'results' => $hasilCheckup,
        ]);
        $checkupService->selesaikan($checkupSaja, 'checkup_only');

        // Riwayat 2: check up → lanjut service → selesai → dibayar
        $hasilCheckup[0]['status'] = 'rusak';
        $hasilCheckup[0]['note'] = 'Oli sudah waktunya diganti';

        $checkupLanjut = $checkupService->simpan([
            'customer_id' => $pelanggan[0]->id,
            'vehicle_id' => $pelanggan[0]->vehicles->first()->id,
            'checkup_template_id' => $template->id,
            'checkup_date' => today()->toDateString(),
            'odometer' => 18700,
            'complaint' => 'Servis rutin 3 bulan',
            'results' => $hasilCheckup,
        ]);

        $hasilLanjut = $checkupService->selesaikan($checkupLanjut, 'continue_service');
        $sa = $hasilLanjut['service_order'];

        $sa = $saService->ubah($sa, [
            'customer_id' => $sa->customer_id,
            'vehicle_id' => $sa->vehicle_id,
            'mechanic_id' => $mekanik[0]->id,
            'fuel_level' => 5,
            'services' => [['service_id' => $jasa[0]->id, 'qty' => 1]],
            'parts' => [
                ['sparepart_id' => Sparepart::query()->where('sku', 'OLI-001')->first()->id, 'qty' => 2],
                ['sparepart_id' => Sparepart::query()->where('sku', 'SPR-004')->first()->id, 'qty' => 1],
            ],
        ]);

        $saService->mulai($sa);
        $saService->selesaikan($sa->fresh());
        $saService->bayar($sa->fresh(), 'cash');

        // Riwayat 3: SA langsung (tanpa check up), belum dibayar
        $saDua = $saService->buat([
            'customer_id' => $pelanggan[2]->id,
            'vehicle_id' => $pelanggan[2]->vehicles->first()->id,
            'mechanic_id' => $mekanik[1]->id,
            'fuel_level' => 2,
            'complaint' => 'Ganti kampas rem belakang',
            'services' => [['service_id' => $jasa[2]->id, 'qty' => 1]],
            'parts' => [['sparepart_id' => Sparepart::query()->where('sku', 'SPR-002')->first()->id, 'qty' => 1]],
        ]);
        $saService->mulai($saDua);

        // Pengeluaran operasional contoh
        app(\App\Services\FinanceService::class)->expense(
            today()->startOfMonth()->addDays(2),
            \App\Enums\FinancialCategory::Operasional,
            450000,
            ['description' => 'Listrik & air bengkel bulan ini']
        );

        $this->command?->info('Data contoh siap: 5 pelanggan, 8 jasa, 12 sparepart, 3 mekanik.');
        $this->command?->info('Login admin : owner@bengkel.test / '.env('OWNER_PASSWORD'));
        if ($passwordAwal) {
            $this->command?->info("Login member: {$pelanggan[0]->phone} / {$passwordAwal} (wajib ganti password)");
        }
    }
}
