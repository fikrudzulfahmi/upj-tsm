<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Format respons JSON konsisten: { success, message, data, meta }.
 */
trait ApiResponse
{
    protected function sukses(mixed $data = null, string $pesan = 'OK', array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $pesan,
            'data' => $data,
            'meta' => $meta === [] ? null : $meta,
        ], $status);
    }

    protected function dibuat(mixed $data = null, string $pesan = 'Data berhasil disimpan.'): JsonResponse
    {
        return $this->sukses($data, $pesan, [], 201);
    }

    protected function galat(string $pesan, int $status = 400, array $errors = [], ?string $kode = null): JsonResponse
    {
        $muatan = [
            'success' => false,
            'message' => $pesan,
            'data' => null,
            'meta' => null,
        ];

        if ($kode) {
            $muatan['code'] = $kode;
        }
        if ($errors !== []) {
            $muatan['errors'] = $errors;
        }

        return response()->json($muatan, $status);
    }

    /** Bungkus data paginator menjadi data + meta standar. */
    protected function halaman($paginator, $resourceKelas = null): JsonResponse
    {
        $koleksi = $resourceKelas ? $resourceKelas::collection($paginator->items()) : collect($paginator->items());

        return $this->sukses($koleksi, 'OK', [
            'halaman' => $paginator->currentPage(),
            'per_halaman' => $paginator->perPage(),
            'total' => $paginator->total(),
            'total_halaman' => $paginator->lastPage(),
        ]);
    }
}
