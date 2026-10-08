<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('plate_number', 15)->unique();
            $table->enum('type', ['motor', 'mobil'])->default('motor');
            $table->string('brand', 50)->nullable();
            $table->string('model', 50)->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color', 30)->nullable();
            $table->string('engine_number', 50)->nullable();
            $table->string('frame_number', 50)->nullable();
            $table->unsignedInteger('last_odometer')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'plate_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
