<?php

namespace App\Models;

use App\Enums\FinancialType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends Model
{
    protected $fillable = [
        'transaction_date', 'type', 'category', 'amount',
        'reference_type', 'reference_id', 'description', 'reversal_of', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'type' => FinancialType::class,
            'amount' => 'integer',
        ];
    }

    public function asalPembalik(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
