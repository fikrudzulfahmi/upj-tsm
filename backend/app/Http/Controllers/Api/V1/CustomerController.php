<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimpanPelangganRequest;
use App\Http\Requests\TukarPoinRequest;
use App\Http\Resources\CheckupResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\MembershipResource;
use App\Http\Resources\PointTransactionResource;
use App\Http\Resources\ServiceOrderResource;
use App\Models\Customer;
use App\Models\Reward;
use App\Services\CustomerService;
use App\Services\MembershipService;
use App\Services\RewardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CustomerService $customerService,
        private readonly MembershipService $membershipService,
        private readonly RewardService $rewardService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()
            ->with(['vehicles', 'membership'])
            ->withCount(['unitEntries', 'serviceOrders'])
            ->cari($request->query('search'));

        if ($request->query('member') === 'aktif') {
            $query->whereHas('membership', fn ($q) => $q->where('status', 'active')->whereDate('expires_at', '>=', today()));
        } elseif ($request->query('member') === 'hangus') {
            $query->whereHas('membership', fn ($q) => $q->where('status', 'expired'));
        }

        $halaman = $query->orderBy('name')->paginate((int) $request->query('per_page', 15));

        return $this->halaman($halaman, CustomerResource::class);
    }

    public function store(SimpanPelangganRequest $request): JsonResponse
    {
        $customer = $this->customerService->simpan($request->validated(), $request->input('vehicles', []));

        return $this->dibuat(new CustomerResource($customer), 'Pelanggan berhasil disimpan.');
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load(['vehicles', 'membership'])->loadCount(['unitEntries', 'serviceOrders']);

        return $this->sukses(new CustomerResource($customer));
    }

    public function update(SimpanPelangganRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->customerService->simpan($request->validated(), $request->input('vehicles', []), $customer);

        return $this->sukses(new CustomerResource($customer), 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return $this->sukses(null, 'Pelanggan berhasil dihapus.');
    }

    /* ----------------------------------------------------------- membership */

    /** Jadikan member + buat akun portal; password awal ditampilkan SEKALI. */
    public function jadikanMember(Customer $customer): JsonResponse
    {
        $hasil = $this->membershipService->aktifkan($customer);

        return $this->dibuat([
            'membership' => new MembershipResource($hasil['membership']),
            'password_awal' => $hasil['password_awal'],
            'login' => $customer->fresh()->phone,
        ], 'Pelanggan berhasil dijadikan member.');
    }

    /** Perpanjang manual (mis. perpanjangan tanpa servis). */
    public function perpanjangMember(Customer $customer): JsonResponse
    {
        $membership = $customer->membership;

        if (! $membership) {
            return $this->galat('Pelanggan ini belum terdaftar sebagai member.', 422, [], 'ATURAN_BISNIS');
        }

        $membership = $this->membershipService->perpanjangManual($customer);

        return $this->sukses([
            'membership' => new MembershipResource($membership),
            'password_awal' => null,
        ], 'Masa membership berhasil diperpanjang.');
    }

    /* ----------------------------------------------------------------- poin */

    public function points(Request $request, Customer $customer): JsonResponse
    {
        $membership = $customer->membership;

        $riwayat = $customer->pointTransactions()
            ->paginate((int) $request->query('per_page', 15));

        return $this->sukses(
            PointTransactionResource::collection($riwayat->items()),
            'OK',
            [
                'halaman' => $riwayat->currentPage(),
                'per_halaman' => $riwayat->perPage(),
                'total' => $riwayat->total(),
                'total_halaman' => $riwayat->lastPage(),
                'saldo' => $membership?->points_balance ?? 0,
                'membership' => $membership ? new MembershipResource($membership) : null,
            ]
        );
    }

    public function redeem(TukarPoinRequest $request, Customer $customer): JsonResponse
    {
        $reward = Reward::with('sparepart')->findOrFail($request->integer('reward_id'));

        $hasil = $this->rewardService->tukar($customer, $reward);

        return $this->dibuat([
            'redemption' => [
                'id' => $hasil->id,
                'reward' => $hasil->reward?->name,
                'points_used' => (int) $hasil->points_used,
                'qty' => (int) $hasil->qty,
                'sparepart' => $hasil->sparepart?->name,
                'redeemed_at' => $hasil->redeemed_at?->toISOString(),
            ],
            'saldo_poin' => $customer->fresh()->membership?->points_balance ?? 0,
        ], 'Poin berhasil ditukar.');
    }

    /* -------------------------------------------------------------- riwayat */

    public function history(Customer $customer): JsonResponse
    {
        $checkups = $customer->checkups()
            ->with(['results', 'vehicle', 'unitEntry'])
            ->latest('checkup_date')
            ->limit(50)
            ->get();

        $serviceOrders = $customer->serviceOrders()
            ->with(['services', 'parts', 'mechanic'])
            ->latest('id')
            ->limit(50)
            ->get();

        return $this->sukses([
            'checkups' => CheckupResource::collection($checkups),
            'service_orders' => ServiceOrderResource::collection($serviceOrders),
        ]);
    }
}