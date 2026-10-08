<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_no' => $this->entry_no,
            'entry_date' => $this->entry_date?->toDateString(),
            'customer_id' => $this->customer_id,
            'vehicle_id' => $this->vehicle_id,
            'type' => $this->type?->value ?? $this->type,
            'has_checkup' => (bool) $this->has_checkup,
            'checkup_id' => $this->checkup_id,
            'service_order_id' => $this->service_order_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'checkup_no' => $this->whenLoaded('checkup', fn () => $this->checkup?->checkup_no),
            'sa_no' => $this->whenLoaded('serviceOrder', fn () => $this->serviceOrder?->sa_no),
            'status_sa' => $this->whenLoaded('serviceOrder', fn () => $this->serviceOrder?->status?->value),
            'grand_total' => $this->whenLoaded('serviceOrder', fn () => $this->serviceOrder?->grand_total),
        ];
    }
}
