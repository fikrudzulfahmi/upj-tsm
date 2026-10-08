<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran dokumen: CU-YYYYMM-0001 (check up), SA-YYYYMM-0001 (service),
 * UE-YYYYMM-0001 (unit entry), PB-YYYYMM-0001 (pembelian part),
 * C-000001 (kode pelanggan), M-000001 (no. member).
 * Aman dari duplikasi: baris penomoran dikunci (lockForUpdate) di dalam transaksi.
 */
class DocumentNumberService
{
    public function buat(string $prefix, ?CarbonInterface $tanggal = null, int $padding = 4): string
    {
        $tanggal ??= now();
        $kunci = $prefix.'-'.$tanggal->format('Ym');
        $nomor = $this->ambilNomorBerikutnya($kunci);

        return $kunci.'-'.str_pad((string) $nomor, $padding, '0', STR_PAD_LEFT);
    }

    public function nomorUrut(string $prefix, bool $pakaiBulan = false, int $padding = 6): string
    {
        $kunci = $pakaiBulan ? $prefix.'-'.now()->format('Ym') : $prefix;
        $nomor = $this->ambilNomorBerikutnya($kunci);

        return $kunci.'-'.str_pad((string) $nomor, $padding, '0', STR_PAD_LEFT);
    }

    protected function ambilNomorBerikutnya(string $kunci): int
    {
        return DB::transaction(function () use ($kunci) {
            DB::table('document_sequences')->insertOrIgnore([
                'key' => $kunci,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $baris = DB::table('document_sequences')->where('key', $kunci)->lockForUpdate()->first();
            $berikut = ((int) $baris->last_number) + 1;

            DB::table('document_sequences')->where('key', $kunci)
                ->update(['last_number' => $berikut, 'updated_at' => now()]);

            return $berikut;
        });
    }
}
