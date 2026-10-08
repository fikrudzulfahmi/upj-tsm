<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Membership extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'customer_id', 'member_no', 'status', 'started_at', 'expires_at',
        'last_service_at', 'points_balance', 'discount_percent_override',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'date',
            'expires_at' => 'date',
            'last_service_at' => 'date',
            'points_balance' => 'integer',
            'discount_percent_override' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'expires_at', 'points_balance'])->logOnlyDirty()->useLogName('membership');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class)->latest('id');
    }

    public function aktif(): bool
    {
        return $this->status === 'active' && $this->expires_at?->gte(today()) === true;
    }
}