<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanKendaraanRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Customer;
use App\Models\ServiceOrder;
use App\Models\Vehicle;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class VehicleController extends Controller
{
    use ApiResponse;

    public function store(SimpanKendaraanRequest $request, Customer $customer): JsonResponse
    {
        $kendaraan = $customer->vehicles()->create($request->validated());

        return $this->dibuat(new VehicleResource($kendaraan), 'Kendaraan berhasil ditambahkan.');
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        return $this->sukses(new VehicleResource($vehicle->load('customer')));
    }

    public function update(SimpanKendaraanRequest $request, Vehicle $vehicle): JsonResponse
    {
        $vehicle->update($request->validated());

        return $this->sukses(new VehicleResource($vehicle->fresh()), 'Data kendaraan berhasil diperbarui.');
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $punyaRiwayat = ServiceOrder::query()->where('vehicle_id', $vehicle->id)->exists();

        if ($punyaRiwayat) {
            return $this->galat(
                'Kendaraan ini sudah punya riwayat servis sehingga tidak dapat dihapus. '
                .'Nonaktifkan saja lewat data pelanggan bila sudah tidak dipakai.',
                422,
                [],
                'ATURAN_BISNIS'
            );
        }

        $vehicle->delete();

        return $this->sukses(null, 'Kendaraan berhasil dihapus.');
    }
}
