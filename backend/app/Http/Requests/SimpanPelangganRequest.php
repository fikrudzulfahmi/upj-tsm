<?php

namespace App\Http\Requests;

use App\Models\Vehicle;
use App\Services\CustomerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanPelangganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && $this->input('phone') !== null) {
            $this->merge(['phone' => app(CustomerService::class)->normalisasiHp($this->input('phone'))]);
        }

        $kendaraan = $this->input('vehicles');
        if (is_array($kendaraan)) {
            $this->merge([
                'vehicles' => array_map(function ($k) {
                    if (! empty($k['plate_number'])) {
                        $k['plate_number'] = app(CustomerService::class)->normalisasiNopol($k['plate_number']);
                    }

                    return $k;
                }, $kendaraan),
            ]);
        }
    }

    public function rules(): array
    {
        $idPelanggan = $this->route('customer')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'phone' => ['nullable', 'string', 'max:25', Rule::unique('customers', 'phone')->ignore($idPelanggan)->whereNull('deleted_at')],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
            'vehicles' => ['nullable', 'array'],
            'vehicles.*.id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'vehicles.*.plate_number' => ['required_with:vehicles', 'string', 'max:15'],
            'vehicles.*.type' => ['nullable', Rule::in(['motor', 'mobil'])],
            'vehicles.*.brand' => ['nullable', 'string', 'max:50'],
            'vehicles.*.model' => ['nullable', 'string', 'max:50'],
            'vehicles.*.year' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'vehicles.*.color' => ['nullable', 'string', 'max:30'],
            'vehicles.*.engine_number' => ['nullable', 'string', 'max:50'],
            'vehicles.*.frame_number' => ['nullable', 'string', 'max:50'],
            'vehicles.*.last_odometer' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** Nopol harus unik di seluruh kendaraan (termasuk milik pelanggan lain). */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            foreach ((array) $this->input('vehicles', []) as $i => $k) {
                if (empty($k['plate_number'])) {
                    continue;
                }

                $bentrok = Vehicle::query()
                    ->where('plate_number', $k['plate_number'])
                    ->when(! empty($k['id']), fn ($q) => $q->whereKeyNot($k['id']))
                    ->first();

                if ($bentrok) {
                    $v->errors()->add("vehicles.{$i}.plate_number", "Nomor polisi {$k['plate_number']} sudah terdaftar pada pelanggan lain.");
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama pelanggan wajib diisi.',
            'phone.unique' => 'Nomor HP ini sudah dipakai pelanggan lain.',
            'vehicles.*.plate_number.required_with' => 'Nomor polisi kendaraan wajib diisi.',
        ];
    }
}
