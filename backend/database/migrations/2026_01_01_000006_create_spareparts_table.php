<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spareparts', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->nullable()->unique();
            $table->string('name');
            $table->string('unit', 20)->default('pcs');
            $table->unsignedBigInteger('buy_price')->default(0);
            $table->unsignedBigInteger('sell_price')->default(0);
            $table->integer('stock')->default(0);
            $table->integer('min_stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spareparts');
    }
};
