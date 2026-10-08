<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TukarPoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reward_id' => ['required', 'integer', 'exists:rewards,id'],
            'service_order_id' => ['nullable', 'integer', 'exists:service_orders,id'],
        ];
    }

    public function messages(): array
    {
        return ['reward_id.required' => 'Reward wajib dipilih.'];
    }
}
