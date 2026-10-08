<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Hapus unit entry yatim (tidak terhubung ke check up maupun Form SA).
        // Sisa dari penghapusan Form SA/check up yang sebelumnya tidak membersihkan kunjungannya.
        DB::table('unit_entries')
            ->whereNull('checkup_id')
            ->whereNull('service_order_id')
            ->delete();
    }

    public function down(): void
    {
        // Pembersihan data tidak bisa di-rollback.
    }
};
