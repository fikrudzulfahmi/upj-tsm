<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MembantuBengkel;
use Tests\TestCase;

class ServiceOrderTest extends TestCase
{
    use MembantuBengkel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
        $this->actingAs($this->buatPengguna('kasir'), 'sanctum');
    }

    protected function buatSa(Customer $pelanggan, array $jasa, array $part, array $tambahan = []): ServiceOrder
    {
        $res = $this->postJson('/api/v1/service-orders', array_merge([
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $pelanggan->vehicles->first()->id,
            'fuel_level' => 3,
            'services' => $jasa,
            'parts' => $part,
        ], $tambahan))->assertStatus(201);

        return ServiceOrder::query()->findOrFail($res->json('data.id'));
    }

    public function test_non_member_tidak_mendapat_diskon(): void
    {
        $pelanggan = $this->pelanggan();
        $jasa = $this->jasa(100000);
        $part = $this->sparepart(10, 40000, 60000);

        $sa = $this->buatSa($pelanggan, [['service_id' => $jasa->id, 'qty' => 2]], [['sparepart_id' => $part->id, 'qty' => 1]]);

        $this->assertFalse($sa->is_member_at_entry);
        $this->assertSame(0, $sa->discount_services);
        $this->assertSame(200000, $sa->subtotal_services);
        $this->assertSame(200000, $sa->total_services);
        $this->assertSame(60000, $sa->total_parts);
        $this->assertSame(260000, $sa->grand_total);
    }

    public function test_member_aktif_mendapat_diskon_hanya_pada_jasa(): void
    {
        $pelanggan = $this->pelanggan();
        $this->postJson("/api/v1/customers/{$pelanggan->id}/membership")->assertStatus(201);

        $jasa = $this->jasa(100000);
        $part = $this->sparepart(10, 40000, 60000);

        $sa = $this->buatSa($pelanggan->fresh(), [['service_id' => $jasa->id, 'qty' => 2]], [['sparepart_id' => $part->id, 'qty' => 2]]);

        $this->assertTrue($sa->is_member_at_entry);
        $this->assertSame(10, $sa->member_discount_percent);
        $this->assertSame(20000, $sa->discount_services);       // 10% dari 200.000
        $this->assertSame(180000, $sa->total_services);
        $this->assertSame(120000, $sa->total_parts);            // sparepart TIDAK didiskon
        $this->assertSame(300000, $sa->grand_total);

        $itemPart = $sa->parts()->first();
        $this->assertSame(0, $itemPart->discount_percent ?? 0);
    }

    public function test_persen_diskon_per_jasa_menimpa_diskon_global(): void
    {
        $pelanggan = $this->pelanggan();
        $this->postJson("/api/v1/customers/{$pelanggan->id}/membership")->assertStatus(201);

        $jasa = $this->jasa(100000, 25);

        $sa = $this->buatSa($pelanggan->fresh(), [['service_id' => $jasa->id, 'qty' => 1]], []);

        $this->assertSame(25, $sa->services()->first()->discount_percent);
        $this->assertSame(25000, $sa->discount_services);
        $this->assertSame(75000, $sa->total_services);
    }

    public function test_member_kedaluwarsa_tidak_mendapat_diskon(): void
    {
        $pelanggan = $this->pelanggan();
        $this->postJson("/api/v1/customers/{$pelanggan->id}/membership")->assertStatus(201);

        $pelanggan->fresh()->membership->update(['expires_at' => today()->subDay()->toDateString()]);

        $jasa = $this->jasa(100000);
        $sa = $this->buatSa($pelanggan->fresh(), [['service_id' => $jasa->id, 'qty' => 1]], []);

        $this->assertFalse($sa->is_member_at_entry);
        $this->assertSame(0, $sa->discount_services);
        $this->assertSame(100000, $sa->total_services);
    }

    public function test_total_dihitung_server_dan_mengabaikan_kiriman_klien(): void
    {
        $pelanggan = $this->pelanggan();
        $jasa = $this->jasa(100000);

        $sa = $this->buatSa($pelanggan, [
            ['service_id' => $jasa->id, 'qty' => 1, 'price' => 1000, 'discount_amount' => 999999, 'subtotal' => 1],
        ], [], ['grand_total' => 1, 'subtotal_services' => 1, 'total_services' => 1]);

        $this->assertSame(100000, $sa->subtotal_services);
        $this->assertSame(0, $sa->discount_services);
        $this->assertSame(100000, $sa->grand_total);
    }

    public function test_transisi_status_tidak_sah_ditolak(): void
    {
        $pelanggan = $this->pelanggan();
        $jasa = $this->jasa(50000);
        $sa = $this->buatSa($pelanggan, [['service_id' => $jasa->id, 'qty' => 1]], []);

        // Bayar sebelum selesai
        $this->postJson("/api/v1/service-orders/{$sa->id}/pay", ['payment_method' => 'cash'])
            ->assertStatus(422)->assertJsonPath('code', 'ATURAN_BISNIS');

        // Selesai sebelum mulai dikerjakan
        $this->postJson("/api/v1/service-orders/{$sa->id}/finish")
            ->assertStatus(422)->assertJsonPath('code', 'ATURAN_BISNIS');

        $this->postJson("/api/v1/service-orders/{$sa->id}/start")->assertOk();
        $this->assertSame('in_progress', $sa->fresh()->status->value);

        // Mulai dua kali
        $this->postJson("/api/v1/service-orders/{$sa->id}/start")->assertStatus(422);
    }

    public function test_form_sa_yang_sudah_dibayar_tidak_bisa_diubah(): void
    {
        $pelanggan = $this->pelanggan();
        $jasa = $this->jasa(50000);
        $sa = $this->buatSa($pelanggan, [['service_id' => $jasa->id, 'qty' => 1]], []);

        $this->postJson("/api/v1/service-orders/{$sa->id}/start")->assertOk();
        $this->postJson("/api/v1/service-orders/{$sa->id}/finish")->assertOk();
        $this->postJson("/api/v1/service-orders/{$sa->id}/pay", ['payment_method' => 'cash'])->assertOk();

        $this->putJson("/api/v1/service-orders/{$sa->id}", [
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $pelanggan->vehicles->first()->id,
            'services' => [['service_id' => $jasa->id, 'qty' => 5]],
        ])->assertStatus(422);
    }
}
