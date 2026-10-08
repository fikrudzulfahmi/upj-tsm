<?php

namespace App\Http\Requests;

use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maksBar = app(SettingService::class)->int('fuel_bar_count', 8);

        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
            'checkup_id' => ['nullable', 'integer', 'exists:checkups,id'],
            'unit_entry_id' => ['nullable', 'integer', 'exists:unit_entries,id'],
            'mechanic_id' => ['nullable', 'integer', 'exists:mechanics,id'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'fuel_level' => ['nullable', 'integer', 'min:0', 'max:'.$maksBar],
            'complaint' => ['nullable', 'string', 'max:2000'],
            'vehicle_condition_notes' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'services' => ['nullable', 'array'],
            'services.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'services.*.name' => ['required_without:services.*.service_id', 'nullable', 'string', 'max:150'],
            'services.*.price' => ['required_without:services.*.service_id', 'nullable', 'integer', 'min:0'],
            'services.*.qty' => ['nullable', 'integer', 'min:1', 'max:999'],

            'parts' => ['nullable', 'array'],
            'parts.*.sparepart_id' => ['required', 'integer', 'exists:spareparts,id'],
            'parts.*.qty' => ['required', 'integer', 'min:1', 'max:9999'],
            'parts.*.is_free_reward' => ['nullable', 'boolean'],

            'conditions' => ['nullable', 'array'],
            'conditions.*.category' => ['required_with:conditions', 'string', 'max:60'],
            'conditions.*.item_name' => ['required_with:conditions', 'string', 'max:150'],
            'conditions.*.status' => ['required_with:conditions', Rule::in(['ok', 'perlu_perhatian', 'rusak', 'tidak_diperiksa'])],
            'conditions.*.note' => ['nullable', 'string', 'max:500'],
            'conditions.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Pelanggan wajib dipilih.',
            'vehicle_id.required' => 'Kendaraan wajib dipilih.',
            'fuel_level.max' => 'Level bensin melebihi jumlah bar yang diizinkan.',
            'services.*.name.required_without' => 'Nama jasa wajib diisi bila bukan dari master.',
            'parts.*.sparepart_id.required' => 'Sparepart wajib dipilih.',
            'parts.*.qty.min' => 'Jumlah sparepart minimal 1.',
        ];
    }
}
