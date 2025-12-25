<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('uuid')->unique()->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'staff'])->default('staff');
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // Indexes
            $table->index(['email', 'is_active']);
            $table->index(['role', 'is_active']);
            $table->index('last_login_at');

            // PostgreSQL GIN index for JSONB queries
            $table->index(['metadata'], null, 'gin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
