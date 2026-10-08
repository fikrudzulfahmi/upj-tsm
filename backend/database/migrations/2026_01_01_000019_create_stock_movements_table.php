<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sparepart_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'adjust', 'return']);
            $table->unsignedInteger('qty');            // selalu positif; arah lewat `type`
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->unsignedBigInteger('buy_price')->default(0);   // snapshot
            $table->unsignedBigInteger('sell_price')->default(0);  // snapshot
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sparepart_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
