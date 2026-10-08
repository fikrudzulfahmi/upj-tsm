<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckupTemplateItem extends Model
{
    protected $fillable = ['checkup_template_id', 'category', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CheckupTemplate::class, 'checkup_template_id');
    }
}
