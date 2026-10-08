<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnitEntryResource;
use App\Models\Checkup;
use App\Models\ServiceOrder;
use App\Models\UnitEntry;
use App\Services\CheckupService;
use App\Services\ServiceOrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitEntryController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ServiceOrderService $serviceOrder,
        private readonly CheckupService $checkup,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = UnitEntry::query()->with(['customer', 'vehicle', 'checkup', 'serviceOrder']);

        if ($cari = $request->query('search')) {
            $query->where(fn ($q) => $q->where('entry_no', 'like', "%{$cari}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$cari}%"))
                ->orWhereHas('vehicle', fn ($v) => $v->where('plate_number', 'like', '%'.strtoupper(str_replace(' ', '', $cari)).'%')));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('dari')) {
            $query->whereDate('entry_date', '>=', $request->query('dari'));
        }

        if ($request->filled('sampai')) {
            $query->whereDate('entry_date', '<=', $request->query('sampai'));
        }

        $halaman = $query->latest('entry_date')->latest('id')->paginate((int) $request->query('per_page', 15));

        return $this->halaman($halaman, UnitEntryResource::class);
    }

    /** Hapus kunjungan (admin/owner). Dokumen yang menempel (SA / check up) ikut dihapus. */
    public function destroy(UnitEntry $unitEntry): JsonResponse
    {
        DB::transaction(function () use ($unitEntry) {
            if ($unitEntry->service_order_id) {
                $sa = ServiceOrder::find($unitEntry->service_order_id);
                if ($sa) {
                    $this->serviceOrder->hapus($sa);
                }
            }

            if ($unitEntry->checkup_id) {
                $checkup = Checkup::find($unitEntry->checkup_id);
                if ($checkup) {
                    $this->checkup->hapus($checkup);
                }
            }

            // No-op bila sudah ikut terhapus lewat SA / check up di atas.
            $unitEntry->delete();
        });

        return $this->sukses(null, 'Kunjungan beserta dokumennya dihapus.');
    }
}
