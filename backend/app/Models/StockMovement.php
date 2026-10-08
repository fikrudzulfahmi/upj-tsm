<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    protected $fillable = [
        'sparepart_id', 'type', 'qty', 'stock_before', 'stock_after',
        'buy_price', 'sell_price', 'reference_type', 'reference_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'qty' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
            'buy_price' => 'integer',
            'sell_price' => 'integer',
        ];
    }

    public function sparepart(): BelongsTo
    {
        return $this->belongsTo(Sparepart::class);
    }

    public function referensi(): MorphTo
    {
        return $this->morphTo('reference');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
