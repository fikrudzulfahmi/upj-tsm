<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckupTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'vehicle_type' => $this->vehicle_type,
            'is_active' => (bool) $this->is_active,
            'jumlah_item' => $this->whenCounted('items'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'category' => $i->category,
                'name' => $i->name,
                'sort_order' => (int) $i->sort_order,
                'is_active' => (bool) $i->is_active,
            ])->values()),
        ];
    }
}
