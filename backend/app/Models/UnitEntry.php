<?php

namespace App\Models;

use App\Enums\UnitEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitEntry extends Model
{
    protected $fillable = [
        'entry_no', 'entry_date', 'customer_id', 'vehicle_id', 'type',
        'has_checkup', 'checkup_id', 'service_order_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['entry_date' => 'date', 'type' => UnitEntryType::class, 'has_checkup' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function checkup(): BelongsTo
    {
        return $this->belongsTo(Checkup::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }
}
