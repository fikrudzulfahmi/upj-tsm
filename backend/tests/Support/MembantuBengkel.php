<?php

namespace Tests\Support;

use App\Models\Customer;
use App\Models\Mechanic;
use App\Models\Service;
use App\Models\Sparepart;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\CheckupTemplateSeeder;
use Database\Seeders\RewardSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Hash;

trait MembantuBengkel
{
    /** Seed dasar: peran+permission, pengaturan, template check up, reward. */
    protected function seedDasar(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingSeeder::class);
        $this->seed(CheckupTemplateSeeder::class);
        $this->seed(RewardSeeder::class);
    }

    protected function buatPengguna(string $peran = 'owner'): User
    {
        $user = User::query()->create([
            'name' => 'Pengguna '.$peran,
            'email' => $peran.'@bengkel.test',
            'password' => Hash::make('rahasia123'),
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $user->assignRole($peran);

        return $user->fresh('roles');
    }

    protected function pelanggan(array $atribut = [], int $jumlahKendaraan = 1): Customer
    {
        $customer = Customer::query()->create(array_merge([
            'code' => 'C-'.str_pad((string) (Customer::withTrashed()->count() + 1), 6, '0', STR_PAD_LEFT),
            'name' => 'Pelanggan Uji',
            'gender' => 'L',
            'phone' => '0812000000'.random_int(10, 99),
        ], $atribut));

        for ($i = 1; $i <= $jumlahKendaraan; $i++) {
            Vehicle::query()->create([
                'customer_id' => $customer->id,
                'plate_number' => 'AB'.random_int(1000, 9999).'CD',
                'type' => 'motor',
                'brand' => 'Honda',
                'model' => 'Beat',
            ]);
        }

        return $customer->fresh(['vehicles', 'membership']);
    }

    protected function sparepart(int $stock = 10, int $buy = 40000, int $sell = 60000): Sparepart
    {
        return Sparepart::query()->create([
            'sku' => 'SKU-'.random_int(100000, 999999),
            'name' => 'Sparepart Uji '.random_int(1, 9999),
            'unit' => 'pcs',
            'buy_price' => $buy,
            'sell_price' => $sell,
            'stock' => $stock,
            'min_stock' => 2,
            'is_active' => true,
        ]);
    }

    protected function jasa(int $harga = 100000, ?int $diskonMember = null): Service
    {
        return Service::query()->create([
            'name' => 'Jasa Uji '.random_int(1, 9999),
            'price' => $harga,
            'member_discount_percent' => $diskonMember,
            'is_active' => true,
        ]);
    }

    protected function mekanik(): Mechanic
    {
        return Mechanic::query()->create(['name' => 'Mekanik Uji', 'is_active' => true]);
    }

    /** Muat seluruh nilai pengaturan ulang ke cache (dipakai setelah mengubah setting). */
    protected function segarkanPengaturan(): void
    {
        app(\App\Services\SettingService::class)->lupakanCache();
    }
}
