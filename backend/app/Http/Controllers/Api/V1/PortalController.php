<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Checkup;
use App\Models\ServiceOrder;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Portal member — SEMUA query difilter `customer_id` milik user yang login.
 * Menebak ID milik member lain harus menghasilkan 404 (bukan data orang lain).
 */
class PortalController extends Controller
{
    use ApiResponse;

    public function profile(Request $request): JsonResponse
    {
        $customer = $this->pelanggan($request);

        if (! $customer) {
            return $this->galat('Akun ini tidak terhubung ke data pelanggan.', 403, [], 'TIDAK_BERWENANG');
        }

        $customer->load(['vehicles', 'membership']);

        return $this->sukses([
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
            ],
            'membership' => $customer->membership ? [
                'member_no' => $customer->membership->member_no,
                'status' => $customer->membership->status,
                'aktif' => $customer->membership->aktif(),
                'started_at' => $customer->membership->started_at?->toDateString(),
                'expires_at' => $customer->membership->expires_at?->toDateString(),
                'sisa_hari' => $customer->membership->expires_at
                    ? (int) now()->startOfDay()->diffInDays($customer->membership->expires_at->startOfDay(), false)
                    : null,
                'points_balance' => (int) $customer->membership->points_balance,
            ] : null,
            'jumlah_kendaraan' => $customer->vehicles->count(),
        ]);
    }

    public function vehicles(Request $request): JsonResponse
    {
        $customer = $this->pelanggan($request);

        if (! $customer) {
            return $this->sukses([]);
        }

        return $this->sukses($customer->vehicles->map(fn ($v) => [
            'id' => $v->id,
            'plate_number' => $v->plate_number,
            'type' => $v->type,
            'brand' => $v->brand,
            'model' => $v->model,
            'year' => $v->year,
            'color' => $v->color,
            'last_odometer' => $v->last_odometer,
        ])->values());
    }

    public function history(Request $request): JsonResponse
    {
        $customer = $this->pelanggan($request);

        if (! $customer) {
            return $this->sukses(['checkups' => [], 'service_orders' => [], 'membership' => null]);
        }

        $checkups = Checkup::query()
            ->where('customer_id', $customer->id)
            ->with(['vehicle', 'unitEntry'])
            ->withCount('results')
            ->latest('checkup_date')
            ->limit(50)
            ->get()
            ->map(fn (Checkup $c) => [
                'id' => $c->id,
                'checkup_no' => $c->checkup_no,
                'checkup_date' => $c->checkup_date?->toDateString(),
                'plate_number' => $c->vehicle?->plate_number,
                'kendaraan' => $c->vehicle?->namaLengkap(),
                'status' => $c->status?->value,
                'result' => $c->result,
                'jumlah_item' => $c->results_count,
                'lanjut_service' => (bool) $c->unitEntry?->service_order_id,
                'service_order_id' => $c->unitEntry?->service_order_id,
            ]);

        $serviceOrders = ServiceOrder::query()
            ->where('customer_id', $customer->id)
            ->with(['mechanic', 'vehicle'])
            ->withCount(['services', 'parts'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (ServiceOrder $sa) => [
                'id' => $sa->id,
                'sa_no' => $sa->sa_no,
                'tanggal' => $sa->created_at?->toDateString(),
                'plate_number' => $sa->plate_number,
                'kendaraan' => $sa->vehicle_name,
                'mekanik' => $sa->mechanic?->name,
                'status' => $sa->status?->value,
                'status_label' => $sa->status?->label(),
                'total' => $sa->status?->value === 'paid' ? (int) $sa->grand_total : null,
                'total_estimasi' => (int) $sa->grand_total,
                'jumlah_jasa' => $sa->services_count,
                'jumlah_part' => $sa->parts_count,
            ]);

        return $this->sukses([
            'checkups' => $checkups,
            'service_orders' => $serviceOrders,
        ]);
    }

    /** Detail hasil tanpa HPP/harga beli (bagian 9 blueprint). */
    public function detail(Request $request, string $tipe, int $id): JsonResponse
    {
        $customer = $this->pelanggan($request);

        if (! $customer) {
            return $this->galat('Akun ini tidak terhubung ke data pelanggan.', 403, [], 'TIDAK_BERWENANG');
        }

        if ($tipe === 'checkup') {
            $checkup = Checkup::query()
                ->where('customer_id', $customer->id)
                ->whereKey($id)
                ->with(['results', 'vehicle', 'template'])
                ->firstOrFail();

            return $this->sukses([
                'tipe' => 'checkup',
                'checkup_no' => $checkup->checkup_no,
                'checkup_date' => $checkup->checkup_date?->toDateString(),
                'plate_number' => $checkup->vehicle?->plate_number,
                'kendaraan' => $checkup->vehicle?->namaLengkap(),
                'odometer' => $checkup->odometer,
                'complaint' => $checkup->complaint,
                'general_notes' => $checkup->general_notes,
                'status' => $checkup->status?->value,
                'result' => $checkup->result,
                'hasil' => $checkup->results->map(fn ($r) => [
                    'category' => $r->category,
                    'item_name' => $r->item_name,
                    'status' => $r->status,
                    'note' => $r->note,
                ])->values(),
            ]);
        }

        if ($tipe === 'service') {
            $sa = ServiceOrder::query()
                ->where('customer_id', $customer->id)
                ->whereKey($id)
                ->with(['services', 'parts', 'conditions', 'mechanic', 'vehicle'])
                ->firstOrFail();

            return $this->sukses([
                'tipe' => 'service',
                'sa_no' => $sa->sa_no,
                'tanggal' => $sa->created_at?->toDateString(),
                'plate_number' => $sa->plate_number,
                'kendaraan' => $sa->vehicle_name,
                'mekanik' => $sa->mechanic?->name,
                'odometer' => $sa->odometer,
                'fuel_level' => (int) $sa->fuel_level,
                'complaint' => $sa->complaint,
                'status' => $sa->status?->value,
                'status_label' => $sa->status?->label(),
                'total_jasa' => (int) $sa->total_services,
                'total_part' => (int) $sa->total_parts,
                'total' => (int) $sa->grand_total,
                'pekerjaan' => $sa->services->map(fn ($s) => [
                    'nama' => $s->name,
                    'qty' => (int) $s->qty,
                    'harga' => (int) $s->price,
                    'diskon' => (int) $s->discount_amount,
                    'subtotal' => (int) $s->subtotal,
                ])->values(),
                // Tanpa buy_price/HPP: hanya nama, qty, harga jual
                'sparepart' => $sa->parts->map(fn ($p) => [
                    'nama' => $p->name,
                    'qty' => (int) $p->qty,
                    'harga' => (int) $p->sell_price,
                    'subtotal' => (int) $p->subtotal,
                    'gratis' => (bool) $p->is_free_reward,
                ])->values(),
                'kondisi' => $sa->conditions->map(fn ($c) => [
                    'category' => $c->category,
                    'item_name' => $c->item_name,
                    'status' => $c->status,
                    'note' => $c->note,
                ])->values(),
            ]);
        }

        return $this->galat('Jenis riwayat tidak dikenal.', 404, [], 'TIDAK_DITEMUKAN');
    }

    private function pelanggan(Request $request): ?\App\Models\Customer
    {
        $id = $request->user()?->customer_id;

        return $id ? \App\Models\Customer::find($id) : null;
    }
}
