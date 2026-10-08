<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone', 25)->nullable()->unique()->after('email');
            $table->boolean('is_active')->default(true)->after('password');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->foreignId('customer_id')->nullable()->after('must_change_password')
                ->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['phone', 'is_active', 'must_change_password']);
        });
    }
};
