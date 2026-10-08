<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkup_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkup_id')->constrained()->cascadeOnDelete();
            $table->string('category', 60);
            $table->string('item_name');
            $table->enum('status', ['ok', 'perlu_perhatian', 'rusak', 'tidak_diperiksa'])->default('tidak_diperiksa');
            $table->text('note')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkup_results');
    }
};
