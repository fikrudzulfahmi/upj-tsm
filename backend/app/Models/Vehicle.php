<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id', 'plate_number', 'type', 'brand', 'model', 'year', 'color',
        'engine_number', 'frame_number', 'last_odometer',
    ];

    protected function casts(): array
    {
        return ['year' => 'integer', 'last_odometer' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function namaLengkap(): string
    {
        return trim(implode(' ', array_filter([$this->brand, $this->model]))) ?: ucfirst($this->type);
    }
}
