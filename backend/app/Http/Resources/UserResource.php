<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'must_change_password' => (bool) $this->must_change_password,
            'customer_id' => $this->customer_id,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
