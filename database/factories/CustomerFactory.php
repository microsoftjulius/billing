<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'uuid' => \Illuminate\Support\Str::orderedUuid(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => '2567' . $this->faker->numerify('#########'),
            'address' => $this->faker->address(),
            'id_type' => $this->faker->randomElement(['NIN', 'Passport', 'Driver License']),
            'id_number' => $this->faker->unique()->numerify('############'),
            'date_of_birth' => $this->faker->date(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'is_active' => $this->faker->boolean(90),
            'last_login_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'metadata' => [
                'registration_source' => $this->faker->randomElement(['web', 'mobile', 'agent']),
                'notes' => $this->faker->sentence()
            ]
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
