<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MembantuBengkel;
use Tests\TestCase;

class PelangganTest extends TestCase
{
    use MembantuBengkel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
        $this->actingAs($this->buatPengguna('kasir'), 'sanctum');
    }

    public function test_tambah_pelanggan_dengan_dua_kendaraan(): void
    {
        $res = $this->postJson('/api/v1/customers', [
            'name' => 'Budi',
            'gender' => 'L',
            'phone' => '0812 3333 4444',
            'vehicles' => [
                ['plate_number' => 'ab 1234 xy', 'type' => 'motor', 'brand' => 'Yamaha'],
                ['plate_number' => 'D 5555 ZZ', 'type' => 'mobil', 'brand' => 'Toyota'],
            ],
        ])->assertStatus(201);

        $this->assertCount(2, $res->json('data.vehicles'));
        // Nomor HP & nopol dinormalisasi
        $this->assertSame('081233334444', $res->json('data.phone'));
        $this->assertSame('AB1234XY', $res->json('data.vehicles.0.plate_number'));
        $this->assertMatchesRegularExpression('/^C-\d{6}$/', $res->json('data.code'));
    }

    public function test_nomor_polisi_duplikat_ditolak(): void
    {
        $this->postJson('/api/v1/customers', [
            'name' => 'Pelanggan A',
            'vehicles' => [['plate_number' => 'AB9999ZZ', 'type' => 'motor']],
        ])->assertStatus(201);

        $this->postJson('/api/v1/customers', [
            'name' => 'Pelanggan B',
            'vehicles' => [['plate_number' => 'ab 9999 zz', 'type' => 'motor']],
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_pencarian_berdasarkan_nopol_dan_nama(): void
    {
        $this->postJson('/api/v1/customers', [
            'name' => 'Siti Aminah',
            'vehicles' => [['plate_number' => 'N 7788 AA', 'type' => 'motor']],
        ])->assertStatus(201);

        $this->getJson('/api/v1/customers?search=7788')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/customers?search=Aminah')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_hapus_pelanggan_soft_delete(): void
    {
        $customer = $this->pelanggan();

        $this->deleteJson('/api/v1/customers/'.$customer->id)->assertOk();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }
}
