<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PenyesuaianStokRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sparepart_id' => ['required', 'integer', 'exists:spareparts,id'],
            'stock_baru' => ['required', 'integer', 'min:0'],
            'alasan' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'sparepart_id.required' => 'Sparepart wajib dipilih.',
            'stock_baru.required' => 'Stok baru wajib diisi.',
            'alasan.required' => 'Alasan penyesuaian stok wajib diisi.',
            'alasan.min' => 'Alasan penyesuaian minimal 3 karakter.',
        ];
    }
}
