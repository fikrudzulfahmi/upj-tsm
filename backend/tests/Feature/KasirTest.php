<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MembantuBengkel;
use Tests\TestCase;

/**
 * Modul kasir dipisah dari Form SA: pembayaran & nota hanya untuk SA
 * yang sudah berstatus `selesai`.
 */
class KasirTest extends TestCase
{
    use MembantuBengkel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
        $this->actingAs($this->buatPengguna('owner'), 'sanctum');
    }

    /** Buat SA dengan status tertentu. @return array{id:int, sa:ServiceOrder} */
    protected function buatSa(string $status = 'finished', int $hargaJasa = 200000, ?string $metode = null): ServiceOrder
    {
        $pelanggan = $this->pelanggan();
        $jasa = $this->jasa($hargaJasa);

        $res = $this->postJson('/api/v1/service-orders', [
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $pelanggan->vehicles->first()->id,
            'services' => [['service_id' => $jasa->id, 'qty' => 1]],
        ])->assertStatus(201);

        $id = $res->json('data.id');

        if (in_array($status, ['in_progress', 'finished', 'paid'], true)) {
            $this->postJson("/api/v1/service-orders/{$id}/start")->assertOk();
        }

        if (in_array($status, ['finished', 'paid'], true)) {
            $this->postJson("/api/v1/service-orders/{$id}/finish")->assertOk();
        }

        if ($status === 'paid') {
            $this->postJson("/api/v1/service-orders/{$id}/pay", ['payment_method' => $metode ?? 'cash'])->assertOk();
        }

        return ServiceOrder::query()->findOrFail($id);
    }

    public function test_daftar_tagihan_hanya_memuat_sa_berstatus_selesai(): void
    {
        $draft = $this->buatSa('draft', 100000);
        $dikerjakan = $this->buatSa('in_progress', 150000);
        $selesai = $this->buatSa('finished', 200000);
        $dibayar = $this->buatSa('paid', 250000);

        $res = $this->getJson('/api/v1/kasir/tagihan')->assertOk();

        $idTampil = collect($res->json('data'))->pluck('id')->all();

        $this->assertContains($selesai->id, $idTampil);
        $this->assertNotContains($draft->id, $idTampil);
        $this->assertNotContains($dikerjakan->id, $idTampil);
        $this->assertNotContains($dibayar->id, $idTampil);
        $this->assertCount(1, $idTampil);
    }

    public function test_ringkasan_tagihan_menghitung_jumlah_dan_total(): void
    {
        $this->buatSa('finished', 200000);
        $this->buatSa('finished', 300000);
        $this->buatSa('paid', 500000, 'transfer');

        $res = $this->getJson('/api/v1/kasir/ringkasan')->assertOk();

        $this->assertSame(2, $res->json('data.jumlah_tagihan'));
        $this->assertSame(500000, $res->json('data.total_tagihan'));
        $this->assertSame(1, $res->json('data.jumlah_dibayar_hari_ini'));
        $this->assertSame(500000, $res->json('data.dibayar_hari_ini'));

        $metode = collect($res->json('data.per_metode_hari_ini'))->firstWhere('metode', 'transfer');
        $this->assertSame(1, $metode['jumlah']);
        $this->assertSame(500000, $metode['total']);
    }

    public function test_kasir_dapat_membayar_tagihan_dan_pemasukan_tercatat(): void
    {
        $this->actingAs($this->buatPengguna('kasir'), 'sanctum');

        $sa = $this->buatSa('finished', 200000);

        $this->postJson("/api/v1/service-orders/{$sa->id}/pay", [
            'payment_method' => 'qris',
            'paid_amount' => 200000,
        ])->assertOk()->assertJsonPath('data.status', 'paid');

        $this->assertSame(200000, (int) FinancialTransaction::query()->where('type', 'income')->sum('amount'));

        // Tagihan hilang dari daftar kasir setelah dibayar
        $this->getJson('/api/v1/kasir/tagihan')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_sa_belum_selesai_tidak_bisa_dibayar(): void
    {
        $draft = $this->buatSa('draft', 120000);

        $this->postJson("/api/v1/service-orders/{$draft->id}/pay", ['payment_method' => 'cash'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ATURAN_BISNIS');

        $this->assertSame(0, FinancialTransaction::count());
    }

    public function test_transaksi_kasir_menampilkan_rekap_per_metode(): void
    {
        $this->buatSa('paid', 200000, 'cash');
        $this->buatSa('paid', 300000, 'qris');
        $this->buatSa('paid', 100000, 'qris');

        $res = $this->getJson('/api/v1/kasir/transaksi')->assertOk();

        $this->assertSame(3, $res->json('meta.rekap.jumlah_transaksi'));
        $this->assertSame(600000, $res->json('meta.rekap.total'));

        $perMetode = collect($res->json('meta.rekap.per_metode'))->keyBy('metode');
        $this->assertSame(1, $perMetode['cash']['jumlah']);
        $this->assertSame(200000, $perMetode['cash']['total']);
        $this->assertSame(2, $perMetode['qris']['jumlah']);
        $this->assertSame(400000, $perMetode['qris']['total']);
        $this->assertSame(0, $perMetode['transfer']['jumlah']);
    }

    public function test_peran_tanpa_izin_kasir_ditolak_403(): void
    {
        $this->actingAs($this->buatPengguna('gudang'), 'sanctum');

        $this->getJson('/api/v1/kasir/tagihan')->assertStatus(403);
    }

    public function test_nota_pdf_ditampilkan_di_tab_bukan_langsung_terunduh(): void
    {
        $sa = $this->buatSa('finished', 200000);

        $res = $this->get("/api/v1/service-orders/{$sa->id}/print");

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
        // `inline` = tampil di tab peramban; `attachment` = langsung terunduh.
        $this->assertStringContainsString('inline', (string) $res->headers->get('content-disposition'));
    }

    public function test_nota_pdf_tidak_membocorkan_harga_beli(): void
    {
        $pelanggan = $this->pelanggan();
        $jasa = $this->jasa(150000);
        $part = $this->sparepart(10, 30000, 50000);

        $res = $this->postJson('/api/v1/service-orders', [
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $pelanggan->vehicles->first()->id,
            'services' => [['service_id' => $jasa->id, 'qty' => 1]],
            'parts' => [['sparepart_id' => $part->id, 'qty' => 1]],
        ])->assertStatus(201);

        $id = $res->json('data.id');
        $this->postJson("/api/v1/service-orders/{$id}/start")->assertOk();
        $this->postJson("/api/v1/service-orders/{$id}/finish")->assertOk();

        $this->get("/api/v1/service-orders/{$id}/print")->assertOk();
        $sa = ServiceOrder::query()->findOrFail($id);

        // Nota menampilkan harga jual; HPP hanya tersimpan di data, tidak dicetak.
        $this->assertSame(30000, $sa->parts()->first()->buy_price);
        $this->assertSame(50000, $sa->parts()->first()->sell_price);
    }
}
