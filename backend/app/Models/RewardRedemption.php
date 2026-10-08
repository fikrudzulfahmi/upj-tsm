<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardRedemption extends Model
{
    protected $fillable = [
        'customer_id', 'reward_id', 'points_used', 'service_order_id',
        'sparepart_id', 'qty', 'cost_amount', 'redeemed_by', 'redeemed_at',
    ];

    protected function casts(): array
    {
        return ['points_used' => 'integer', 'qty' => 'integer', 'cost_amount' => 'integer', 'redeemed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function sparepart(): BelongsTo
    {
        return $this->belongsTo(Sparepart::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }
}
