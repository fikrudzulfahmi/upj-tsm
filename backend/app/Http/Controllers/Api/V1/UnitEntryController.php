<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnitEntryResource;
use App\Models\UnitEntry;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitEntryController extends Controller
{
    use ApiResponse;

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
}
