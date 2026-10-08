<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'plate_number' => $this->plate_number,
            'type' => $this->type,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color,
            'engine_number' => $this->engine_number,
            'frame_number' => $this->frame_number,
            'last_odometer' => $this->last_odometer,
            'nama_lengkap' => $this->namaLengkap(),
        ];
    }
}
