<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimpanRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'points_required' => ['required', 'integer', 'min:1'],
            'sparepart_id' => ['nullable', 'integer', 'exists:spareparts,id'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama reward wajib diisi.',
            'points_required.required' => 'Jumlah poin reward wajib diisi.',
        ];
    }
}
