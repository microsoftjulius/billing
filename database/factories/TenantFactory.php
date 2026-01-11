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
            'data' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}