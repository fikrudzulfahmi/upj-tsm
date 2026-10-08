<?php

namespace Tests\Feature;

use App\Models\Checkup;
use App\Models\ServiceOrder;
use App\Models\UnitEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MembantuBengkel;
use Tests\TestCase;

class CheckupFlowTest extends TestCase
{
    use MembantuBengkel, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
        $this->actingAs($this->buatPengguna('kasir'), 'sanctum');
    }

    protected function buatCheckup(array $tambahan = []): Checkup
    {
        $pelanggan = $this->pelanggan();
        $kendaraan = $pelanggan->vehicles->first();
        $template = \App\Models\CheckupTemplate::query()->where('vehicle_type', 'motor')->first();

        $hasil = $template->items->map(fn ($i) => [
            'category' => $i->category,
            'item_name' => $i->name,
            'status' => 'ok',
            'sort_order' => $i->sort_order,
        ])->all();

        // satu item dibuat "rusak" untuk menguji salinan kondisi
        $hasil[1]['status'] = 'rusak';
        $hasil[1]['note'] = 'Kampas rem tipis';

        $res = $this->postJson('/api/v1/checkups', array_merge([
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $kendaraan->id,
            'checkup_template_id' => $template->id,
            'checkup_date' => today()->toDateString(),
            'odometer' => 10000,
            'complaint' => 'Rem kurang pakem',
            'results' => $hasil,
        ], $tambahan))->assertStatus(201);

        return Checkup::query()->findOrFail($res->json('data.id'));
    }

    public function test_check_up_membuat_satu_unit_entry_dan_menyimpan_hasil_snapshot(): void
    {
        $checkup = $this->buatCheckup();

        $this->assertMatchesRegularExpression('/^CU-\d{6}-\d{4}$/', $checkup->checkup_no);
        $this->assertCount(1, UnitEntry::all());
        $this->assertTrue($checkup->unitEntry->has_checkup);
        $this->assertSame('checkup_only', $checkup->unitEntry->type->value);
        $this->assertGreaterThan(20, $checkup->results()->count());

        // nama item disalin (snapshot), bukan relasi ke template
        $rusak = $checkup->results()->where('status', 'rusak')->first();
        $this->assertNotNull($rusak);
        $this->assertNotEmpty($rusak->item_name);
    }

    public function test_baris_hasil_wajib_menyertakan_status_kondisi(): void
    {
        // Kontrak: server TIDAK mengisi kondisi sendiri. Baris tanpa `status` ditolak,
        // supaya tidak ada item yang "diam-diam" tercatat sudah diperiksa padahal belum.
        $pelanggan = $this->pelanggan();
        $kendaraan = $pelanggan->vehicles->first();

        $this->postJson('/api/v1/checkups', [
            'customer_id' => $pelanggan->id,
            'vehicle_id' => $kendaraan->id,
            'checkup_date' => today()->toDateString(),
            'results' => [['category' => 'Mesin', 'item_name' => 'Oli mesin', 'sort_order' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('results.0.status');
    }

    public function test_default_kolom_status_adalah_tidak_diperiksa(): void
    {
        // Default kolom DB juga "Tidak diperiksa" (bukan "OK") — jaring pengaman
        // untuk baris yang dibuat tanpa status dari luar aplikasi.
        $checkup = $this->buatCheckup();

        $id = DB::table('checkup_results')->insertGetId([
            'checkup_id' => $checkup->id,
            'category' => 'Mesin',
            'item_name' => 'Item tanpa status',
            'sort_order' => 99,
        ]);

        $this->assertSame('tidak_diperiksa', DB::table('checkup_results')->where('id', $id)->value('status'));
    }

    public function test_duplikat_template_menyalin_semua_item_dan_nama_tidak_bertumpuk(): void
    {
        $asal = \App\Models\CheckupTemplate::query()->where('vehicle_type', 'motor')->with('items')->first();
        $jumlahAsal = $asal->items->count();
        $this->assertGreaterThan(20, $jumlahAsal);

        // Peran kasir (akun bawaan setUp) TIDAK boleh menyalin template —
        // mengubah template butuh izin setting.manage.
        $this->postJson("/api/v1/checkup-templates/{$asal->id}/duplikat")->assertStatus(403);

        $this->actingAs($this->buatPengguna('owner'), 'sanctum');
        $res = $this->postJson("/api/v1/checkup-templates/{$asal->id}/duplikat")->assertStatus(201);
        $idBaru = $res->json('data.id');

        $salinan = \App\Models\CheckupTemplate::query()->with('items')->findOrFail($idBaru);
        $this->assertSame($asal->vehicle_type, $salinan->vehicle_type);
        $this->assertCount($jumlahAsal, $salinan->items, 'semua item ikut tersalin');
        $this->assertSame(
            $asal->items()->orderBy('sort_order')->pluck('name')->all(),
            $salinan->items()->orderBy('sort_order')->pluck('name')->all(),
            'urutan & nama item sama dengan sumber',
        );

        // Salinan kedua tidak boleh memakai nama yang sama.
        $res2 = $this->postJson("/api/v1/checkup-templates/{$asal->id}/duplikat")->assertStatus(201);
        $this->assertNotSame($res->json('data.name'), $res2->json('data.name'));
        $this->assertStringContainsString('salinan', $res2->json('data.name'));
    }

    public function test_finish_checkup_only_tidak_membuat_form_sa(): void
    {
        $checkup = $this->buatCheckup();

        $this->postJson("/api/v1/checkups/{$checkup->id}/finish", ['result' => 'checkup_only'])
            ->assertOk()
            ->assertJsonPath('data.service_order', null);

        $this->assertSame(0, ServiceOrder::count());
        $this->assertSame('completed', $checkup->fresh()->status->value);
        $this->assertSame('checkup_only', $checkup->fresh()->unitEntry->type->value);
    }

    public function test_finish_lanjut_service_membuat_sa_draft_dengan_kondisi_tersalin_dan_unit_entry_sama(): void
    {
        $checkup = $this->buatCheckup();
        $unitEntryId = $checkup->unit_entry_id;

        $res = $this->postJson("/api/v1/checkups/{$checkup->id}/finish", ['result' => 'continue_service'])
            ->assertOk();

        $saId = $res->json('data.service_order_id');
        $this->assertNotNull($saId);

        $sa = ServiceOrder::query()->findOrFail($saId);
        $this->assertSame('draft', $sa->status->value);
        $this->assertSame($checkup->id, $sa->checkup_id);
        $this->assertSame($unitEntryId, $sa->unit_entry_id);
        $this->assertSame($checkup->complaint, $sa->complaint);
        $this->assertSame($checkup->results()->count(), $sa->conditions()->count());
        $this->assertSame('service', $sa->unitEntry->type->value);
        $this->assertSame($sa->id, $sa->unitEntry->service_order_id);
    }

    public function test_finish_dua_kali_ditolak_422(): void
    {
        $checkup = $this->buatCheckup();

        $this->postJson("/api/v1/checkups/{$checkup->id}/finish", ['result' => 'checkup_only'])->assertOk();
        $this->postJson("/api/v1/checkups/{$checkup->id}/finish", ['result' => 'checkup_only'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ATURAN_BISNIS');
    }

    public function test_pilihan_hasil_tidak_dikenal_ditolak_422(): void
    {
        $checkup = $this->buatCheckup();

        $this->postJson("/api/v1/checkups/{$checkup->id}/finish", ['result' => 'apa_saja'])
            ->assertStatus(422);
    }
}
