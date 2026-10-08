<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['checkups', 'service_orders', 'customers'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->string('idempotency_key', 64)->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (['checkups', 'service_orders', 'customers'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
