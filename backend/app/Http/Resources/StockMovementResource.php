<?php

namespace App\Http\Resources;

use App\Enums\StockMovementType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tipe = $this->type instanceof StockMovementType ? $this->type : StockMovementType::tryFrom((string) $this->type);

        return [
            'id' => $this->id,
            'sparepart_id' => $this->sparepart_id,
            'sparepart' => $this->whenLoaded('sparepart', fn () => $this->sparepart?->name),
            'type' => $tipe?->value,
            'label' => $tipe?->label(),
            'qty' => (int) $this->qty,
            'stock_before' => (int) $this->stock_before,
            'stock_after' => (int) $this->stock_after,
            'buy_price' => (int) $this->buy_price,
            'sell_price' => (int) $this->sell_price,
            'notes' => $this->notes,
            'pembuat' => $this->whenLoaded('pembuat', fn () => $this->pembuat?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
