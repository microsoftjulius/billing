<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('uuid')->unique()->nullable();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('recipient')->index();
            $table->text('content');
            $table->string('sender_id')->nullable();
            $table->string('message_id')->nullable();
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed'])->default('pending');
            $table->string('delivery_status')->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->string('currency', 3)->default('UGX');
            $table->string('provider')->default('ugsms');
            $table->jsonb('provider_response')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Polymorphic relationship
            $table->uuidMorphs('smsable');

            $table->timestamps();

            // Indexes for performance
            $table->index(['recipient', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['provider', 'status']);
            $table->index(['message_id', 'provider']);
            $table->index('sent_at');

            // PostgreSQL GIN indexes for JSONB queries
            $table->index(['provider_response'], null, 'gin');
            $table->index(['metadata'], null, 'gin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
