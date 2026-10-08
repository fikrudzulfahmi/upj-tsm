<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\ServiceOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceOrderResource;
use App\Models\ServiceOrder;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Modul KASIR — dipisah dari Form SA.
 *
 * Form SA hanya mencatat pekerjaan (jasa/part/mekanik) sampai berstatus `selesai`.
 * Pembayaran, nota/invoice, dan riwayat penerimaan uang ditangani di sini,
 * dan hanya untuk Form SA yang SUDAH SELESAI.
 */
class KasirController extends Controller
{
    use ApiResponse;

    /** Daftar tagihan: SA berstatus selesai & belum dibayar (urutan FIFO). */
    public function tagihan(Request $request): JsonResponse
    {
        $query = ServiceOrder::query()
            ->with($this->relasi())
            ->where('status', ServiceOrderStatus::Finished->value);

        $this->saring($query, $request);

        $halaman = $query->orderBy('finished_at')->orderBy('id')
            ->paginate((int) $request->query('per_page', 15));

        return $this->sukses(ServiceOrderResource::collection($halaman->items()), 'OK', [
            'halaman' => $halaman->currentPage(),
            'per_halaman' => $halaman->perPage(),
            'total' => $halaman->total(),
            'total_halaman' => $halaman->lastPage(),
            'ringkasan' => $this->ringkasanTagihan(),
        ]);
    }

    public function ringkasan(): JsonResponse
    {
        return $this->sukses($this->ringkasanTagihan());
    }

    /** Riwayat kasir: SA yang sudah dibayar (+ rekap per metode pembayaran). */
    public function transaksi(Request $request): JsonResponse
    {
        $dari = $request->query('dari') ?: today()->toDateString();
        $sampai = $request->query('sampai') ?: today()->toDateString();

        $query = ServiceOrder::query()
            ->with($this->relasi())
            ->where('status', ServiceOrderStatus::Paid->value)
            ->whereBetween('paid_at', [$dari.' 00:00:00', $sampai.' 23:59:59']);

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->query('payment_method'));
        }

        if ($cari = $request->query('search')) {
            $query->where(fn ($q) => $q->where('sa_no', 'like', "%{$cari}%")
                ->orWhere('customer_name', 'like', "%{$cari}%")
                ->orWhere('plate_number', 'like', '%'.strtoupper(str_replace(' ', '', $cari)).'%'));
        }

        $halaman = $query->orderByDesc('paid_at')->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 15));

        return $this->sukses(ServiceOrderResource::collection($halaman->items()), 'OK', [
            'halaman' => $halaman->currentPage(),
            'per_halaman' => $halaman->perPage(),
            'total' => $halaman->total(),
            'total_halaman' => $halaman->lastPage(),
            'dari' => $dari,
            'sampai' => $sampai,
            'rekap' => $this->rekapPeriode($dari, $sampai),
        ]);
    }

    /* ------------------------------------------------------------- helper */

    private function saring($query, Request $request): void
    {
        if ($cari = $request->query('search')) {
            $query->where(fn ($q) => $q->where('sa_no', 'like', "%{$cari}%")
                ->orWhere('customer_name', 'like', "%{$cari}%")
                ->orWhere('plate_number', 'like', '%'.strtoupper(str_replace(' ', '', $cari)).'%'));
        }

        if ($request->filled('dari')) {
            $query->whereDate('finished_at', '>=', $request->query('dari'));
        }

        if ($request->filled('sampai')) {
            $query->whereDate('finished_at', '<=', $request->query('sampai'));
        }
    }

    /** @return array<string, mixed> */
    private function ringkasanTagihan(): array
    {
        $belumBayar = ServiceOrder::query()->where('status', ServiceOrderStatus::Finished->value);

        $hariIni = ServiceOrder::query()->where('status', ServiceOrderStatus::Paid->value)
            ->whereDate('paid_at', today());

        return [
            'jumlah_tagihan' => (clone $belumBayar)->count(),
            'total_tagihan' => (int) (clone $belumBayar)->sum('grand_total'),
            'tagihan_tertua' => (clone $belumBayar)->min('finished_at'),
            'tagihan_hari_ini' => (clone $belumBayar)->whereDate('finished_at', today())->count(),
            'jumlah_dibayar_hari_ini' => (clone $hariIni)->count(),
            'dibayar_hari_ini' => (int) (clone $hariIni)->sum('grand_total'),
            'per_metode_hari_ini' => $this->rekapMetode(clone $hariIni),
        ];
    }

    /** @return array<string, mixed> */
    private function rekapPeriode(string $dari, string $sampai): array
    {
        $query = ServiceOrder::query()->where('status', ServiceOrderStatus::Paid->value)
            ->whereBetween('paid_at', [$dari.' 00:00:00', $sampai.' 23:59:59']);

        return [
            'jumlah_transaksi' => (clone $query)->count(),
            'total' => (int) (clone $query)->sum('grand_total'),
            'per_metode' => $this->rekapMetode(clone $query),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function rekapMetode($query): array
    {
        $baris = $query
            ->selectRaw('payment_method, COUNT(*) as jumlah, SUM(grand_total) as total')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $hasil = [];

        foreach (PaymentMethod::cases() as $metode) {
            $item = $baris->get($metode->value);

            $hasil[] = [
                'metode' => $metode->value,
                'label' => $metode->label(),
                'jumlah' => (int) ($item->jumlah ?? 0),
                'total' => (int) ($item->total ?? 0),
            ];
        }

        return $hasil;
    }

    /** @return array<int, string> */
    private function relasi(): array
    {
        return ['services', 'parts.sparepart', 'conditions', 'mechanic', 'customer', 'vehicle', 'unitEntry'];
    }
}
