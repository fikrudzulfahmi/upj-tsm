<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanJasaRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Service::query();

        if ($cari = $request->query('search')) {
            $query->where('name', 'like', "%{$cari}%");
        }

        if ($request->has('aktif')) {
            $query->where('is_active', $request->boolean('aktif'));
        }

        $halaman = $query->orderBy('name')->paginate((int) $request->query('per_page', 15));

        return $this->halaman($halaman, ServiceResource::class);
    }

    /** Untuk dropdown pencarian pada Form SA. */
    public function pilihan(Request $request): JsonResponse
    {
        $query = Service::query()->aktif()->orderBy('name')->limit((int) $request->query('limit', 20));

        if ($cari = $request->query('search')) {
            $query->where('name', 'like', "%{$cari}%");
        }

        return $this->sukses(ServiceResource::collection($query->get()));
    }

    public function store(SimpanJasaRequest $request): JsonResponse
    {
        $jasa = Service::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return $this->dibuat(new ServiceResource($jasa), 'Pekerjaan berhasil disimpan.');
    }

    public function show(Service $service): JsonResponse
    {
        return $this->sukses(new ServiceResource($service));
    }

    public function update(SimpanJasaRequest $request, Service $service): JsonResponse
    {
        $service->update($request->validated());

        return $this->sukses(new ServiceResource($service->fresh()), 'Data pekerjaan berhasil diperbarui.');
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return $this->sukses(null, 'Pekerjaan berhasil dihapus.');
    }
}
