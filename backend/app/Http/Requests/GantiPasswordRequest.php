<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class GantiPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $wajibLama = ! $this->user()?->must_change_password;

        return [
            'password_lama' => [$wajibLama ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'password_lama.required' => 'Password lama wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak sama.',
            'password.min' => 'Password baru minimal 8 karakter.',
        ];
    }
}
