<?php

namespace App\Http\Requests;

use App\Enums\FinancialCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanPengeluaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'category' => ['required', Rule::in(FinancialCategory::untukPengeluaranManual())],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => 'Kategori pengeluaran wajib dipilih.',
            'category.in' => 'Kategori pengeluaran tidak dikenal.',
            'amount.min' => 'Nominal pengeluaran harus lebih dari nol.',
            'description.required' => 'Keterangan pengeluaran wajib diisi.',
        ];
    }
}
