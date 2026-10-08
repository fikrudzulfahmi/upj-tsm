<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanSparepartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('sparepart')?->id;

        return [
            'sku' => ['nullable', 'string', 'max:50', Rule::unique('spareparts', 'sku')->ignore($id)->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'unit' => ['required', 'string', 'max:20'],
            'buy_price' => ['required', 'integer', 'min:0'],
            'sell_price' => ['required', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            // `stock` hanya berubah lewat pergerakan stok (input sparepart/penyesuaian).
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama sparepart wajib diisi.',
            'unit.required' => 'Satuan wajib diisi.',
            'buy_price.required' => 'Harga beli wajib diisi.',
            'sell_price.required' => 'Harga jual wajib diisi.',
            'sku.unique' => 'Kode SKU sudah dipakai.',
        ];
    }
}
