<?php

namespace App\Http\Resources;

use App\Enums\FinancialCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'type' => $this->type?->value ?? $this->type,
            'category' => $this->category,
            'label_kategori' => FinancialCategory::tryFrom((string) $this->category)?->label() ?? $this->category,
            'amount' => (int) $this->amount,
            'description' => $this->description,
            'reversal_of' => $this->reversal_of,
            'pembuat' => $this->whenLoaded('pembuat', fn () => $this->pembuat?->name),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
