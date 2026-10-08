<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_no', 30)->unique();
            $table->date('purchase_date')->index();
            $table->string('supplier_name')->nullable();
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('part_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sparepart_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('buy_price');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_purchase_items');
        Schema::dropIfExists('part_purchases');
    }
};
