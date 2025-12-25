<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('uuid')->unique()->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->unique();
            $table->string('address')->nullable();
            $table->string('id_type')->nullable()->comment('NIN, Passport, etc');
            $table->string('id_number')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['phone', 'is_active']);
            $table->index(['email', 'is_active']);
            $table->index('created_at');
            $table->index('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
