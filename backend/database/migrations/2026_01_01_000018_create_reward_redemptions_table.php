<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reward_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points_used');
            $table->foreignId('service_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sparepart_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedBigInteger('cost_amount')->default(0);
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('redeemed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_redemptions');
    }
};
