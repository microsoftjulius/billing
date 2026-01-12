<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->json('audit_trail')->nullable()->after('metadata');
            $table->timestamp('resolved_at')->nullable()->after('audit_trail');
            $table->text('resolution_notes')->nullable()->after('resolved_at');
            $table->timestamp('disputed_at')->nullable()->after('resolution_notes');
            $table->text('dispute_reason')->nullable()->after('disputed_at');
            
            // Add indexes for better query performance (only if they don't exist)
            if (!Schema::hasIndex('payments', 'payments_amount_currency_index')) {
                $table->index(['amount', 'currency']);
            }
            if (!Schema::hasIndex('payments', 'payments_resolved_at_index')) {
                $table->index('resolved_at');
            }
            if (!Schema::hasIndex('payments', 'payments_disputed_at_index')) {
                $table->index('disputed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop indexes if they exist
            if (Schema::hasIndex('payments', 'payments_amount_currency_index')) {
                $table->dropIndex(['amount', 'currency']);
            }
            if (Schema::hasIndex('payments', 'payments_resolved_at_index')) {
                $table->dropIndex('resolved_at');
            }
            if (Schema::hasIndex('payments', 'payments_disputed_at_index')) {
                $table->dropIndex('disputed_at');
            }
            
            $table->dropColumn([
                'audit_trail',
                'resolved_at',
                'resolution_notes',
                'disputed_at',
                'dispute_reason'
            ]);
        });
    }
};