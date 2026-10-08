<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanRewardRequest;
use App\Http\Resources\RewardResource;
use App\Models\Reward;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Reward::query()->with('sparepart');

        if ($request->has('aktif')) {
            $query->where('is_active', $request->boolean('aktif'));
        }

        $halaman = $query->orderBy('points_required')->paginate((int) $request->query('per_page', 15));

        return $this->halaman($halaman, RewardResource::class);
    }

    public function store(SimpanRewardRequest $request): JsonResponse
    {
        $reward = Reward::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return $this->dibuat(new RewardResource($reward->load('sparepart')), 'Reward berhasil disimpan.');
    }

    public function show(Reward $reward): JsonResponse
    {
        return $this->sukses(new RewardResource($reward->load('sparepart')));
    }

    public function update(SimpanRewardRequest $request, Reward $reward): JsonResponse
    {
        $reward->update($request->validated());

        return $this->sukses(new RewardResource($reward->fresh('sparepart')), 'Data reward berhasil diperbarui.');
    }

    public function destroy(Reward $reward): JsonResponse
    {
        $reward->delete();

        return $this->sukses(null, 'Reward berhasil dihapus.');
    }
}
