<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SettingService $setting) {}

    /** Dibaca semua pengguna yang login (nama bengkel, jumlah bar bensin, diskon, poin). */
    public function index(): JsonResponse
    {
        return $this->sukses($this->setting->semua(), 'OK', [
            'grup' => SettingService::GRUP,
            'kunci' => array_map(fn ($grup) => $grup, SettingService::GRUP),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $diizinkan = array_keys(SettingService::BAWAAN);

        $data = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
        ], [
            'settings.required' => 'Tidak ada pengaturan yang dikirim.',
        ]);

        $pasangan = array_intersect_key($data['settings'], array_flip($diizinkan));

        if ($pasangan === []) {
            return $this->galat('Tidak ada pengaturan yang dikenali pada permintaan ini.', 422);
        }

        $baru = $this->setting->simpanBanyak($pasangan);

        return $this->sukses($baru, 'Pengaturan berhasil disimpan.');
    }
}
