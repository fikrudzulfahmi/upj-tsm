<?php

namespace App\Http\Requests;

use App\Enums\CheckupResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelesaikanCheckupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'result' => ['required', Rule::in([CheckupResult::CheckupOnly->value, CheckupResult::ContinueService->value])],
        ];
    }

    public function messages(): array
    {
        return [
            'result.required' => 'Pilih hasil check up: hanya check up atau lanjut service.',
            'result.in' => 'Pilihan hasil check up tidak dikenal.',
        ];
    }
}
