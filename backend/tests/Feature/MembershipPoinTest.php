<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\PointTransaction;
use App\Models\Reward;
use App\Models\Sparepart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\MembantuBengkel;
use Tests\TestCase;

class MembershipPoinTest extends TestCase
{
    use MembantuBengkel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
        $this->actingAs($this->buatPengguna('owner'), 'sanctum');
    }

    protected function buatSaDibayar($pelanggan, int $hargaJasa, ?int $qtyPart = null, ?Sparepart $part = null): void
    {
        $jasa = $this->jasa($hargaJasa);

        $muatan = [
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $pelanggan->vehicles->first()->id,
            'services' => [['service_id' => $jasa->id, 'qty' => 1]],
        ];

        if ($part && $qtyPart) {
            $muatan['parts'] = [['sparepart_id' => $part->id, 'qty' => $qtyPart]];
        }

        $res = $this->postJson('/api/v1/service-orders', $muatan)->assertStatus(201);
        $id = $res->json('data.id');

        $this->postJson("/api/v1/service-orders/{$id}/start")->assertOk();
        $this->postJson("/api/v1/service-orders/{$id}/finish")->assertOk();
        $this->postJson("/api/v1/service-orders/{$id}/pay", ['payment_method' => 'cash'])->assertOk();
    }

    public function test_jadikan_member_membuat_akun_portal_dan_masa_berlaku_enam_bulan(): void
    {
        $pelanggan = $this->pelanggan(['phone' => '081234500001']);

        $res = $this->postJson("/api/v1/customers/{$pelanggan->id}/membership")->assertStatus(201);

        $this->assertSame('active', $res->json('data.membership.status'));
        $this->assertSame(today()->addMonths(6)->toDateString(), $res->json('data.membership.expires_at'));
        $this->assertMatchesRegularExpression('/^M-\d{6}$/', $res->json('data.membership.member_no'));
        $this->assertNotEmpty($res->json('data.password_awal'));

        // Akun portal: login = nomor HP, wajib ganti password
        $akun = User::query()->where('customer_id', $pelanggan->id)->first();
        $this->assertNotNull($akun);
        $this->assertSame('081234500001', $akun->phone);
        $this->assertTrue($akun->must_change_password);
        $this->assertTrue($akun->hasRole('member'));

        // Password awal hanya tampil sekali (permintaan kedua = aktivasi ulang ditolak)
        $this->postJson("/api/v1/customers/{$pelanggan->id}/membership")
            ->assertStatus(422)->assertJsonPath('code', 'ATURAN_BISNIS');
    }

    public function test_servis_dibayar_menambah_poin_dan_memperpanjang_masa_member(): void
    {
        $pelanggan = $this->pelanggan();
        $this->postJson("/api/v1/customers/{$pelanggan->id}/membership")->assertStatus(201);

        // Masa berlaku digeser ke belakang agar perpanjangan terlihat jelas
        $pelanggan->fresh()->membership->update(['expires_at' => today()->addDays(10)->toDateString()]);

        $this->buatSaDibayar($pelanggan->fresh(), 250000);

        $membership = $pelanggan->fresh()->membership;
        // Member aktif: jasa 250.000 didiskon 10% → 225.000 → 22 poin
        $this->assertSame(22, $membership->points_balance);
        $this->assertSame(today()->addMonths(6)->toDateString(), $membership->expires_at->toDateString());
        $this->assertSame(today()->toDateString(), $membership->last_service_at->toDateString());

        $ledger = PointTransaction::query()->where('type', 'earn')->first();
        $this->assertNotNull($ledger);
        $this->assertSame(22, $ledger->points);
        $this->assertSame(22, $ledger->balance_after);
    }

    public function test_non_member_tidak_mendapat_poin_dan_tidak_memperpanjang_member(): void
    {
        $pelanggan = $this->pelanggan();

        $this->buatSaDibayar($pelanggan, 500000);

        $this->assertNull($pelanggan->fresh()->membership);
        $this->assertSame(0, PointTransaction::count());
    }

    public function test_poin_tidak_dihitung_dari_sparepart(): void
    {
        $pelanggan = $this->pelanggan();
        $this->postJson("/api/v1/customers/{$pelanggan->id}/membership")->assertStatus(201);

        $part = $this->sparepart(10, 40000, 300000);
        $this->buatSaDibayar($pelanggan->fresh(), 110000, 1, $part);

        // 110.000 jasa didiskon 10% → 99.000 → 9 poin (part 300.000 tidak dihitung)
        $this->assertSame(9, $pelanggan->fresh()->membership->points_balance);
    }

    public function test_perintah_expire_menghanguskan_member_dan_poinnya(): void
    {
        $pelanggan = $this->pelanggan();
        $this->postJson("/api/v1/customers/{$pelanggan->id}/membership")->assertStatus(201);

        $membership = $pelanggan->fresh()->membership;
        $membership->update([
            'expires_at' => today()->subDays(2)->toDateString(),
            'points_balance' => 40,
        ]);
        PointTransaction::query()->create([
            'customer_id' => $pelanggan->id, 'membership_id' => $membership->id,
            'type' => 'earn', 'points' => 40, 'balance_after' => 40, 'description' => 'awal',
        ]);

        $this->artisan('memberships:expire')->assertSuccessful();

        $membership->refresh();
        $this->assertSame('expired', $membership->status);
        $this->assertSame(0, $membership->points_balance);

        $hangus = PointTransaction::query()->where('type', 'expire')->first();
        $this->assertNotNull($hangus);
        $this->assertSame(-40, $hangus->points);
    }

    public function test_tukar_poin_mengurangi_stok_dan_mencatat_biaya_promosi(): void
    {
        $oli = $this->sparepart(10, 40000, 60000);
        $oli->update(['name' => 'Oli Mesin 10W-40']);
        $this->seed(\Database\Seeders\RewardSeeder::class);

        $pelanggan = $this->pelanggan();
        $this->postJson("/api/v1/customers/{$pelanggan->id}/membership")->assertStatus(201);

        $membership = $pelanggan->fresh()->membership;
        $membership->update(['points_balance' => 120]);
        PointTransaction::query()->create([
            'customer_id' => $pelanggan->id, 'membership_id' => $membership->id,
            'type' => 'earn', 'points' => 120, 'balance_after' => 120, 'description' => 'awal',
        ]);

        $reward = Reward::query()->firstOrFail();

        $this->postJson("/api/v1/customers/{$pelanggan->id}/redeem", ['reward_id' => $reward->id])
            ->assertStatus(201)
            ->assertJsonPath('data.saldo_poin', 20);

        $this->assertSame(9, $oli->fresh()->stock);
        $this->assertSame(20, $pelanggan->fresh()->membership->points_balance);

        $biaya = \App\Models\FinancialTransaction::query()->where('category', 'promosi_poin')->first();
        $this->assertNotNull($biaya);
        $this->assertSame(40000, $biaya->amount); // HPP
    }

    public function test_tukar_poin_tanpa_saldo_cukup_ditolak(): void
    {
        $oli = $this->sparepart(5, 40000, 60000);
        $oli->update(['name' => 'Oli Mesin 10W-40']);
        $this->seed(\Database\Seeders\RewardSeeder::class);

        $pelanggan = $this->pelanggan();
        $this->postJson("/api/v1/customers/{$pelanggan->id}/membership")->assertStatus(201);

        $reward = Reward::query()->firstOrFail();

        $this->postJson("/api/v1/customers/{$pelanggan->id}/redeem", ['reward_id' => $reward->id])
            ->assertStatus(422)->assertJsonPath('code', 'ATURAN_BISNIS');

        $this->assertSame(5, $oli->fresh()->stock);
        $this->assertSame(0, PointTransaction::query()->where('type', 'redeem')->count());
    }

    public function test_perpanjang_manual_menambah_enam_bulan_dari_tanggal_berakhir(): void
    {
        $pelanggan = $this->pelanggan();
        $this->postJson("/api/v1/customers/{$pelanggan->id}/membership")->assertStatus(201);

        $sebelum = Membership::query()->where('customer_id', $pelanggan->id)->firstOrFail();
        $berakhirSebelumnya = $sebelum->expires_at->copy();

        // Perpanjangan dilakukan sebulan kemudian; dasar perhitungan = tanggal berakhir yang MASIH berlaku.
        Carbon::setTestNow(today()->addMonth());

        $this->postJson("/api/v1/customers/{$pelanggan->id}/membership/renew")->assertOk();

        $sesudah = Membership::query()->where('customer_id', $pelanggan->id)->firstOrFail();
        $this->assertSame($berakhirSebelumnya->addMonths(6)->toDateString(), $sesudah->expires_at->toDateString());
        $this->assertSame('active', $sesudah->status);
        Carbon::setTestNow();
    }

    public function test_perpanjang_manual_saat_sudah_hangus_dihitung_dari_hari_ini(): void
    {
        $pelanggan = $this->pelanggan();
        $this->postJson("/api/v1/customers/{$pelanggan->id}/membership")->assertStatus(201);

        Membership::query()->where('customer_id', $pelanggan->id)
            ->update(['status' => 'expired', 'expires_at' => today()->subMonths(2)->toDateString()]);

        $this->postJson("/api/v1/customers/{$pelanggan->id}/membership/renew")->assertOk();

        $membership = Membership::query()->where('customer_id', $pelanggan->id)->firstOrFail();
        $this->assertSame('active', $membership->status);
        $this->assertSame(today()->addMonths(6)->toDateString(), $membership->expires_at->toDateString());
    }
}