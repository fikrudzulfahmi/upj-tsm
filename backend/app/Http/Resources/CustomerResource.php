<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'address' => $this->address,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'vehicles' => VehicleResource::collection($this->whenLoaded('vehicles')),
            'membership' => $this->whenLoaded('membership', fn () => $this->membership
                ? new MembershipResource($this->membership)
                : null),
            'jumlah_unit' => $this->whenCounted('unitEntries'),
            'jumlah_sa' => $this->whenCounted('serviceOrders'),
        ];
    }
}
