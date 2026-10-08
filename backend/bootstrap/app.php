<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Exceptions\AturanBisnisException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'aktif' => \App\Http\Middleware\PastikanAkunAktif::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // Kontrak galat JSON konsisten: { success, message, code, errors? }
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            [$status, $kode, $pesan] = match (true) {
                $e instanceof ValidationException => [
                    422, 'DATA_TIDAK_VALID', $e->getMessage() ?: 'Data yang dikirim tidak valid.',
                ],
                $e instanceof AturanBisnisException => [
                    422, $e->kodeAturan(), $e->getMessage(),
                ],
                $e instanceof AuthenticationException => [
                    401, 'TIDAK_TERAUTENTIKASI', 'Sesi berakhir, silakan masuk kembali.',
                ],
                $e instanceof AuthorizationException => [
                    403, 'TIDAK_BERWENANG', $e->getMessage() ?: 'Anda tidak berwenang melakukan aksi ini.',
                ],
                $e instanceof ModelNotFoundException => [
                    404, 'TIDAK_DITEMUKAN', 'Data tidak ditemukan.',
                ],
                $e instanceof NotFoundHttpException => [
                    404, 'TIDAK_DITEMUKAN', 'Endpoint atau data tidak ditemukan.',
                ],
                default => null,
            };

            if ($status === null && $e instanceof HttpExceptionInterface) {
                $http = $e->getStatusCode();
                [$status, $kode, $pesan] = match ($http) {
                    401 => [401, 'TIDAK_TERAUTENTIKASI', 'Sesi berakhir, silakan masuk kembali.'],
                    403 => [403, 'TIDAK_BERWENANG', $e->getMessage() ?: 'Anda tidak berwenang melakukan aksi ini.'],
                    404 => [404, 'TIDAK_DITEMUKAN', 'Data tidak ditemukan.'],
                    405 => [405, 'METODE_TIDAK_DIIZINKAN', 'Metode permintaan tidak diizinkan untuk endpoint ini.'],
                    419 => [419, 'SESI_KEDALUWARSA', 'Sesi kedaluwarsa, silakan muat ulang.'],
                    429 => [429, 'TERLALU_BANYAK_PERCOBAAN', 'Terlalu banyak percobaan. Coba beberapa saat lagi.'],
                    default => null,
                };
            }

            if ($status === null) {
                return null; // biarkan Laravel menangani (500 dan lainnya)
            }

            $muatan = [
                'success' => false,
                'message' => $pesan,
                'code' => $kode,
                'data' => null,
            ];

            if ($e instanceof ValidationException) {
                $muatan['errors'] = $e->errors();
                $muatan['message'] = collect($e->errors())->flatten()->first() ?: $muatan['message'];
            }

            return response()->json($muatan, $status);
        });
    })->create();
