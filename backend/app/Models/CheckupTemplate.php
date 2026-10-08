<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckupTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'vehicle_type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CheckupTemplateItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Item aktif saja (dipakai saat membuat form check up). */
    public function itemsAktif(): HasMany
    {
        return $this->items()->where('is_active', true);
    }
}
