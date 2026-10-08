<?php

namespace Tests\Feature;

use App\Models\ServiceOrder;
use App\Models\Sparepart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MembantuBengkel;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use MembantuBengkel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
        $this->actingAs($this->buatPengguna('owner'), 'sanctum');
    }

    /** @return array{sa: ServiceOrder, part: Sparepart} */
    protected function siapkanData(): array
    {
        // Pengeluaran: beli 10 part @50.000 = 500.000
        $part = $this->sparepart(0, 50000, 100000);
        $this->postJson('/api/v1/part-purchases', [
            'purchase_date' => today()->toDateString(),
            'items' => [['sparepart_id' => $part->id, 'qty' => 10, 'buy_price' => 50000]],
        ])->assertStatus(201);

        // Pemasukan: SA dibayar (jasa 200.000 + part 1 pcs harga jual 100.000)
        $jasa = $this->jasa(200000);
        $pelanggan = $this->pelanggan();

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

        return ['sa' => $sa->fresh(), 'part' => $part];
    }

    public function test_laporan_bulanan_menghitung_arus_kas_dan_laba_kotor(): void
    {
        $this->siapkanData();

        $res = $this->getJson('/api/v1/reports/finance?period=bulanan')->assertOk();

        $ringkas = $res->json('data.ringkasan');
        $this->assertSame(300000, $ringkas['total_pemasukan']);       // 200.000 jasa + 100.000 part
        $this->assertSame(500000, $ringkas['total_pengeluaran']);     // pembelian part
        $this->assertSame(-200000, $ringkas['arus_kas']);
        $this->assertSame(200000, $ringkas['pendapatan_jasa']);
        $this->assertSame(100000, $ringkas['pendapatan_sparepart']);
        $this->assertSame(50000, $ringkas['hpp_sparepart']);          // 1 × harga beli 50.000
        $this->assertSame(250000, $ringkas['laba_kotor']);            // 300.000 - 50.000
        $this->assertSame(1, $ringkas['jumlah_sa_dibayar']);
        $this->assertSame(300000, $ringkas['rata_rata_per_sa']);
    }

    public function test_transaksi_dibalik_dihitung_sekali_tidak_ganda(): void
    {
        $data = $this->siapkanData();

        $this->postJson("/api/v1/service-orders/{$data['sa']->id}/cancel", ['alasan' => 'Salah input'])
            ->assertOk();

        $ringkas = $this->getJson('/api/v1/reports/finance?period=bulanan')->json('data.ringkasan');

        // Baris reversal menetralkan pemasukan; pemasukan asli tetap ada (tidak dihapus)
        $this->assertSame(300000, $ringkas['total_pemasukan']);
        $this->assertSame(800000, $ringkas['total_pengeluaran']); // 500.000 beli + 300.000 reversal
        $this->assertSame(-500000, $ringkas['arus_kas']);
        // Laba kotor kembali nol karena SA tidak lagi berstatus dibayar
        $this->assertSame(0, $ringkas['laba_kotor']);
    }

    public function test_periode_mingguan_dan_tahunan_memakai_rentang_benar(): void
    {
        $this->siapkanData();

        $mingguan = $this->getJson('/api/v1/reports/finance?period=mingguan')->assertOk()->json('data');
        $this->assertSame(now()->startOfWeek(\Illuminate\Support\Carbon::MONDAY)->toDateString(), $mingguan['dari']);
        $this->assertSame(now()->endOfWeek(\Illuminate\Support\Carbon::SUNDAY)->toDateString(), $mingguan['sampai']);

        $tahunan = $this->getJson('/api/v1/reports/finance?period=tahunan')->assertOk()->json('data');
        $this->assertSame(now()->startOfYear()->toDateString(), $tahunan['dari']);
        $this->assertSame(now()->startOfYear()->toDateString(), $tahunan['dari']);
        $this->assertSame(12, count($tahunan['seri']));
    }

    public function test_laporan_stok_menghitung_nilai_persediaan(): void
    {
        $this->siapkanData();

        $res = $this->getJson('/api/v1/reports/stock')->assertOk();
        $baris = collect($res->json('data.baris'))->firstWhere('stock', '>', 0);

        $this->assertSame(9, $baris['stock']);
        $this->assertSame(9 * 50000, $baris['nilai_persediaan']);
        $this->assertSame(9 * 50000, $res->json('data.ringkasan.total_nilai_persediaan'));
    }

    public function test_laporan_unit_entry_menghitung_konversi_check_up_ke_service(): void
    {
        $pelanggan = $this->pelanggan();
        $template = \App\Models\CheckupTemplate::query()->where('vehicle_type', 'motor')->first();
        $hasil = $template->items->map(fn ($i) => [
            'category' => $i->category, 'item_name' => $i->name, 'status' => 'ok', 'sort_order' => $i->sort_order,
        ])->all();

        // 1 kunjungan check up saja
        $c1 = $this->postJson('/api/v1/checkups', [
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $pelanggan->vehicles->first()->id,
            'checkup_template_id' => $template->id,
            'checkup_date' => today()->toDateString(),
            'results' => $hasil,
        ])->assertStatus(201)->json('data.id');
        $this->postJson("/api/v1/checkups/{$c1}/finish", ['result' => 'checkup_only'])->assertOk();

        // 1 kunjungan check up yang lanjut service
        $c2 = $this->postJson('/api/v1/checkups', [
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $pelanggan->vehicles->first()->id,
            'checkup_template_id' => $template->id,
            'checkup_date' => today()->toDateString(),
            'results' => $hasil,
        ])->assertStatus(201)->json('data.id');
        $this->postJson("/api/v1/checkups/{$c2}/finish", ['result' => 'continue_service'])->assertOk();

        $res = $this->getJson('/api/v1/reports/unit-entries?from='.today()->toDateString().'&to='.today()->toDateString())
            ->assertOk();

        $ringkas = $res->json('data.ringkasan');
        $this->assertSame(2, $ringkas['total_unit']);
        $this->assertSame(1, $ringkas['checkup_saja']);
        $this->assertSame(1, $ringkas['service']);
        $this->assertSame(1, $ringkas['checkup_lanjut_service']);
        $this->assertSame(50, $ringkas['persen_konversi']);
    }

    public function test_kasir_tidak_boleh_membuka_laporan_keuangan(): void
    {
        $this->actingAs($this->buatPengguna('kasir'), 'sanctum');

        $this->getJson('/api/v1/reports/finance')->assertStatus(403);
    }

    public function test_pengeluaran_manual_tercatat_sebagai_operasional(): void
    {
        $this->postJson('/api/v1/expenses', [
            'transaction_date' => today()->toDateString(),
            'category' => 'operasional',
            'amount' => 350000,
            'description' => 'Bayar listrik bengkel',
        ])->assertStatus(201);

        $ringkas = $this->getJson('/api/v1/reports/finance?period=bulanan')->json('data.ringkasan');
        $this->assertSame(350000, $ringkas['total_pengeluaran']);
    }
}
