<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = ['key', 'last_number'];

    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }
}
