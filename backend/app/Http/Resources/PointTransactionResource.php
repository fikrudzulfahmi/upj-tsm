<?php

namespace App\Http\Resources;

use App\Enums\PointTransactionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PointTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tipe = $this->type instanceof PointTransactionType
            ? $this->type
            : PointTransactionType::tryFrom((string) $this->type);

        return [
            'id' => $this->id,
            'type' => $tipe?->value,
            'label' => $tipe?->label(),
            'points' => (int) $this->points,
            'balance_after' => (int) $this->balance_after,
            'description' => $this->description,
            'service_order_id' => $this->service_order_id,
            'reward_id' => $this->reward_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
