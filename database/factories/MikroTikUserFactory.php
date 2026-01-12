<?php

namespace Database\Factories;

use App\Models\MikroTikUser;
use App\Models\MikroTikDevice;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MikroTikUser>
 */
class MikroTikUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MikroTikUser::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => MikroTikDevice::factory(),
            'username' => $this->faker->unique()->userName(),
            'password' => $this->faker->password(8, 16),
            'profile' => $this->faker->randomElement(['default', '1M', '2M', '5M', '10M', 'unlimited']),
            'voucher_id' => null, // Will be set when needed
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
        ];
    }

    /**
     * Indicate that the user is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the user has a voucher.
     */
    public function withVoucher(): static
    {
        return $this->state(fn (array $attributes) => [
            'voucher_id' => Voucher::factory(),
        ]);
    }

    /**
     * Indicate that the user has no voucher.
     */
    public function withoutVoucher(): static
    {
        return $this->state(fn (array $attributes) => [
            'voucher_id' => null,
        ]);
    }

    /**
     * Create a user with a specific profile.
     */
    public function withProfile(string $profile): static
    {
        return $this->state(fn (array $attributes) => [
            'profile' => $profile,
        ]);
    }

    /**
     * Create a user with a specific username pattern.
     */
    public function withUsernamePattern(string $prefix = 'user'): static
    {
        return $this->state(fn (array $attributes) => [
            'username' => $prefix . '_' . $this->faker->unique()->numberBetween(1000, 9999),
        ]);
    }
}