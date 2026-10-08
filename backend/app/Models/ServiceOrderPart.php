<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderPart extends Model
{
    protected $fillable = [
        'service_order_id', 'sparepart_id', 'name', 'qty',
        'buy_price', 'sell_price', 'subtotal', 'is_free_reward',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer', 'buy_price' => 'integer', 'sell_price' => 'integer',
            'subtotal' => 'integer', 'is_free_reward' => 'boolean',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function sparepart(): BelongsTo
    {
        return $this->belongsTo(Sparepart::class);
    }
}
