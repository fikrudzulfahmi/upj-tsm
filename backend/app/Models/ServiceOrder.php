<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ServiceOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ServiceOrder extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'sa_no', 'unit_entry_id', 'checkup_id', 'customer_id', 'vehicle_id',
        'customer_name', 'plate_number', 'phone', 'vehicle_name', 'mechanic_id',
        'odometer', 'fuel_level', 'complaint', 'vehicle_condition_notes',
        'is_member_at_entry', 'member_discount_percent',
        'subtotal_services', 'discount_services', 'total_services', 'total_parts',
        'grand_total', 'estimate_total', 'status', 'finished_at', 'paid_at',
        'payment_method', 'paid_amount', 'cancel_reason', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ServiceOrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'is_member_at_entry' => 'boolean',
            'finished_at' => 'datetime',
            'paid_at' => 'datetime',
            'odometer' => 'integer',
            'fuel_level' => 'integer',
            'member_discount_percent' => 'integer',
            'subtotal_services' => 'integer',
            'discount_services' => 'integer',
            'total_services' => 'integer',
            'total_parts' => 'integer',
            'grand_total' => 'integer',
            'estimate_total' => 'integer',
            'paid_amount' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'grand_total', 'paid_amount', 'mechanic_id'])->logOnlyDirty()->useLogName('service_order');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }

    public function unitEntry(): BelongsTo
    {
        return $this->belongsTo(UnitEntry::class);
    }

    public function checkup(): BelongsTo
    {
        return $this->belongsTo(Checkup::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(ServiceOrderService::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(ServiceOrderPart::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(ServiceOrderCondition::class)->orderBy('sort_order')->orderBy('id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}