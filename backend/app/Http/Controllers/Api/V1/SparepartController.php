<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PenyesuaianStokRequest;
use App\Http\Requests\SimpanPembelianRequest;
use App\Http\Requests\SimpanSparepartRequest;
use App\Http\Resources\PartPurchaseResource;
use App\Http\Resources\SparepartResource;
use App\Http\Resources\StockMovementResource;
use App\Models\PartPurchase;
use App\Models\PartPurchaseItem;
use App\Models\Sparepart;
use App\Models\StockMovement;
use App\Services\DocumentNumberService;
use App\Services\FinanceService;
use App\Services\StockService;
use App\Enums\FinancialCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SparepartController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly StockService $stok,
        private readonly FinanceService $keuangan,
        private readonly DocumentNumberService $nomor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Sparepart::query();

        if ($cari = $request->query('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$cari}%")->orWhere('sku', 'like', "%{$cari}%"));
        }

        if ($request->boolean('menipis')) {
            $query->menipis();
        }

        if ($request->has('aktif')) {
            $query->where('is_active', $request->boolean('aktif'));
        }

        $halaman = $query->orderBy('name')->paginate((int) $request->query('per_page', 15));

        return $this->halaman($halaman, SparepartResource::class);
    }

    /** Untuk dropdown pencarian (tanpa paginasi, item aktif saja). */
    public function pilihan(Request $request): JsonResponse
    {
        $query = Sparepart::query()->aktif()->orderBy('name')->limit((int) $request->query('limit', 20));

        if ($cari = $request->query('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$cari}%")->orWhere('sku', 'like', "%{$cari}%"));
        }

        return $this->sukses(SparepartResource::collection($query->get()));
    }

    public function store(SimpanSparepartRequest $request): JsonResponse
    {
        $sparepart = Sparepart::create($request->validated() + [
            'stock' => 0,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->dibuat(
            new SparepartResource($sparepart->fresh()),
            'Sparepart berhasil disimpan. Stok awal diisi lewat Input Sparepart atau Penyesuaian Stok.'
        );
    }

    public function show(Sparepart $sparepart): JsonResponse
    {
        return $this->sukses(new SparepartResource($sparepart));
    }

    /** Stok/master part; `stock` sengaja tidak diterima di sini (harus lewat movement). */
    public function update(SimpanSparepartRequest $request, Sparepart $sparepart): JsonResponse
    {
        $sparepart->update($request->validated());

        return $this->sukses(new SparepartResource($sparepart->fresh()), 'Data sparepart berhasil diperbarui.');
    }

    public function destroy(Sparepart $sparepart): JsonResponse
    {
        if (DB::table('service_order_parts')->where('sparepart_id', $sparepart->id)->exists()) {
            return $this->galat(
                'Sparepart ini sudah pernah dipakai pada Form SA sehingga tidak dapat dihapus. Nonaktifkan saja.',
                422, [], 'ATURAN_BISNIS'
            );
        }

        $sparepart->delete();

        return $this->sukses(null, 'Sparepart berhasil dihapus.');
    }

    /* ------------------------------------------------------- pembelian (in) */

    public function pembelian(Request $request): JsonResponse
    {
        $query = PartPurchase::query()->with(['items.sparepart', 'pembuat']);

        if ($request->filled('dari')) {
            $query->whereDate('purchase_date', '>=', $request->query('dari'));
        }
        if ($request->filled('sampai')) {
            $query->whereDate('purchase_date', '<=', $request->query('sampai'));
        }

        $halaman = $query->latest('purchase_date')->latest('id')->paginate((int) $request->query('per_page', 15));

        return $this->halaman($halaman, PartPurchaseResource::class);
    }

    /** Input sparepart: stok bertambah, movement `in`, dan SATU pengeluaran pembelian_part. */
    public function simpanPembelian(SimpanPembelianRequest $request): JsonResponse
    {
        $data = $request->validated();

        $purchase = DB::transaction(function () use ($data) {
            $total = 0;
            foreach ($data['items'] as $item) {
                $total += (int) $item['qty'] * (int) $item['buy_price'];
            }

            $purchase = PartPurchase::create([
                'purchase_no' => $this->nomor->buat('PB', \Illuminate\Support\Carbon::parse($data['purchase_date'])),
                'purchase_date' => $data['purchase_date'],
                'supplier_name' => $data['supplier_name'] ?? null,
                'total_amount' => $total,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $item) {
                $sparepart = Sparepart::findOrFail($item['sparepart_id']);

                PartPurchaseItem::create([
                    'part_purchase_id' => $purchase->id,
                    'sparepart_id' => $sparepart->id,
                    'qty' => (int) $item['qty'],
                    'buy_price' => (int) $item['buy_price'],
                    'subtotal' => (int) $item['qty'] * (int) $item['buy_price'],
                ]);

                $this->stok->increase($sparepart, (int) $item['qty'], [
                    'reference_type' => PartPurchase::class,
                    'reference_id' => $purchase->id,
                    'buy_price' => (int) $item['buy_price'],
                    'notes' => "Pembelian {$purchase->purchase_no}",
                ]);
            }

            $this->keuangan->expense($data['purchase_date'], FinancialCategory::PembelianSparepart, $total, [
                'reference_type' => PartPurchase::class,
                'reference_id' => $purchase->id,
                'description' => 'Pembelian sparepart '.$purchase->purchase_no
                    .(! empty($data['supplier_name']) ? " dari {$data['supplier_name']}" : ''),
            ]);

            return $purchase;
        });

        return $this->dibuat(new PartPurchaseResource($purchase->load('items.sparepart')), 'Pembelian sparepart berhasil disimpan. Stok dan pengeluaran telah diperbarui.');
    }

    public function detailPembelian(PartPurchase $partPurchase): JsonResponse
    {
        return $this->sukses(new PartPurchaseResource($partPurchase->load('items.sparepart', 'pembuat')));
    }

    /* ------------------------------------------------------------ penyesuaian */

    public function penyesuaian(PenyesuaianStokRequest $request): JsonResponse
    {
        $sparepart = Sparepart::findOrFail($request->integer('sparepart_id'));

        $movement = $this->stok->adjust(
            $sparepart,
            $request->integer('stock_baru'),
            (string) $request->input('alasan')
        );

        return $this->sukses(new StockMovementResource($movement), 'Penyesuaian stok berhasil dicatat.');
    }

    public function kartuStok(Request $request): JsonResponse
    {
        $query = StockMovement::query()->with(['sparepart', 'pembuat']);

        if ($request->filled('sparepart_id')) {
            $query->where('sparepart_id', $request->integer('sparepart_id'));
        }

        $halaman = $query->latest('id')->paginate((int) $request->query('per_page', 20));

        return $this->halaman($halaman, StockMovementResource::class);
    }
}