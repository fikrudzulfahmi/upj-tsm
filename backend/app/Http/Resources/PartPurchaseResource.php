<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartPurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_no' => $this->purchase_no,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'supplier_name' => $this->supplier_name,
            'total_amount' => (int) $this->total_amount,
            'notes' => $this->notes,
            'pembuat' => $this->whenLoaded('pembuat', fn () => $this->pembuat?->name),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'sparepart_id' => $i->sparepart_id,
                'sparepart' => $i->sparepart?->name,
                'qty' => (int) $i->qty,
                'buy_price' => (int) $i->buy_price,
                'subtotal' => (int) $i->subtotal,
            ])->values()),
        ];
    }
}
