<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckupResult extends Model
{
    protected $fillable = ['checkup_id', 'category', 'item_name', 'status', 'note', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function checkup(): BelongsTo
    {
        return $this->belongsTo(Checkup::class);
    }
}
