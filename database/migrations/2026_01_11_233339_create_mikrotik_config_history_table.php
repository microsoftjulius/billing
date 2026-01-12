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
        Schema::create('mikrotik_config_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('device_id');
            $table->json('configuration_data');
            $table->enum('change_type', ['backup', 'restore', 'update']);
            $table->uuid('changed_by')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('mikrotik_devices')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['device_id', 'created_at']);
            $table->index('change_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mikrotik_config_history');
    }
};