<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->string('category', 60);
            $table->string('item_name');
            $table->enum('status', ['ok', 'perlu_perhatian', 'rusak', 'tidak_diperiksa'])->default('tidak_diperiksa');
            $table->text('note')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('service_order_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                       // snapshot
            $table->unsignedBigInteger('price');          // snapshot
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->timestamps();
        });

        Schema::create('service_order_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sparepart_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // snapshot
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('buy_price')->default(0);   // snapshot HPP
            $table->unsignedBigInteger('sell_price')->default(0);  // snapshot harga jual
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->boolean('is_free_reward')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_parts');
        Schema::dropIfExists('service_order_services');
        Schema::dropIfExists('service_order_conditions');
    }
};
