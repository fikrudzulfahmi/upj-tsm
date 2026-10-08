<?php

namespace App\Http\Resources;

use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $persenGlobal = app(SettingService::class)->int('member_discount_percent', 10);
        $persenEfektif = $this->member_discount_percent ?? $persenGlobal;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => (int) $this->price,
            'member_discount_percent' => $this->member_discount_percent,
            'diskon_efektif' => (int) $persenEfektif,
            'harga_member' => (int) round($this->price * (100 - $persenEfektif) / 100),
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
