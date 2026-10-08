<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ServiceOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BatalkanServiceOrderRequest;
use App\Http\Requests\BayarServiceOrderRequest;
use App\Http\Requests\SimpanServiceOrderRequest;
use App\Http\Resources\ServiceOrderResource;
use App\Models\ServiceOrder;
use App\Services\ReportService;
use App\Services\ServiceOrderService;
use App\Support\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServiceOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ServiceOrderService $serviceOrder,
        private readonly ReportService $laporan,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ServiceOrder::query()->with(['customer', 'vehicle', 'mechanic']);

        if ($cari = $request->query('search')) {
            $query->where(fn ($q) => $q->where('sa_no', 'like', "%{$cari}%")
                ->orWhere('customer_name', 'like', "%{$cari}%")
                ->orWhere('plate_number', 'like', '%'.strtoupper(str_replace(' ', '', $cari)).'%'));
        }

        if ($request->filled('status')) {
            $status = explode(',', (string) $request->query('status'));
            $query->whereIn('status', $status);
        }

        if ($request->filled('dari')) {
            $query->whereDate('created_at', '>=', $request->query('dari'));
        }

        if ($request->filled('sampai')) {
            $query->whereDate('created_at', '<=', $request->query('sampai'));
        }

        $halaman = $query->latest('id')->paginate((int) $request->query('per_page', 15));

        return $this->halaman($halaman, ServiceOrderResource::class);
    }

    public function store(SimpanServiceOrderRequest $request): JsonResponse
    {
        $sa = $this->serviceOrder->buat($request->validated(), $request->user()->id);

        return $this->dibuat(new ServiceOrderResource($sa), 'Form SA berhasil dibuat.');
    }

    public function show(ServiceOrder $serviceOrder): JsonResponse
    {
        return $this->sukses(new ServiceOrderResource($serviceOrder->load($this->relasi())));
    }

    public function update(SimpanServiceOrderRequest $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $sa = $this->serviceOrder->ubah($serviceOrder, $request->validated());

        return $this->sukses(new ServiceOrderResource($sa), 'Form SA berhasil diperbarui.');
    }

    public function start(ServiceOrder $serviceOrder): JsonResponse
    {
        $sa = $this->serviceOrder->mulai($serviceOrder);

        return $this->sukses(new ServiceOrderResource($sa), 'Pengerjaan dimulai. Stok akan berkurang saat Form SA diselesaikan.');
    }

    public function finish(ServiceOrder $serviceOrder): JsonResponse
    {
        $sa = $this->serviceOrder->selesaikan($serviceOrder);

        return $this->sukses(new ServiceOrderResource($sa), 'Form SA selesai. Stok sparepart sudah dikurangi.');
    }

    public function pay(BayarServiceOrderRequest $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $sa = $this->serviceOrder->bayar(
            $serviceOrder,
            (string) $request->input('payment_method'),
            $request->filled('paid_amount') ? $request->integer('paid_amount') : null
        );

        return $this->sukses(new ServiceOrderResource($sa), 'Pembayaran berhasil dicatat.');
    }

    public function cancel(BatalkanServiceOrderRequest $request, ServiceOrder $serviceOrder): JsonResponse
    {
        $sa = $this->serviceOrder->batalkan($serviceOrder, (string) $request->input('alasan'));

        return $this->sukses(new ServiceOrderResource($sa), 'Form SA dibatalkan. Stok dan catatan keuangan sudah disesuaikan.');
    }

    /** Cetak Form SA / nota (PDF). */
    public function print(ServiceOrder $serviceOrder): Response
    {
        $sa = $serviceOrder->load($this->relasi());

        $pdf = Pdf::loadView('pdf.nota-sa', [
            'sa' => $sa,
            'bengkel' => [
                'nama' => app(\App\Services\SettingService::class)->string('shop_name', 'Bengkel'),
                'alamat' => app(\App\Services\SettingService::class)->string('shop_address'),
                'telepon' => app(\App\Services\SettingService::class)->string('shop_phone'),
            ],
            'dicetak' => now(),
        ])->setPaper('a4');

        // Ukuran berkas tetap kecil: kompresi aktif + subsetting font.
        $pdf->setOption('isFontSubsettingEnabled', true);

        // Tampilkan di tab peramban (bukan langsung terunduh) supaya pengguna
        // bisa melihat nota lebih dulu lalu mencetaknya dari penampil PDF.
        return $pdf->stream('FormSA-'.$sa->sa_no.'.pdf', ['Attachment' => false]);
    }

    /** @return array<int, string> */
    private function relasi(): array
    {
        return ['services', 'parts.sparepart', 'conditions', 'mechanic', 'customer', 'vehicle', 'unitEntry'];
    }
}