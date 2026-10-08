<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sisa = $this->expires_at ? (int) now()->startOfDay()->diffInDays($this->expires_at->startOfDay(), false) : null;

        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'member_no' => $this->member_no,
            'status' => $this->status,
            'aktif' => (bool) $this->aktif(),
            'started_at' => $this->started_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'last_service_at' => $this->last_service_at?->toDateString(),
            'sisa_hari' => $sisa,
            'points_balance' => (int) $this->points_balance,
            'discount_percent_override' => $this->discount_percent_override,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
