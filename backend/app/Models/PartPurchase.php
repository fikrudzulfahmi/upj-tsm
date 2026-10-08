<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartPurchase extends Model
{
    protected $fillable = ['purchase_no', 'purchase_date', 'supplier_name', 'total_amount', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'total_amount' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PartPurchaseItem::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
