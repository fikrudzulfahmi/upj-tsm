<?php

namespace App\Http\Requests;

use App\Enums\CheckupItemStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanCheckupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'checkup_template_id' => ['nullable', 'integer', 'exists:checkup_templates,id'],
            'checkup_date' => ['nullable', 'date'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'complaint' => ['nullable', 'string', 'max:2000'],
            'general_notes' => ['nullable', 'string', 'max:2000'],
            'results' => ['nullable', 'array'],
            'results.*.category' => ['required_with:results', 'string', 'max:60'],
            'results.*.item_name' => ['required_with:results', 'string', 'max:150'],
            'results.*.status' => ['required_with:results', Rule::in(CheckupItemStatus::nilaiValid())],
            'results.*.note' => ['nullable', 'string', 'max:500'],
            'results.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Pelanggan wajib dipilih.',
            'vehicle_id.required' => 'Kendaraan wajib dipilih.',
            'results.*.status.in' => 'Status hasil pemeriksaan tidak dikenal.',
        ];
    }
}
