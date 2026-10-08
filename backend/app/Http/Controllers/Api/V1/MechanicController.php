<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanMekanikRequest;
use App\Http\Resources\MechanicResource;
use App\Models\Mechanic;
use App\Models\ServiceOrder;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MechanicController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Mechanic::query();

        if ($cari = $request->query('search')) {
            $query->where('name', 'like', "%{$cari}%");
        }

        if ($request->has('aktif')) {
            $query->where('is_active', $request->boolean('aktif'));
        }

        $halaman = $query->orderBy('name')->paginate((int) $request->query('per_page', 15));

        return $this->halaman($halaman, MechanicResource::class);
    }

    public function store(SimpanMekanikRequest $request): JsonResponse
    {
        $mekanik = Mechanic::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return $this->dibuat(new MechanicResource($mekanik), 'Mekanik berhasil disimpan.');
    }

    public function show(Mechanic $mechanic): JsonResponse
    {
        return $this->sukses(new MechanicResource($mechanic));
    }

    public function update(SimpanMekanikRequest $request, Mechanic $mechanic): JsonResponse
    {
        $mechanic->update($request->validated());

        return $this->sukses(new MechanicResource($mechanic->fresh()), 'Data mekanik berhasil diperbarui.');
    }

    public function destroy(Mechanic $mechanic): JsonResponse
    {
        if (ServiceOrder::query()->where('mechanic_id', $mechanic->id)->exists()) {
            return $this->galat(
                'Mekanik ini sudah terhubung ke riwayat Form SA sehingga tidak dapat dihapus. Nonaktifkan saja.',
                422, [], 'ATURAN_BISNIS'
            );
        }

        $mechanic->delete();

        return $this->sukses(null, 'Mekanik berhasil dihapus.');
    }
}
