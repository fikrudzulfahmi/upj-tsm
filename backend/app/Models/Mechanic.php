<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mechanic extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
