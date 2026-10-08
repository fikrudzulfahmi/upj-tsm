<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->string('sa_no', 30)->unique();
            $table->foreignId('unit_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('checkup_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            // SNAPSHOT data pelanggan/kendaraan saat transaksi
            $table->string('customer_name');
            $table->string('plate_number', 15);
            $table->string('phone', 25)->nullable();
            $table->string('vehicle_name')->nullable();
            $table->foreignId('mechanic_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('odometer')->nullable();
            $table->unsignedTinyInteger('fuel_level')->default(0);
            $table->text('complaint')->nullable();
            $table->text('vehicle_condition_notes')->nullable();
            $table->boolean('is_member_at_entry')->default(false);
            $table->unsignedTinyInteger('member_discount_percent')->default(0);
            $table->unsignedBigInteger('subtotal_services')->default(0);
            $table->unsignedBigInteger('discount_services')->default(0);
            $table->unsignedBigInteger('total_services')->default(0);
            $table->unsignedBigInteger('total_parts')->default(0);
            $table->unsignedBigInteger('grand_total')->default(0);
            $table->unsignedBigInteger('estimate_total')->default(0);
            $table->enum('status', ['draft', 'in_progress', 'finished', 'paid', 'cancelled'])
                ->default('draft')->index();
            $table->dateTime('finished_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->enum('payment_method', ['cash', 'transfer', 'qris'])->nullable();
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
