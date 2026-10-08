<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'points_required', 'sparepart_id', 'qty', 'is_active'];

    protected function casts(): array
    {
        return ['points_required' => 'integer', 'qty' => 'integer', 'is_active' => 'boolean'];
    }

    public function sparepart(): BelongsTo
    {
        return $this->belongsTo(Sparepart::class);
    }
}
