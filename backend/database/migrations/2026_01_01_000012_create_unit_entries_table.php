<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_no', 30)->unique();
            $table->date('entry_date')->index();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['checkup_only', 'service'])->default('checkup_only')->index();
            $table->boolean('has_checkup')->default(false);
            // FK melingkar (unit_entries <-> checkups/service_orders) ditambahkan
            // di migrasi terakhir setelah kedua tabel itu ada.
            $table->unsignedBigInteger('checkup_id')->nullable();
            $table->unsignedBigInteger('service_order_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_entries');
    }
};
