<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkups', function (Blueprint $table) {
            $table->id();
            $table->string('checkup_no', 30)->unique();
            $table->foreignId('unit_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checkup_template_id')->nullable()->constrained()->nullOnDelete();
            $table->date('checkup_date')->index();
            $table->unsignedInteger('odometer')->nullable();
            $table->text('complaint')->nullable();
            $table->text('general_notes')->nullable();
            $table->enum('result', ['checkup_only', 'continue_service'])->nullable();
            $table->enum('status', ['draft', 'completed'])->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkups');
    }
};
