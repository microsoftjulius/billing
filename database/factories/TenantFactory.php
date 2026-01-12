<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = $this->faker->company();
        
        return [
            'id' => Str::random(8), // Use random string instead of UUID
            'name' => $name,
            'slug' => Str::slug($name),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'logo' => null,
            'is_active' => true,
            'plan' => $this->faker->randomElement(['basic', 'premium', 'enterprise']),
            'max_users' => $this->faker->numberBetween(10, 100),
            'max_vouchers_per_day' => $this->faker->numberBetween(50, 500),
            'data_retention_days' => $this->faker->numberBetween(30, 365),
            'billing_cycle' => $this->faker->randomElement(['monthly', 'yearly']),
            'next_billing_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'metadata' => [],
            'data' => [], // Required by Stancl\Tenancy
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}