<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SimpanUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:25', Rule::unique('users', 'phone')->ignore($id)],
            'password' => [$id ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama pengguna wajib diisi.',
            'email.unique' => 'Email sudah dipakai pengguna lain.',
            'phone.unique' => 'Nomor HP sudah dipakai pengguna lain.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'role.required' => 'Peran wajib dipilih.',
        ];
    }
}
