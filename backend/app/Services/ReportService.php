<?php

namespace App\Services;

use App\Enums\FinancialCategory;
use App\Enums\FinancialType;
use App\Models\FinancialTransaction;
use App\Models\ServiceOrder;
use App\Models\Sparepart;
use App\Models\UnitEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan keuangan (arus kas & laba kotor), stok, dan unit entry.
 *
 * Catatan penting: pembelian part = pengeluaran, penjualan part = pemasukan,
 * maka laporan menyediakan DUA sudut pandang:
 *   1. Arus Kas   = pemasukan - pengeluaran (uang riil)
 *   2. Laba Kotor = pendapatan - HPP part yang benar-benar terjual
 * Baris REVERSAL ikut dihitung pada sisi kebalikannya (tidak diabaikan, tidak dihitung ganda).
 */
class ReportService
{
    /** Rentang tanggal untuk sebuah periode. */
    public function rentang(string $periode, ?string $tanggal = null): array
    {
        $acuan = $tanggal ? Carbon::parse($tanggal) : Carbon::now();

        return match ($periode) {
            'harian', 'daily' => [
                $acuan->copy()->startOfDay(), $acuan->copy()->endOfDay(), $acuan->translatedFormat('d F Y'),
            ],
            'mingguan', 'weekly' => [
                $acuan->copy()->startOfWeek(Carbon::MONDAY), $acuan->copy()->endOfWeek(Carbon::SUNDAY),
                'Minggu '.$acuan->copy()->startOfWeek(Carbon::MONDAY)->format('d/m').' - '.$acuan->copy()->endOfWeek(Carbon::SUNDAY)->format('d/m/Y'),
            ],
            'tahunan', 'yearly' => [
                $acuan->copy()->startOfYear(), $acuan->copy()->endOfYear(), 'Tahun '.$acuan->year,
            ],
            'rentang' => [
                Carbon::parse($tanggal ?? now())->startOfDay(),
                Carbon::parse(request()->query('sampai', now()))->endOfDay(),
                'Periode pilihan',
            ],
            default => [
                $acuan->copy()->startOfMonth(), $acuan->copy()->endOfMonth(), $acuan->translatedFormat('F Y'),
            ],
        };
    }

    /**
     * Ringkasan keuangan + rincian per kategori + data grafik.
     *
     * @return array<string, mixed>
     */
    public function keuangan(string $periode = 'bulanan', ?string $tanggal = null, ?string $sampai = null): array
    {
        [$dari, $sampaiTanggal, $label] = $periode === 'rentang'
            ? [
                Carbon::parse($tanggal ?? now())->startOfDay(),
                Carbon::parse($sampai ?? $tanggal ?? now())->endOfDay(),
                'Periode pilihan',
            ]
            : $this->rentang($periode, $tanggal);

        $dariTanggal = $dari->toDateString();
        $sampaiDate = $sampaiTanggal->toDateString();

        // --- arus kas -------------------------------------------------------
        $perKategori = FinancialTransaction::query()
            ->whereBetween('transaction_date', [$dariTanggal, $sampaiDate])
            ->select('type', 'category', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as jumlah'))
            ->groupBy('type', 'category')
            ->get();

        $pemasukan = [];
        $pengeluaran = [];

        foreach ($perKategori as $baris) {
            $nama = FinancialCategory::tryFrom($baris->category)?->label() ?? $baris->category;
            $item = ['kategori' => $baris->category, 'label' => $nama, 'total' => (int) $baris->total, 'jumlah' => (int) $baris->jumlah];

            // `type` di-cast ke enum oleh model → bandingkan nilai stringnya.
            $tipe = $baris->type instanceof FinancialType ? $baris->type->value : (string) $baris->type;

            if ($tipe === FinancialType::Income->value) {
                $pemasukan[] = $item;
            } else {
                $pengeluaran[] = $item;
            }
        }

        $totalPemasukan = array_sum(array_column($pemasukan, 'total'));
        $totalPengeluaran = array_sum(array_column($pengeluaran, 'total'));

        // --- laba kotor -----------------------------------------------------
        $saDibayar = ServiceOrder::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$dari->copy()->startOfDay(), $sampaiTanggal->copy()->endOfDay()]);

        $jumlahSa = (clone $saDibayar)->count();
        $pendapatanJasa = (int) (clone $saDibayar)->sum('total_services');
        $pendapatanPart = (int) (clone $saDibayar)->sum('total_parts');

        $hpp = (int) DB::table('service_order_parts')
            ->join('service_orders', 'service_orders.id', '=', 'service_order_parts.service_order_id')
            ->where('service_orders.status', 'paid')
            ->whereBetween('service_orders.paid_at', [$dari->copy()->startOfDay(), $sampaiTanggal->copy()->endOfDay()])
            ->selectRaw('COALESCE(SUM(service_order_parts.buy_price * service_order_parts.qty), 0) as total')
            ->value('total');

        $labaKotor = ($pendapatanJasa + $pendapatanPart) - $hpp;

        // --- grafik ---------------------------------------------------------
        $harian = $periode === 'tahunan';
        $seri = $this->seriGrafik($dari, $sampaiTanggal, $harian);

        return [
            'periode' => $periode,
            'label_periode' => $label,
            'dari' => $dariTanggal,
            'sampai' => $sampaiDate,
            'ringkasan' => [
                'total_pemasukan' => $totalPemasukan,
                'total_pengeluaran' => $totalPengeluaran,
                'arus_kas' => $totalPemasukan - $totalPengeluaran,
                'pendapatan_jasa' => $pendapatanJasa,
                'pendapatan_sparepart' => $pendapatanPart,
                'hpp_sparepart' => $hpp,
                'laba_kotor' => $labaKotor,
                'jumlah_sa_dibayar' => $jumlahSa,
                'rata_rata_per_sa' => $jumlahSa > 0 ? (int) round(($pendapatanJasa + $pendapatanPart) / $jumlahSa) : 0,
            ],
            'pemasukan' => $pemasukan,
            'pengeluaran' => $pengeluaran,
            'seri' => $seri,
        ];
    }

    /** Data seri grafik: harian untuk periode pendek, bulanan untuk tahunan. */
    private function seriGrafik(CarbonInterface $dari, CarbonInterface $sampai, bool $bulanan): array
    {
        $pemasukan = FinancialTransaction::query()
            ->where('type', FinancialType::Income->value)
            ->whereBetween('transaction_date', [$dari->toDateString(), $sampai->toDateString()]);

        $pengeluaran = FinancialTransaction::query()
            ->where('type', FinancialType::Expense->value)
            ->whereBetween('transaction_date', [$dari->toDateString(), $sampai->toDateString()]);

        $format = $bulanan ? '%Y-%m' : '%Y-%m-%d';
        $kunciPemasukan = $pemasukan->selectRaw("DATE_FORMAT(transaction_date, '{$format}') as kunci, SUM(amount) as total")
            ->groupBy('kunci')->pluck('total', 'kunci');
        $kunciPengeluaran = $pengeluaran->selectRaw("DATE_FORMAT(transaction_date, '{$format}') as kunci, SUM(amount) as total")
            ->groupBy('kunci')->pluck('total', 'kunci');

        $seri = [];
        $kursor = $bulanan ? $dari->copy()->startOfMonth() : $dari->copy()->startOfDay();
        $langkah = 0;

        while ($kursor->lte($sampai) && $langkah < 400) {
            $kunci = $kursor->format($bulanan ? 'Y-m' : 'Y-m-d');
            $seri[] = [
                'label' => $kursor->format($bulanan ? 'M Y' : 'd/m'),
                'pemasukan' => (int) ($kunciPemasukan[$kunci] ?? 0),
                'pengeluaran' => (int) ($kunciPengeluaran[$kunci] ?? 0),
            ];
            $bulanan ? $kursor->addMonth() : $kursor->addDay();
            $langkah++;
        }

        return $seri;
    }

    /** Laporan stok terkini. */
    public function stok(bool $hanyaMenipis = false): array
    {
        $query = Sparepart::query()->orderBy('name');

        if ($hanyaMenipis) {
            $query->menipis();
        }

        $baris = $query->get()->map(fn (Sparepart $p) => [
            'id' => $p->id,
            'sku' => $p->sku,
            'name' => $p->name,
            'unit' => $p->unit,
            'stock' => $p->stock,
            'min_stock' => $p->min_stock,
            'buy_price' => $p->buy_price,
            'sell_price' => $p->sell_price,
            'nilai_persediaan' => $p->nilaiPersediaan(),
            'menipis' => $p->stock <= $p->min_stock,
        ])->values()->all();

        return [
            'baris' => $baris,
            'ringkasan' => [
                'jumlah_item' => count($baris),
                'total_nilai_persediaan' => array_sum(array_column($baris, 'nilai_persediaan')),
                'jumlah_menipis' => count(array_filter($baris, fn ($b) => $b['menipis'])),
            ],
        ];
    }

    /** Laporan unit entry + konversi check up → service. */
    public function unitEntry(?string $dari = null, ?string $sampai = null, ?string $tipe = null): array
    {
        $dari ??= now()->startOfMonth()->toDateString();
        $sampai ??= now()->endOfMonth()->toDateString();

        $query = UnitEntry::query()
            ->with(['customer:id,name,phone', 'vehicle:id,plate_number,brand,model', 'serviceOrder:id,sa_no,status,grand_total', 'checkup:id,checkup_no,result,status'])
            ->whereBetween('entry_date', [$dari, $sampai])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($tipe) {
            $query->where('type', $tipe);
        }

        $baris = $query->get();

        $jumlahCheckupSaja = $baris->where('type', 'checkup_only')->count();
        $jumlahService = $baris->where('type', 'service')->count();
        $lanjutService = $baris->filter(fn ($u) => $u->checkup_id && $u->service_order_id)->count();
        $totalCheckup = $baris->where('has_checkup', true)->count();

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'baris' => $baris->map(fn (UnitEntry $u) => [
                'id' => $u->id,
                'entry_no' => $u->entry_no,
                'entry_date' => $u->entry_date->toDateString(),
                'customer' => $u->customer?->name,
                'phone' => $u->customer?->phone,
                'plate_number' => $u->vehicle?->plate_number,
                'kendaraan' => $u->vehicle?->namaLengkap(),
                'type' => $u->type->value,
                'has_checkup' => $u->has_checkup,
                'checkup_no' => $u->checkup?->checkup_no,
                'sa_no' => $u->serviceOrder?->sa_no,
                'status_sa' => $u->serviceOrder?->status?->value,
                'grand_total' => $u->serviceOrder?->grand_total,
            ])->all(),
            'ringkasan' => [
                'total_unit' => $baris->count(),
                'checkup_saja' => $jumlahCheckupSaja,
                'service' => $jumlahService,
                'checkup_lanjut_service' => $lanjutService,
                'persen_konversi' => $totalCheckup > 0 ? (int) round($lanjutService / $totalCheckup * 100) : 0,
            ],
        ];
    }

    /** Ringkasan dashboard. */
    public function dashboard(): array
    {
        $hariIni = today()->toDateString();

        $saHariIni = ServiceOrder::query()->whereDate('created_at', $hariIni);
        $stokMenipis = Sparepart::query()->menipis()->orderBy('stock')->limit(5)->get();

        $memberAkanHangus = DB::table('memberships')
            ->join('customers', 'customers.id', '=', 'memberships.customer_id')
            ->where('memberships.status', 'active')
            ->whereBetween('memberships.expires_at', [$hariIni, now()->addDays(14)->toDateString()])
            ->orderBy('memberships.expires_at')
            ->limit(5)
            ->get(['customers.id', 'customers.name', 'customers.phone', 'memberships.member_no', 'memberships.expires_at', 'memberships.points_balance'])
            ->map(fn ($m) => [
                'customer_id' => $m->id,
                'name' => $m->name,
                'phone' => $m->phone,
                'member_no' => $m->member_no,
                'expires_at' => $m->expires_at,
                'points_balance' => (int) $m->points_balance,
                'sisa_hari' => (int) now()->startOfDay()->diffInDays(Carbon::parse($m->expires_at), false),
            ])
            ->all();

        $grafik = $this->seriGrafik(now()->subDays(6)->startOfDay(), now()->endOfDay(), false);

        return [
            'tanggal' => $hariIni,
            'unit_hari_ini' => UnitEntry::query()->whereDate('entry_date', $hariIni)->count(),
            'checkup_hari_ini' => UnitEntry::query()->whereDate('entry_date', $hariIni)->where('type', 'checkup_only')->count(),
            'sa_berjalan' => ServiceOrder::query()->whereIn('status', ['draft', 'in_progress'])->count(),
            'sa_menunggu_bayar' => ServiceOrder::query()->where('status', 'finished')->count(),
            'sa_selesai_hari_ini' => ServiceOrder::query()->whereDate('finished_at', $hariIni)->count(),
            'omzet_hari_ini' => (int) ServiceOrder::query()->where('status', 'paid')->whereDate('paid_at', $hariIni)->sum('grand_total'),
            'omzet_bulan_ini' => (int) ServiceOrder::query()->where('status', 'paid')->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('grand_total'),
            'total_member_aktif' => DB::table('memberships')->where('status', 'active')->where('expires_at', '>=', $hariIni)->count(),
            'unit_hari_ini_daftar' => (clone $saHariIni)->latest()->limit(5)->get(['id', 'sa_no', 'customer_name', 'plate_number', 'status', 'grand_total']),
            'stok_menipis' => $stokMenipis->map(fn (Sparepart $p) => [
                'id' => $p->id, 'name' => $p->name, 'stock' => $p->stock, 'min_stock' => $p->min_stock, 'unit' => $p->unit,
            ])->all(),
            'member_akan_hangus' => $memberAkanHangus,
            'grafik_7_hari' => $grafik,
        ];
    }

    /** Daftar transaksi keuangan (untuk tabel rincian). */
    public function transaksi(?string $dari, ?string $sampai, int $batas = 200): Collection
    {
        return FinancialTransaction::query()
            ->with('pembuat:id,name')
            ->whereBetween('transaction_date', [$dari, $sampai])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit($batas)
            ->get();
    }
}