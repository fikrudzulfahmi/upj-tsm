<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('member_no', 30)->unique();
            $table->enum('status', ['active', 'expired'])->default('active')->index();
            $table->date('started_at');
            $table->date('expires_at')->index();
            $table->date('last_service_at')->nullable();
            $table->integer('points_balance')->default(0);
            $table->unsignedTinyInteger('discount_percent_override')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
