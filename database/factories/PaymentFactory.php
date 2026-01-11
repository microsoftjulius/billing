<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'completed', 'failed', 'refunded', 'cancelled']);
        $amount = $this->faker->randomFloat(2, 1000, 100000);
        $provider = $this->faker->randomElement(['collectug', 'stripe', 'paypal']);
        
        return [
            'uuid' => $this->faker->uuid(),
            'customer_id' => Customer::factory(),
            'transaction_id' => 'TXN-' . $this->faker->regexify('[A-Z0-9]{10}'),
            'reference' => 'REF-' . $this->faker->regexify('[A-Z0-9]{10}'),
            'amount' => $amount,
            'currency' => 'UGX',
            'status' => $status,
            'payment_method' => $this->faker->randomElement(['mobile_money', 'card', 'bank_transfer']),
            'provider' => $provider,
            'paid_at' => $status === 'completed' ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
            'failed_at' => $status === 'failed' ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
            'refunded_at' => $status === 'refunded' ? $this->faker->dateTimeBetween('-30 days', 'now') : null,
            'gateway_response' => [
                'transaction_id' => $this->faker->uuid(),
                'status' => $status,
                'amount' => $amount,
                'currency' => 'UGX',
                'provider' => $provider,
            ],
            'metadata' => [
                'package' => $this->faker->randomElement(['basic', 'premium', 'enterprise']),
                'validity_hours' => $this->faker->randomElement([24, 48, 72, 168]),
                'source' => 'api',
            ],
        ];
    }

    /**
     * Indicate that the payment is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'paid_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'failed_at' => null,
            'refunded_at' => null,
        ]);
    }

    /**
     * Indicate that the payment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_at' => null,
            'failed_at' => null,
            'refunded_at' => null,
        ]);
    }

    /**
     * Indicate that the payment is failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'paid_at' => null,
            'failed_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'refunded_at' => null,
        ]);
    }

    /**
     * Create a payment with a specific amount.
     */
    public function amount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'gateway_response' => array_merge($attributes['gateway_response'] ?? [], [
                'amount' => $amount,
            ]),
        ]);
    }

    /**
     * Create a payment with a specific currency.
     */
    public function currency(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
            'gateway_response' => array_merge($attributes['gateway_response'] ?? [], [
                'currency' => $currency,
            ]),
        ]);
    }
}