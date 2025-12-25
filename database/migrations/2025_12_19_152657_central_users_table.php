<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('uuid')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('user');
            $table->boolean('is_super_admin')->default(false);
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index(['email', 'is_active']);
            $table->index(['role', 'is_active']);
            $table->index('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_users');
    }
};
