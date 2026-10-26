<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('requester')->index();
            $table->string('department', 100)->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['role', 'department', 'is_active']);
        });
    }
};
