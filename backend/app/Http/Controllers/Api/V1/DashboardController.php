<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ReportService $laporan) {}

    public function summary(): JsonResponse
    {
        return $this->sukses($this->laporan->dashboard());
    }

    /** Endpoint kesehatan sederhana (dipakai monitoring / cron). */
    public function health(): JsonResponse
    {
        return $this->sukses([
            'waktu' => now()->toIso8601String(),
            'aplikasi' => config('app.name'),
            'database' => rescue(fn () => \Illuminate\Support\Facades\DB::connection()->getPdo() ? 'ok' : 'gagal', 'gagal', false),
        ], 'Aplikasi berjalan normal.');
    }

    /** Iseng: pastikan /api/v1/ping tersedia untuk cek CORS dari peramban. */
    public function ping(): JsonResponse
    {
        return $this->sukses(['pong' => true], 'OK');
    }
}
