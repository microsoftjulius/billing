<?php

namespace Database\Factories;

use App\Models\Voucher;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Voucher>
 */
class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['active', 'used', 'expired', 'disabled']);
        $validityHours = $this->faker->randomElement([24, 48, 72, 168]);
        $profile = $this->faker->randomElement(['daily_1gb', 'weekly_5gb', 'monthly_20gb', 'unlimited_daily']);
        $price = $this->faker->randomFloat(2, 1000, 50000);
        
        $activatedAt = $status === 'active' ? $this->faker->dateTimeBetween('-7 days', 'now') : null;
        $expiresAt = $activatedAt ? \Carbon\Carbon::parse($activatedAt)->addHours($validityHours) : null;
        $usedAt = $status === 'used' ? $this->faker->dateTimeBetween($activatedAt ?? '-7 days', 'now') : null;
        
        return [
            'uuid' => $this->faker->uuid(),
            'customer_id' => Customer::factory(),
            'payment_id' => Payment::factory(),
            'code' => $this->generateVoucherCode(),
            'password' => $this->faker->regexify('[0-9]{8}'),
            'profile' => $profile,
            'validity_hours' => $validityHours,
            'data_limit_mb' => $this->getDataLimitForProfile($profile),
            'price' => $price,
            'currency' => 'UGX',
            'status' => $status,
            'activated_at' => $activatedAt,
            'expires_at' => $expiresAt,
            'used_at' => $usedAt,
            'sms_sent_at' => null,
            'router_metadata' => [
                'router_id' => $this->faker->uuid(),
                'profile_name' => $profile,
                'created_by' => 'system',
            ],
            'usage_stats' => [
                'bytes_uploaded' => $this->faker->numberBetween(0, 1000000000),
                'bytes_downloaded' => $this->faker->numberBetween(0, 5000000000),
                'session_time' => $this->faker->numberBetween(0, 86400),
                'last_activity' => $this->faker->dateTimeBetween('-24 hours', 'now')->format('c'),
            ],
            'metadata' => [
                'source' => 'api',
                'package_type' => $this->faker->randomElement(['basic', 'premium', 'enterprise']),
                'auto_generated' => $this->faker->boolean(),
            ],
        ];
    }

    /**
     * Indicate that the voucher is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'activated_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'expires_at' => now()->addHours($attributes['validity_hours'] ?? 24),
            'used_at' => null,
        ]);
    }

    /**
     * Indicate that the voucher is used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'used',
            'activated_at' => $this->faker->dateTimeBetween('-7 days', '-1 day'),
            'used_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ]);
    }

    /**
     * Indicate that the voucher is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'activated_at' => $this->faker->dateTimeBetween('-30 days', '-7 days'),
            'expires_at' => $this->faker->dateTimeBetween('-7 days', '-1 day'),
            'used_at' => null,
        ]);
    }

    /**
     * Create a voucher with a specific code.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
        ]);
    }

    /**
     * Create a voucher with a specific profile.
     */
    public function withProfile(string $profile): static
    {
        return $this->state(fn (array $attributes) => [
            'profile' => $profile,
            'data_limit_mb' => $this->getDataLimitForProfile($profile),
        ]);
    }

    /**
     * Create a voucher with SMS sent.
     */
    public function smsSent(): static
    {
        return $this->state(fn (array $attributes) => [
            'sms_sent_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Create a voucher without SMS sent.
     */
    public function smsNotSent(): static
    {
        return $this->state(fn (array $attributes) => [
            'sms_sent_at' => null,
        ]);
    }

    /**
     * Generate a unique voucher code.
     */
    private function generateVoucherCode(): string
    {
        do {
            $code = 'BIL-' . strtoupper($this->faker->bothify('??##')) . '-' . strtoupper($this->faker->bothify('??##'));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }

    /**
     * Get data limit for a given profile.
     */
    private function getDataLimitForProfile(string $profile): ?int
    {
        return match ($profile) {
            'daily_1gb' => 1024,
            'weekly_5gb' => 5120,
            'monthly_20gb' => 20480,
            'unlimited_daily', 'unlimited_weekly' => null,
            default => 1024,
        };
    }
}