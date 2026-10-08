<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkup_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkup_template_id')->constrained()->cascadeOnDelete();
            $table->string('category', 60);
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['checkup_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkup_template_items');
    }
};
