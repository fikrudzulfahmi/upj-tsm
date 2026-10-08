<?php

namespace App\Models;

use App\Services\DocumentNumberService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Customer extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['code', 'name', 'gender', 'phone', 'address', 'notes'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['code', 'name', 'phone', 'address'])->logOnlyDirty()->useLogName('customer');
    }

    protected static function booted(): void
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->code)) {
                $customer->code = app(DocumentNumberService::class)->nomorUrut('C', false, 6);
            }
        });
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class)->orderBy('plate_number');
    }

    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class)->latest('id');
    }

    public function unitEntries(): HasMany
    {
        return $this->hasMany(UnitEntry::class);
    }

    public function checkups(): HasMany
    {
        return $this->hasMany(Checkup::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function scopeCari($query, ?string $kata)
    {
        if (! $kata) {
            return $query;
        }

        return $query->where(function ($q) use ($kata) {
            $q->where('name', 'like', "%{$kata}%")
                ->orWhere('phone', 'like', "%{$kata}%")
                ->orWhere('code', 'like', "%{$kata}%")
                ->orWhereHas('vehicles', fn ($v) => $v->where('plate_number', 'like', '%'.strtoupper(str_replace(' ', '', $kata)).'%'));
        });
    }
}