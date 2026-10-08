<?php

namespace Tests\Feature;

use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\MembantuBengkel;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use MembantuBengkel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
        $this->actingAs($this->buatPengguna('owner'), 'sanctum');
    }

    /** Buat pelanggan member + akun portal, lalu kembalikan user-nya. */
    protected function memberDenganRiwayat(): array
    {
        $pelanggan = $this->pelanggan(['name' => 'Member A', 'phone' => '081299900001']);
        $this->postJson("/api/v1/customers/{$pelanggan->id}/membership")->assertStatus(201);

        $part = $this->sparepart(10, 30000, 50000);
        $jasa = $this->jasa(150000);

        $res = $this->postJson('/api/v1/service-orders', [
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $pelanggan->vehicles->first()->id,
            'services' => [['service_id' => $jasa->id, 'qty' => 1]],
            'parts' => [['sparepart_id' => $part->id, 'qty' => 1]],
        ])->assertStatus(201);

        $sa = ServiceOrder::query()->findOrFail($res->json('data.id'));
        $this->postJson("/api/v1/service-orders/{$sa->id}/start")->assertOk();
        $this->postJson("/api/v1/service-orders/{$sa->id}/finish")->assertOk();
        $this->postJson("/api/v1/service-orders/{$sa->id}/pay", ['payment_method' => 'cash'])->assertOk();

        $akun = User::query()->where('customer_id', $pelanggan->id)->firstOrFail();

        return ['pelanggan' => $pelanggan, 'akun' => $akun, 'sa' => $sa->fresh()];
    }

    public function test_portal_menampilkan_profil_member_dan_poin(): void
    {
        $data = $this->memberDenganRiwayat();
        $this->actingAs($data['akun'], 'sanctum');

        $res = $this->getJson('/api/v1/portal/profile')->assertOk();

        $this->assertSame('Member A', $res->json('data.customer.name'));
        $this->assertSame('active', $res->json('data.membership.status'));
        // Member aktif: jasa 150.000 didiskon 10% → 135.000 → 13 poin
        $this->assertSame(13, $res->json('data.membership.points_balance'));
    }

    public function test_member_tidak_dapat_melihat_data_member_lain_404(): void
    {
        $data = $this->memberDenganRiwayat();

        // Member lain dengan SA miliknya sendiri
        $lain = $this->pelanggan(['name' => 'Member B', 'phone' => '081299900002']);
        $jasa = $this->jasa(90000);
        $res = $this->postJson('/api/v1/service-orders', [
            'customer_id' => $lain->id,
            'vehicle_id' => $lain->vehicles->first()->id,
            'services' => [['service_id' => $jasa->id, 'qty' => 1]],
        ])->assertStatus(201);
        $saLain = $res->json('data.id');

        $this->actingAs($data['akun'], 'sanctum');

        // Menebak ID milik orang lain → 404, bukan data orang lain
        $this->getJson("/api/v1/portal/history/service/{$saLain}")
            ->assertStatus(404)
            ->assertJsonPath('code', 'TIDAK_DITEMUKAN');

        // Riwayat sendiri tetap bisa dibuka
        $this->getJson("/api/v1/portal/history/service/{$data['sa']->id}")->assertOk();
    }

    public function test_detail_portal_tidak_membocorkan_harga_beli(): void
    {
        $data = $this->memberDenganRiwayat();
        $this->actingAs($data['akun'], 'sanctum');

        $res = $this->getJson("/api/v1/portal/history/service/{$data['sa']->id}")->assertOk();

        $kunci = $this->kumpulkanKunci($res->json('data'));
        $this->assertNotContains('buy_price', $kunci);
        $this->assertNotContains('hpp_sparepart', $kunci);
        $this->assertNotContains('stock_before', $kunci);
    }

    public function test_member_tidak_boleh_mengakses_endpoint_admin(): void
    {
        $data = $this->memberDenganRiwayat();
        $this->actingAs($data['akun'], 'sanctum');

        $this->getJson('/api/v1/customers')->assertStatus(403);
        $this->getJson('/api/v1/reports/finance')->assertStatus(403);
        $this->getJson('/api/v1/settings')->assertOk(); // pengaturan bengkel boleh dibaca
    }

    public function test_member_kedaluwarsa_tetap_bisa_login_dan_melihat_riwayat(): void
    {
        $data = $this->memberDenganRiwayat();
        $data['pelanggan']->fresh()->membership->update([
            'status' => 'expired',
            'expires_at' => today()->subMonth()->toDateString(),
        ]);

        $this->actingAs($data['akun']->fresh(), 'sanctum');

        $this->getJson('/api/v1/portal/profile')
            ->assertOk()
            ->assertJsonPath('data.membership.status', 'expired');

        $this->getJson('/api/v1/portal/history')->assertOk();
    }

    /** Kumpulkan seluruh nama kunci JSON secara rekursif (untuk uji kebocoran). */
    private function kumpulkanKunci(mixed $data): array
    {
        $kunci = [];

        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $kunci[] = $k;
                $kunci = array_merge($kunci, $this->kumpulkanKunci($v));
            }
        }

        return array_values(array_unique($kunci));
    }
}