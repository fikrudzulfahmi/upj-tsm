<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'checkup_no' => $this->checkup_no,
            'unit_entry_id' => $this->unit_entry_id,
            'customer_id' => $this->customer_id,
            'vehicle_id' => $this->vehicle_id,
            'checkup_template_id' => $this->checkup_template_id,
            'checkup_date' => $this->checkup_date?->toDateString(),
            'odometer' => $this->odometer,
            'complaint' => $this->complaint,
            'general_notes' => $this->general_notes,
            'result' => $this->result,
            'status' => $this->status?->value ?? $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'template' => $this->whenLoaded('template', fn () => $this->template ? [
                'id' => $this->template->id,
                'name' => $this->template->name,
                'vehicle_type' => $this->template->vehicle_type,
            ] : null),
            'unit_entry' => $this->whenLoaded('unitEntry', fn () => $this->unitEntry ? [
                'id' => $this->unitEntry->id,
                'entry_no' => $this->unitEntry->entry_no,
                'type' => $this->unitEntry->type?->value,
                'service_order_id' => $this->unitEntry->service_order_id,
            ] : null),
            'results' => $this->whenLoaded('results', fn () => $this->results->map(fn ($r) => [
                'id' => $r->id,
                'category' => $r->category,
                'item_name' => $r->item_name,
                'status' => $r->status,
                'note' => $r->note,
                'sort_order' => (int) $r->sort_order,
            ])->values()),
        ];
    }
}
