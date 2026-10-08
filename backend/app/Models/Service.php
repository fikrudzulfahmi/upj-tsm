<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Service extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'price', 'member_discount_percent', 'is_active', 'description'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'member_discount_percent' => 'integer', 'is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'price', 'member_discount_percent', 'is_active'])->logOnlyDirty()->useLogName('service');
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    /** Harga setelah diskon member efektif (persen service → setting global). */
    public function hargaMember(?int $persenGlobal = null): int
    {
        $persen = $this->member_discount_percent ?? $persenGlobal ?? 0;

        return (int) round($this->price * (100 - $persen) / 100);
    }
}