<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanItemTemplateRequest;
use App\Http\Requests\SimpanTemplateRequest;
use App\Http\Resources\CheckupTemplateResource;
use App\Models\CheckupTemplate;
use App\Models\CheckupTemplateItem;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckupTemplateController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = CheckupTemplate::query()->with('items')->withCount('items');

        if ($request->filled('vehicle_type')) {
            $query->where(fn ($q) => $q->where('vehicle_type', $request->query('vehicle_type'))->orWhereNull('vehicle_type'));
        }

        return $this->sukses(CheckupTemplateResource::collection($query->orderBy('name')->get()));
    }

    /** Template efektif untuk sebuah jenis kendaraan (dipakai form Check Up). */
    public function untukKendaraan(Request $request): JsonResponse
    {
        $tipe = $request->query('vehicle_type', 'motor');

        $template = CheckupTemplate::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('vehicle_type', $tipe)->orWhereNull('vehicle_type'))
            ->with('itemsAktif')
            ->orderByRaw('vehicle_type is null')
            ->first();

        return $this->sukses($template ? new CheckupTemplateResource($template->load('items')->loadCount('items')) : null);
    }

    public function store(SimpanTemplateRequest $request): JsonResponse
    {
        $template = CheckupTemplate::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return $this->dibuat(new CheckupTemplateResource($template->load('items')), 'Template check up berhasil disimpan.');
    }

    public function show(CheckupTemplate $checkupTemplate): JsonResponse
    {
        return $this->sukses(new CheckupTemplateResource($checkupTemplate->load('items')->loadCount('items')));
    }

    public function update(SimpanTemplateRequest $request, CheckupTemplate $checkupTemplate): JsonResponse
    {
        $checkupTemplate->update($request->validated());

        return $this->sukses(new CheckupTemplateResource($checkupTemplate->fresh()->load('items')), 'Template berhasil diperbarui.');
    }

    public function destroy(CheckupTemplate $checkupTemplate): JsonResponse
    {
        $checkupTemplate->delete();

        return $this->sukses(null, 'Template berhasil dihapus.');
    }

    /* -------------------------------------------------------------- item */

    public function storeItem(SimpanItemTemplateRequest $request, CheckupTemplate $checkupTemplate): JsonResponse
    {
        $urut = $request->integer('sort_order') ?: ((int) $checkupTemplate->items()->max('sort_order')) + 1;

        $item = $checkupTemplate->items()->create($request->validated() + [
            'sort_order' => $urut,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->dibuat([
            'id' => $item->id,
            'category' => $item->category,
            'name' => $item->name,
            'sort_order' => (int) $item->sort_order,
            'is_active' => (bool) $item->is_active,
        ], 'Item pemeriksaan berhasil ditambahkan.');
    }

    public function updateItem(SimpanItemTemplateRequest $request, CheckupTemplate $checkupTemplate, CheckupTemplateItem $item): JsonResponse
    {
        abort_if((int) $item->checkup_template_id !== (int) $checkupTemplate->id, 404, 'Item tidak ditemukan pada template ini.');

        $item->update($request->validated());

        return $this->sukses([
            'id' => $item->id,
            'category' => $item->category,
            'name' => $item->name,
            'sort_order' => (int) $item->sort_order,
            'is_active' => (bool) $item->is_active,
        ], 'Item pemeriksaan berhasil diperbarui.');
    }

    public function destroyItem(CheckupTemplate $checkupTemplate, CheckupTemplateItem $item): JsonResponse
    {
        abort_if((int) $item->checkup_template_id !== (int) $checkupTemplate->id, 404, 'Item tidak ditemukan pada template ini.');

        $item->delete();

        return $this->sukses(null, 'Item pemeriksaan berhasil dihapus.');
    }

    /** Urutkan ulang item: body { items: [id, id, ...] } mengikuti urutan baru. */
    public function reorderItem(Request $request, CheckupTemplate $checkupTemplate): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer', 'exists:checkup_template_items,id'],
        ], [
            'items.required' => 'Urutan item wajib dikirim.',
        ]);

        foreach ($data['items'] as $urutan => $id) {
            CheckupTemplateItem::query()
                ->where('checkup_template_id', $checkupTemplate->id)
                ->whereKey($id)
                ->update(['sort_order' => $urutan + 1]);
        }

        return $this->sukses(new CheckupTemplateResource($checkupTemplate->fresh()->load('items')), 'Urutan item berhasil disimpan.');
    }
}
