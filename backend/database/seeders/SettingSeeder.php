<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $dariEnv = [
            'shop_name' => env('SHOP_NAME'),
            'shop_address' => env('SHOP_ADDRESS'),
            'shop_phone' => env('SHOP_PHONE'),
        ];

        foreach (SettingService::BAWAAN as $key => $nilaiDefault) {
            $nilai = $dariEnv[$key] ?? null;

            if ($nilai === null || $nilai === '') {
                $nilai = $nilaiDefault;
            }

            $tipe = match (true) {
                is_bool($nilai) => 'bool',
                is_int($nilai) => 'int',
                default => 'string',
            };

            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_bool($nilai) ? ($nilai ? '1' : '0') : (string) $nilai,
                    'type' => $tipe,
                    'group' => SettingService::grupUntuk($key),
                    'description' => 'Pengaturan '.str_replace('_', ' ', $key),
                ]
            );
        }

        app(SettingService::class)->lupakanCache();
    }
}
