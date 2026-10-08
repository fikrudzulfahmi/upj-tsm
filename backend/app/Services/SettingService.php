<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    private const KUNCI_CACHE = 'bengkel:settings';

    /** Pengaturan default — dipakai bila baris belum ada di database. */
    public const BAWAAN = [
        'shop_name' => 'Bengkel Motor',
        'shop_address' => '',
        'shop_phone' => '',
        'membership_duration_months' => 6,
        'member_discount_percent' => 10,
        'points_per_amount' => 10000,
        'member_points_expire_with_membership' => true,
        'checkup_extends_membership' => false,
        'auto_member_after_first_service' => false,
        'fuel_bar_count' => 8,
        'record_redeem_cost' => true,
        'update_buy_price_on_purchase' => true,
        'service_reminder_days' => 90,
    ];

    /** Kelompok pengaturan (untuk halaman Pengaturan). */
    public const GRUP = [
        'identitas' => ['shop_name', 'shop_address', 'shop_phone'],
        'membership' => [
            'membership_duration_months', 'member_discount_percent', 'points_per_amount',
            'member_points_expire_with_membership', 'checkup_extends_membership',
            'auto_member_after_first_service', 'record_redeem_cost',
        ],
        'operasional' => ['fuel_bar_count', 'update_buy_price_on_purchase', 'service_reminder_days'],
    ];

    /** @return array<string, mixed> */
    public function semua(): array
    {
        return Cache::rememberForever(self::KUNCI_CACHE, function () {
            $nilai = self::BAWAAN;

            foreach (Setting::query()->get() as $baris) {
                $nilai[$baris->key] = $this->cast($baris->value, $baris->type);
            }

            return $nilai;
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $semua = $this->semua();

        return $semua[$key] ?? $default ?? (self::BAWAAN[$key] ?? null);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool) $this->get($key, $default);
    }

    public function string(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->simpanBanyak([$key => $value]);
    }

    /**
     * Simpan banyak pengaturan sekaligus.
     *
     * @param  array<string, mixed>  $pasangan
     * @return array<string, mixed>
     */
    public function simpanBanyak(array $pasangan): array
    {
        foreach ($pasangan as $key => $value) {
            $tipe = $this->tebakTipe($value);
            $baris = Setting::query()->firstOrNew(['key' => $key]);
            $baris->type = $baris->exists ? $baris->type : $tipe;
            $baris->value = $this->keString($value, $baris->type);
            $baris->group = $this->grupUntuk($key);
            $baris->save();
        }

        $this->lupakanCache();

        return $this->semua();
    }

    public function lupakanCache(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }

    public static function grupUntuk(string $key): string
    {
        foreach (self::GRUP as $grup => $keys) {
            if (in_array($key, $keys, true)) {
                return $grup;
            }
        }

        return 'umum';
    }

    private function tebakTipe(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_array($value) => 'json',
            default => 'string',
        };
    }

    private function keString(mixed $value, string $tipe): string
    {
        return match ($tipe) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    private function cast(?string $nilai, string $tipe): mixed
    {
        if ($nilai === null) {
            return null;
        }

        return match ($tipe) {
            'int' => (int) $nilai,
            'bool' => in_array($nilai, ['1', 'true', 'on', 'ya'], true),
            'json' => json_decode($nilai, true),
            default => $nilai,
        };
    }
}
