<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimpanPembelianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_date' => ['required', 'date'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sparepart_id' => ['required', 'integer', 'exists:spareparts,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.buy_price' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Minimal satu item sparepart harus diisi.',
            'items.min' => 'Minimal satu item sparepart harus diisi.',
            'items.*.sparepart_id.required' => 'Sparepart pada baris item wajib dipilih.',
            'items.*.qty.min' => 'Jumlah minimal 1.',
            'items.*.buy_price.required' => 'Harga beli wajib diisi.',
        ];
    }
}
