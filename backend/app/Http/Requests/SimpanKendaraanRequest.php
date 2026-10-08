<?php

namespace App\Http\Requests;

use App\Services\CustomerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanKendaraanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('plate_number')) {
            $this->merge(['plate_number' => app(CustomerService::class)->normalisasiNopol($this->input('plate_number'))]);
        }
    }

    public function rules(): array
    {
        $idKendaraan = $this->route('vehicle')?->id;
        $idPelanggan = $this->route('customer')?->id ?? $this->input('customer_id');

        return [
            'customer_id' => [$idPelanggan ? 'nullable' : 'required', 'integer', 'exists:customers,id'],
            'plate_number' => ['required', 'string', 'max:15', Rule::unique('vehicles', 'plate_number')->ignore($idKendaraan)->whereNull('deleted_at')],
            'type' => ['required', Rule::in(['motor', 'mobil'])],
            'brand' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:30'],
            'engine_number' => ['nullable', 'string', 'max:50'],
            'frame_number' => ['nullable', 'string', 'max:50'],
            'last_odometer' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'plate_number.required' => 'Nomor polisi wajib diisi.',
            'plate_number.unique' => 'Nomor polisi ini sudah terdaftar.',
            'type.required' => 'Jenis kendaraan wajib dipilih.',
        ];
    }
}
