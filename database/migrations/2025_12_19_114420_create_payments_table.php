<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('uuid')->unique()->nullable();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('transaction_id')->unique();
            $table->string('reference')->nullable()->comment('Gateway reference');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('UGX');
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'cancelled'])->default('pending');
            $table->string('payment_method')->default('mobile_money');
            $table->string('provider')->default('collectug');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->jsonb('gateway_response')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['customer_id', 'status']);
            $table->index(['transaction_id', 'status']);
            $table->index(['reference', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['amount', 'status']);
            $table->index('paid_at');
            $table->index(['provider', 'status']);

            // PostgreSQL GIN index for JSONB queries
            $table->index(['gateway_response'], null, 'gin');
            $table->index(['metadata'], null, 'gin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
