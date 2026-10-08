<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /** Permission → peran yang memilikinya (bagian 5 blueprint). */
    public const PETA = [
        'customer.manage' => ['owner', 'admin', 'kasir'],
        'checkup.manage' => ['owner', 'admin', 'kasir'],
        'service-order.manage' => ['owner', 'admin', 'kasir'],
        'kasir.manage' => ['owner', 'admin', 'kasir'], // modul kasir: daftar tagihan, pembayaran, riwayat + nota
        'sparepart.manage' => ['owner', 'admin', 'gudang'],
        'stock.input' => ['owner', 'admin', 'gudang'],
        'service.manage' => ['owner', 'admin'],
        'mechanic.manage' => ['owner', 'admin'],
        'report.finance' => ['owner', 'admin'],
        'report.stock' => ['owner', 'admin', 'gudang', 'kasir'],
        'setting.manage' => ['owner', 'admin'],
        'user.manage' => ['owner'],
    ];

    public const PERAN = ['owner', 'admin', 'kasir', 'gudang', 'member'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERAN as $peran) {
            Role::findOrCreate($peran, 'web');
        }

        foreach (array_keys(self::PETA) as $izin) {
            Permission::findOrCreate($izin, 'web');
        }

        // Buang permission yang sudah tidak dipakai (mis. `service-order.pay`
        // yang digantikan `kasir.manage`) agar tidak ada izin "hantu" di database.
        Permission::query()->whereNotIn('name', array_keys(self::PETA))->delete();

        foreach (self::PETA as $izin => $peran) {
            foreach ($peran as $p) {
                Role::findByName($p, 'web')->givePermissionTo($izin);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}