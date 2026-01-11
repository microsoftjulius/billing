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
        Schema::create('mikrotik_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->ipAddress('ip_address');
            $table->json('location');
            $table->integer('api_port')->default(8728);
            $table->string('username');
            $table->text('password_encrypted');
            $table->enum('status', ['online', 'offline', 'error'])->default('offline');
            $table->timestamp('last_seen')->nullable();
            $table->bigInteger('uptime_seconds')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index(['ip_address', 'api_port']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mikrotik_devices');
    }
};
