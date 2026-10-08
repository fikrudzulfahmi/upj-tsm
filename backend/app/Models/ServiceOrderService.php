<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOrderService extends Model
{
    protected $fillable = [
        'service_order_id', 'service_id', 'name', 'price', 'qty',
        'discount_percent', 'discount_amount', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer', 'qty' => 'integer', 'discount_percent' => 'integer',
            'discount_amount' => 'integer', 'subtotal' => 'integer',
        ];
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }
}
