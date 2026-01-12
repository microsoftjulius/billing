<?php

namespace Database\Factories;

use App\Models\MikroTikDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MikroTikDevice>
 */
class MikroTikDeviceFactory extends Factory
{
    protected $model = MikroTikDevice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'MikroTik-' . $this->faker->bothify('##??##'),
            'ip_address' => $this->faker->ipv4(),
            'api_port' => $this->faker->randomElement([8728, 8729, 8730]),
            'username' => $this->faker->randomElement(['admin', 'user', $this->faker->userName()]),
            'password' => $this->faker->password(8, 20),
            'location' => [
                'region' => $this->faker->randomElement(['Central', 'Eastern', 'Western', 'Northern']),
                'district' => $this->faker->city(),
                'coordinates' => [
                    'lat' => $this->faker->latitude(),
                    'lng' => $this->faker->longitude()
                ]
            ],
            'status' => $this->faker->randomElement(['online', 'offline', 'error']),
            'last_seen' => $this->faker->optional()->dateTimeBetween('-1 week', 'now'),
            'uptime_seconds' => $this->faker->numberBetween(0, 2592000), // Up to 30 days, never null
            'configuration' => null, // Will be set when needed
            'backup_data' => null, // Will be set when needed
        ];
    }

    /**
     * Indicate that the device is online.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'online',
            'last_seen' => now(),
            'uptime_seconds' => $this->faker->numberBetween(3600, 2592000), // At least 1 hour uptime
        ]);
    }

    /**
     * Indicate that the device is offline.
     */
    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'offline',
            'last_seen' => $this->faker->dateTimeBetween('-1 week', '-1 hour'),
            'uptime_seconds' => 0,
        ]);
    }

    /**
     * Indicate that the device has an error.
     */
    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'last_seen' => $this->faker->dateTimeBetween('-1 day', '-10 minutes'),
            'uptime_seconds' => 0,
        ]);
    }

    /**
     * Create a device with standard MikroTik settings.
     */
    public function standard(): static
    {
        return $this->state(fn (array $attributes) => [
            'api_port' => 8728,
            'username' => 'admin',
            'password' => 'admin123',
        ]);
    }

    /**
     * Create a device in Central region.
     */
    public function central(): static
    {
        return $this->state(fn (array $attributes) => [
            'location' => [
                'region' => 'Central',
                'district' => 'Kampala',
                'coordinates' => [
                    'lat' => $this->faker->randomFloat(6, 0.1, 0.5),
                    'lng' => $this->faker->randomFloat(6, 32.3, 32.8)
                ]
            ],
        ]);
    }

    /**
     * Create a device with configuration.
     */
    public function withConfiguration(): static
    {
        return $this->state(fn (array $attributes) => [
            'configuration' => [
                'system' => [
                    'identity' => $attributes['name'] ?? 'MikroTik-Device',
                    'clock' => [
                        'time_zone_name' => 'Africa/Kampala',
                    ],
                ],
                'interfaces' => [
                    'ether1' => [
                        'name' => 'ether1',
                        'type' => 'ethernet',
                        'mtu' => 1500,
                        'disabled' => false,
                    ],
                    'ether2' => [
                        'name' => 'ether2',
                        'type' => 'ethernet',
                        'mtu' => 1500,
                        'disabled' => false,
                    ],
                ],
                'ip_addresses' => [
                    [
                        'address' => $this->faker->localIpv4() . '/24',
                        'interface' => 'ether1',
                        'disabled' => false,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Create a device with backup data.
     */
    public function withBackup(): static
    {
        return $this->state(fn (array $attributes) => [
            'backup_data' => [
                'backup_date' => now()->toISOString(),
                'backup_size' => $this->faker->numberBetween(1024, 10240),
                'backup_version' => '7.1.5',
                'configuration_hash' => $this->faker->sha256(),
            ],
        ]);
    }
}