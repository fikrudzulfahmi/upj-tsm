<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimpanJasaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'integer', 'min:0'],
            'member_discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama pekerjaan wajib diisi.',
            'price.required' => 'Harga pekerjaan wajib diisi.',
            'member_discount_percent.max' => 'Persen diskon maksimal 100.',
        ];
    }
}
