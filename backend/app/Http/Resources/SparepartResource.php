<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SparepartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'unit' => $this->unit,
            'buy_price' => (int) $this->buy_price,
            'sell_price' => (int) $this->sell_price,
            'stock' => (int) $this->stock,
            'min_stock' => (int) $this->min_stock,
            'nilai_persediaan' => $this->nilaiPersediaan(),
            'menipis' => $this->stock <= $this->min_stock,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
