<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RewardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'points_required' => (int) $this->points_required,
            'sparepart_id' => $this->sparepart_id,
            'sparepart' => $this->whenLoaded('sparepart', fn () => $this->sparepart ? [
                'id' => $this->sparepart->id,
                'name' => $this->sparepart->name,
                'unit' => $this->sparepart->unit,
                'stock' => $this->sparepart->stock,
                'buy_price' => $this->sparepart->buy_price,
            ] : null),
            'qty' => (int) $this->qty,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
