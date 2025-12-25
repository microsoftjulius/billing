<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('uuid')->unique()->nullable();
            $table->enum('level', ['debug', 'info', 'warning', 'error', 'critical'])->default('info');
            $table->string('module')->index()->comment('payment, voucher, sms, etc');
            $table->string('action')->index();
            $table->text('message');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('data')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['level', 'created_at']);
            $table->index(['module', 'action']);
            $table->index(['module', 'level', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');

            // PostgreSQL GIN indexes for JSONB queries
            $table->index(['data'], null, 'gin');
            $table->index(['metadata'], null, 'gin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
