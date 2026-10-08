<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SelesaikanCheckupRequest;
use App\Http\Requests\SimpanCheckupRequest;
use App\Http\Resources\CheckupResource;
use App\Http\Resources\ServiceOrderResource;
use App\Models\Checkup;
use App\Services\CheckupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckupController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CheckupService $checkupService) {}

    public function index(Request $request): JsonResponse
    {
        $query = Checkup::query()
            ->with(['customer', 'vehicle', 'unitEntry'])
            ->withCount('results');

        if ($cari = $request->query('search')) {
            $query->where(fn ($q) => $q->where('checkup_no', 'like', "%{$cari}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$cari}%")->orWhere('phone', 'like', "%{$cari}%"))
                ->orWhereHas('vehicle', fn ($v) => $v->where('plate_number', 'like', '%'.strtoupper(str_replace(' ', '', $cari)).'%')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('dari')) {
            $query->whereDate('checkup_date', '>=', $request->query('dari'));
        }

        if ($request->filled('sampai')) {
            $query->whereDate('checkup_date', '<=', $request->query('sampai'));
        }

        $halaman = $query->latest('checkup_date')->latest('id')->paginate((int) $request->query('per_page', 15));

        return $this->halaman($halaman, CheckupResource::class);
    }

    public function store(SimpanCheckupRequest $request): JsonResponse
    {
        $checkup = $this->checkupService->simpan($request->validated(), null, $request->user()->id);

        return $this->dibuat(new CheckupResource($checkup), 'Check up berhasil disimpan.');
    }

    public function show(Checkup $checkup): JsonResponse
    {
        $checkup->load(['results', 'customer', 'vehicle', 'template', 'unitEntry']);

        return $this->sukses(new CheckupResource($checkup));
    }

    public function update(SimpanCheckupRequest $request, Checkup $checkup): JsonResponse
    {
        $checkup = $this->checkupService->simpan($request->validated(), $checkup, $request->user()->id);

        return $this->sukses(new CheckupResource($checkup), 'Check up berhasil diperbarui.');
    }

    /** Selesaikan: `checkup_only` atau `continue_service` (membuat SA draft otomatis). */
    public function finish(SelesaikanCheckupRequest $request, Checkup $checkup): JsonResponse
    {
        $hasil = $this->checkupService->selesaikan($checkup, $request->input('result'), $request->user()->id);

        return $this->sukses([
            'checkup' => new CheckupResource($hasil['checkup']),
            'service_order' => $hasil['service_order'] ? new ServiceOrderResource($hasil['service_order']) : null,
            'service_order_id' => $hasil['service_order']?->id,
        ], $hasil['service_order']
            ? 'Check up selesai dan Form SA sudah dibuat otomatis.'
            : 'Check up selesai.');
    }
}
