<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\ServiceOrder;
use App\Models\Sparepart;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MembantuBengkel;
use Tests\TestCase;

class StokKeuanganTest extends TestCase
{
    use MembantuBengkel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
        $this->actingAs($this->buatPengguna('owner'), 'sanctum');
    }

    public function test_input_sparepart_menambah_stok_dan_mencatat_pengeluaran(): void
    {
        $part = $this->sparepart(0, 40000, 60000);

        $this->postJson('/api/v1/part-purchases', [
            'purchase_date' => today()->toDateString(),
            'supplier_name' => 'Supplier Uji',
            'items' => [['sparepart_id' => $part->id, 'qty' => 12, 'buy_price' => 45000]],
        ])->assertStatus(201);

        $this->assertSame(12, $part->fresh()->stock);
        $this->assertSame(45000, $part->fresh()->buy_price); // harga beli mengikuti pembelian terakhir

        $movement = StockMovement::query()->where('sparepart_id', $part->id)->first();
        $this->assertSame('in', $movement->type->value);
        $this->assertSame(0, $movement->stock_before);
        $this->assertSame(12, $movement->stock_after);

        $expense = FinancialTransaction::query()->where('category', 'pembelian_sparepart')->first();
        $this->assertNotNull($expense);
        $this->assertSame('expense', $expense->type->value);
        $this->assertSame(12 * 45000, $expense->amount);
    }

    public function test_stok_tidak_boleh_minus(): void
    {
        $part = $this->sparepart(2, 40000, 60000);

        $this->postJson('/api/v1/stock-adjustments', [
            'sparepart_id' => $part->id, 'stock_baru' => -1, 'alasan' => 'uji',
        ])->assertStatus(422);

        $this->assertSame(2, $part->fresh()->stock);
    }

    public function test_penyesuaian_stok_wajib_alasan_dan_mencatat_movement(): void
    {
        $part = $this->sparepart(5, 40000, 60000);

        $this->postJson('/api/v1/stock-adjustments', [
            'sparepart_id' => $part->id, 'stock_baru' => 7, 'alasan' => '',
        ])->assertStatus(422);

        $this->postJson('/api/v1/stock-adjustments', [
            'sparepart_id' => $part->id, 'stock_baru' => 7, 'alasan' => 'Stok opname bulanan',
        ])->assertOk();

        $this->assertSame(7, $part->fresh()->stock);
        $movement = StockMovement::query()->where('sparepart_id', $part->id)->latest('id')->first();
        $this->assertSame('adjust', $movement->type->value);
        $this->assertSame(5, $movement->stock_before);
        $this->assertSame(7, $movement->stock_after);
        // Penyesuaian tidak menyentuh keuangan
        $this->assertSame(0, FinancialTransaction::count());
    }

    public function test_form_sa_menolak_part_melebihi_stok_saat_dibuat(): void
    {
        $pelanggan = $this->pelanggan();
        $part = $this->sparepart(3, 40000, 60000);

        $this->postJson('/api/v1/service-orders', [
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $pelanggan->vehicles->first()->id,
            'parts' => [['sparepart_id' => $part->id, 'qty' => 5]],
        ])->assertStatus(422)->assertJsonPath('code', 'ATURAN_BISNIS');

        $this->assertSame(3, $part->fresh()->stock);
    }

    public function test_selesai_gagal_total_saat_stok_kurang_dan_stok_tidak_berubah(): void
    {
        $pelanggan = $this->pelanggan();
        $partA = $this->sparepart(10, 40000, 60000);
        $partB = $this->sparepart(10, 40000, 70000);

        $res = $this->postJson('/api/v1/service-orders', [
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $pelanggan->vehicles->first()->id,
            'parts' => [
                ['sparepart_id' => $partA->id, 'qty' => 4],
                ['sparepart_id' => $partB->id, 'qty' => 8],
            ],
        ])->assertStatus(201);

        $sa = ServiceOrder::query()->findOrFail($res->json('data.id'));

        // Stok part B dihabiskan lebih dulu oleh transaksi lain
        $this->postJson('/api/v1/stock-adjustments', [
            'sparepart_id' => $partB->id, 'stock_baru' => 1, 'alasan' => 'dipakai unit lain',
        ])->assertOk();

        $this->postJson("/api/v1/service-orders/{$sa->id}/start")->assertOk();
        $this->postJson("/api/v1/service-orders/{$sa->id}/finish")
            ->assertStatus(422)->assertJsonPath('code', 'ATURAN_BISNIS');

        // Tidak ada stok yang berkurang (transaksi di-rollback seluruhnya)
        $this->assertSame(10, $partA->fresh()->stock);
        $this->assertSame(1, $partB->fresh()->stock);
        $this->assertSame('in_progress', $sa->fresh()->status->value);
    }

    public function test_selesai_mengurangi_stok_dan_dibayar_mencatat_dua_baris_pemasukan(): void
    {
        $pelanggan = $this->pelanggan();
        $jasa = $this->jasa(200000);
        $part = $this->sparepart(10, 40000, 60000);

        $res = $this->postJson('/api/v1/service-orders', [
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $pelanggan->vehicles->first()->id,
            'services' => [['service_id' => $jasa->id, 'qty' => 1]],
            'parts' => [['sparepart_id' => $part->id, 'qty' => 2]],
        ])->assertStatus(201);

        $id = $res->json('data.id');
        $this->postJson("/api/v1/service-orders/{$id}/start")->assertOk();
        $this->postJson("/api/v1/service-orders/{$id}/finish")->assertOk();

        $this->assertSame(8, $part->fresh()->stock);
        $movement = StockMovement::query()->where('type', 'out')->first();
        $this->assertSame(2, $movement->qty);

        $this->postJson("/api/v1/service-orders/{$id}/pay", ['payment_method' => 'transfer'])->assertOk();

        $this->assertSame(1, FinancialTransaction::query()->where('category', 'pendapatan_jasa')->count());
        $this->assertSame(1, FinancialTransaction::query()->where('category', 'penjualan_sparepart')->count());
        $this->assertSame(200000, (int) FinancialTransaction::query()->where('category', 'pendapatan_jasa')->sum('amount'));
        $this->assertSame(120000, (int) FinancialTransaction::query()->where('category', 'penjualan_sparepart')->sum('amount'));
    }

    public function test_dibayar_tanpa_part_hanya_mencatat_satu_baris_pemasukan(): void
    {
        $pelanggan = $this->pelanggan();
        $jasa = $this->jasa(80000);

        $res = $this->postJson('/api/v1/service-orders', [
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $pelanggan->vehicles->first()->id,
            'services' => [['service_id' => $jasa->id, 'qty' => 1]],
        ])->assertStatus(201);

        $id = $res->json('data.id');
        $this->postJson("/api/v1/service-orders/{$id}/start")->assertOk();
        $this->postJson("/api/v1/service-orders/{$id}/finish")->assertOk();
        $this->postJson("/api/v1/service-orders/{$id}/pay", ['payment_method' => 'cash'])->assertOk();

        $this->assertSame(1, FinancialTransaction::count()); // baris nol tidak dicatat
        $this->assertSame('pendapatan_jasa', FinancialTransaction::first()->category);
    }

    public function test_pembatalan_mengembalikan_stok_dan_membalik_keuangan(): void
    {
        $pelanggan = $this->pelanggan();
        $jasa = $this->jasa(150000);
        $part = $this->sparepart(10, 40000, 60000);

        $res = $this->postJson('/api/v1/service-orders', [
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $pelanggan->vehicles->first()->id,
            'services' => [['service_id' => $jasa->id, 'qty' => 1]],
            'parts' => [['sparepart_id' => $part->id, 'qty' => 3]],
        ])->assertStatus(201);

        $id = $res->json('data.id');
        $this->postJson("/api/v1/service-orders/{$id}/start")->assertOk();
        $this->postJson("/api/v1/service-orders/{$id}/finish")->assertOk();
        $this->postJson("/api/v1/service-orders/{$id}/pay", ['payment_method' => 'cash'])->assertOk();

        $this->assertSame(7, $part->fresh()->stock);

        $this->postJson("/api/v1/service-orders/{$id}/cancel", ['alasan' => 'Pelanggan batal bayar'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        // Stok kembali
        $this->assertSame(10, $part->fresh()->stock);
        $this->assertSame(1, StockMovement::query()->where('type', 'return')->count());

        // Keuangan dibalik, bukan dihapus
        $asli = FinancialTransaction::query()->whereNull('reversal_of')->count();
        $pembalik = FinancialTransaction::query()->whereNotNull('reversal_of')->count();
        $this->assertSame(2, $asli);
        $this->assertSame(2, $pembalik);

        // Arus kas kembali ke 0 (pemasukan dibatalkan oleh baris reversal)
        $masuk = (int) FinancialTransaction::query()->where('type', 'income')->sum('amount');
        $keluar = (int) FinancialTransaction::query()->where('type', 'expense')->sum('amount');
        $this->assertSame(0, $masuk - $keluar);
    }
}