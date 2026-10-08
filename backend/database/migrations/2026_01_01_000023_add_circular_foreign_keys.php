<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_entries', function (Blueprint $table) {
            $table->foreign('checkup_id')->references('id')->on('checkups')->nullOnDelete();
            $table->foreign('service_order_id')->references('id')->on('service_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('unit_entries', function (Blueprint $table) {
            $table->dropForeign(['checkup_id']);
            $table->dropForeign(['service_order_id']);
        });
    }
};
