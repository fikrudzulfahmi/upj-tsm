<?php

namespace App\Models;

use App\Enums\CheckupStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checkup extends Model
{
    use HasFactory;

    protected $fillable = [
        'checkup_no', 'unit_entry_id', 'customer_id', 'vehicle_id', 'checkup_template_id',
        'checkup_date', 'odometer', 'complaint', 'general_notes', 'result', 'status', 'created_by',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'checkup_date' => 'date',
            'status' => CheckupStatus::class,
            'odometer' => 'integer',
        ];
    }

    public function unitEntry(): BelongsTo
    {
        return $this->belongsTo(UnitEntry::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CheckupTemplate::class, 'checkup_template_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(CheckupResult::class)->orderBy('sort_order')->orderBy('id');
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', CheckupStatus::Draft->value);
    }
}
