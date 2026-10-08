<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartPurchaseItem extends Model
{
    protected $fillable = ['part_purchase_id', 'sparepart_id', 'qty', 'buy_price', 'subtotal'];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'buy_price' => 'integer', 'subtotal' => 'integer'];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(PartPurchase::class, 'part_purchase_id');
    }

    public function sparepart(): BelongsTo
    {
        return $this->belongsTo(Sparepart::class);
    }
}
