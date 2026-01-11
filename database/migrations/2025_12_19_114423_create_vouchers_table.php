<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('uuid')->unique()->nullable();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('password');
            $table->string('profile')->index()->comment('daily_1gb, weekly_5gb, etc');
            $table->integer('validity_hours')->default(24);
            $table->integer('data_limit_mb')->nullable();
            $table->decimal('price', 15, 2);
            $table->string('currency', 3)->default('UGX');
            $table->enum('status', ['pending', 'active', 'used', 'expired', 'disabled'])->default('pending');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('sms_sent_at')->nullable();
            $table->jsonb('router_metadata')->nullable()->comment('MikroTik specific data');
            $table->jsonb('usage_stats')->nullable()->comment('Bandwidth usage, connection stats');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['code', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['payment_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index(['profile', 'status']);
            $table->index(['created_at', 'status']);
            $table->index(['expires_at', 'status']);
            $table->index('activated_at');
            $table->index('sms_sent_at');

            // PostgreSQL GIN indexes for JSONB queries
            $table->index(['router_metadata'], null, 'gin');
            $table->index(['usage_stats'], null, 'gin');
            $table->index(['metadata'], null, 'gin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
