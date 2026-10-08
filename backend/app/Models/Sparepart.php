<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Sparepart extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'sku', 'name', 'unit', 'buy_price', 'sell_price', 'stock', 'min_stock', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'buy_price' => 'integer',
            'sell_price' => 'integer',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'buy_price', 'sell_price', 'min_stock', 'is_active'])->logOnlyDirty()->useLogName('sparepart');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeMenipis($query)
    {
        return $query->whereColumn('stock', '<=', 'min_stock');
    }

    public function nilaiPersediaan(): int
    {
        return $this->stock * $this->buy_price;
    }
}