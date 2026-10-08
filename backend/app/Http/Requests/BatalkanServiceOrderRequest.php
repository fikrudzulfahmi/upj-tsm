<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatalkanServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'alasan.required' => 'Alasan pembatalan wajib diisi.',
            'alasan.min' => 'Alasan pembatalan minimal 3 karakter.',
        ];
    }
}
