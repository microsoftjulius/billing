<?php

namespace Database\Factories;

use App\Models\PaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentGateway>
 */
class PaymentGatewayFactory extends Factory
{
    protected $model = PaymentGateway::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = $this->faker->randomElement(['collectug', 'stripe', 'paypal']);
        
        $configuration = match ($provider) {
            'collectug' => [
                'api_key' => $this->faker->uuid(),
                'base_url' => 'https://api.collectug.com'
            ],
            'stripe' => [
                'secret_key' => 'sk_test_' . $this->faker->regexify('[A-Za-z0-9]{24}'),
                'webhook_secret' => 'whsec_' . $this->faker->regexify('[A-Za-z0-9]{32}')
            ],
            'paypal' => [
                'client_id' => $this->faker->regexify('[A-Za-z0-9]{80}'),
                'client_secret' => $this->faker->regexify('[A-Za-z0-9]{80}'),
                'environment' => $this->faker->randomElement(['sandbox', 'live'])
            ]
        };

        return [
            'uuid' => $this->faker->uuid(),
            'name' => $this->faker->company() . ' Gateway',
            'provider' => $provider,
            'webhook_url' => $this->faker->optional()->url(),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'configuration' => $configuration,
        ];
    }

    /**
     * Indicate that the gateway is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the gateway is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a CollectUG gateway.
     */
    public function collectug(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'collectug',
            'configuration' => [
                'api_key' => $this->faker->uuid(),
                'base_url' => 'https://api.collectug.com'
            ],
        ]);
    }

    /**
     * Create a Stripe gateway.
     */
    public function stripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'stripe',
            'configuration' => [
                'secret_key' => 'sk_test_' . $this->faker->regexify('[A-Za-z0-9]{24}'),
                'webhook_secret' => 'whsec_' . $this->faker->regexify('[A-Za-z0-9]{32}')
            ],
        ]);
    }

    /**
     * Create a PayPal gateway.
     */
    public function paypal(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'paypal',
            'configuration' => [
                'client_id' => $this->faker->regexify('[A-Za-z0-9]{80}'),
                'client_secret' => $this->faker->regexify('[A-Za-z0-9]{80}'),
                'environment' => $this->faker->randomElement(['sandbox', 'live'])
            ],
        ]);
    }
}