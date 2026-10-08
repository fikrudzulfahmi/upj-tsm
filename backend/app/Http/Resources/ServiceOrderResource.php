<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sa_no' => $this->sa_no,
            'unit_entry_id' => $this->unit_entry_id,
            'checkup_id' => $this->checkup_id,
            'customer_id' => $this->customer_id,
            'vehicle_id' => $this->vehicle_id,
            'customer_name' => $this->customer_name,
            'plate_number' => $this->plate_number,
            'phone' => $this->phone,
            'vehicle_name' => $this->vehicle_name,
            'mechanic_id' => $this->mechanic_id,
            'odometer' => $this->odometer,
            'fuel_level' => (int) $this->fuel_level,
            'complaint' => $this->complaint,
            'vehicle_condition_notes' => $this->vehicle_condition_notes,
            'is_member_at_entry' => (bool) $this->is_member_at_entry,
            'member_discount_percent' => (int) $this->member_discount_percent,
            'subtotal_services' => (int) $this->subtotal_services,
            'discount_services' => (int) $this->discount_services,
            'total_services' => (int) $this->total_services,
            'total_parts' => (int) $this->total_parts,
            'grand_total' => (int) $this->grand_total,
            'estimate_total' => (int) $this->estimate_total,
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status?->label(),
            'finished_at' => $this->finished_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'payment_method' => $this->payment_method?->value,
            'paid_amount' => (int) $this->paid_amount,
            'cancel_reason' => $this->cancel_reason,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'mechanic' => new MechanicResource($this->whenLoaded('mechanic')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'unit_entry' => $this->whenLoaded('unitEntry', fn () => $this->unitEntry ? [
                'id' => $this->unitEntry->id,
                'entry_no' => $this->unitEntry->entry_no,
                'entry_date' => $this->unitEntry->entry_date?->toDateString(),
                'type' => $this->unitEntry->type?->value,
            ] : null),
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($s) => [
                'id' => $s->id,
                'service_id' => $s->service_id,
                'name' => $s->name,
                'price' => (int) $s->price,
                'qty' => (int) $s->qty,
                'discount_percent' => (int) $s->discount_percent,
                'discount_amount' => (int) $s->discount_amount,
                'subtotal' => (int) $s->subtotal,
            ])->values()),
            'parts' => $this->whenLoaded('parts', fn () => $this->parts->map(fn ($p) => [
                'id' => $p->id,
                'sparepart_id' => $p->sparepart_id,
                'name' => $p->name,
                'qty' => (int) $p->qty,
                'buy_price' => (int) $p->buy_price,
                'sell_price' => (int) $p->sell_price,
                'subtotal' => (int) $p->subtotal,
                'is_free_reward' => (bool) $p->is_free_reward,
                'stok_tersedia' => $p->relationLoaded('sparepart') ? $p->sparepart?->stock : null,
            ])->values()),
            'conditions' => $this->whenLoaded('conditions', fn () => $this->conditions->map(fn ($c) => [
                'id' => $c->id,
                'category' => $c->category,
                'item_name' => $c->item_name,
                'status' => $c->status,
                'note' => $c->note,
                'sort_order' => (int) $c->sort_order,
            ])->values()),
        ];
    }
}
