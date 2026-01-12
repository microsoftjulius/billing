<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();

            // Tenant information
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Plan and limits
            $table->string('plan')->default('basic');
            $table->integer('max_users')->default(10);
            $table->integer('max_vouchers_per_day')->default(100);
            $table->integer('data_retention_days')->default(365);
            $table->string('billing_cycle')->default('monthly');
            $table->timestamp('next_billing_date')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
