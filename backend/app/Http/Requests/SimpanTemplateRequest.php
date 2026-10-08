<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'vehicle_type' => ['nullable', Rule::in(['motor', 'mobil'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'Nama template wajib diisi.'];
    }
}
