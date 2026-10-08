<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kolom tabel yang menyimpan kondisi item pemeriksaan. */
    private const TABEL = ['checkup_results', 'service_order_conditions'];

    private const NILAI = ['ok', 'perlu_perhatian', 'rusak', 'tidak_diperiksa'];

    /**
     * Default kondisi item menjadi "Tidak diperiksa".
     *
     * Sebelumnya default DB adalah "ok", sehingga baris yang dibuat tanpa status
     * dianggap sudah diperiksa padahal belum. Aplikasi sudah mengirim status
     * eksplisit; default ini hanya jaring pengaman untuk baris tanpa status.
     */
    public function up(): void
    {
        $this->ubahDefault('tidak_diperiksa');
    }

    public function down(): void
    {
        $this->ubahDefault('ok');
    }

    private function ubahDefault(string $default): void
    {
        foreach (self::TABEL as $tabel) {
            if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, 'status')) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) use ($default) {
                $table->enum('status', self::NILAI)->default($default)->change();
            });
        }
    }
};
