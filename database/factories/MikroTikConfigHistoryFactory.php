<?php

namespace Database\Factories;

use App\Models\MikroTikConfigHistory;
use App\Models\MikroTikDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MikroTikConfigHistory>
 */
class MikroTikConfigHistoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MikroTikConfigHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => MikroTikDevice::factory(),
            'configuration_data' => [
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
                'dhcp_server' => [
                    'name' => 'dhcp1',
                    'interface' => 'ether2',
                    'address_pool' => '192.168.1.100-192.168.1.200',
                    'disabled' => false,
                ],
                'firewall_rules' => [
                    [
                        'chain' => 'input',
                        'action' => 'accept',
                        'connection_state' => 'established,related',
                    ],
                    [
                        'chain' => 'input',
                        'action' => 'drop',
                        'in_interface' => 'ether2',
                    ],
                ],
                'system' => [
                    'identity' => $this->faker->domainWord(),
                    'clock' => [
                        'time_zone_name' => 'Africa/Kampala',
                    ],
                ],
            ],
            'change_type' => $this->faker->randomElement(['backup', 'restore', 'update']),
            'changed_by' => User::factory(),
        ];
    }

    /**
     * Indicate that this is a backup entry.
     */
    public function backup(): static
    {
        return $this->state(fn (array $attributes) => [
            'change_type' => 'backup',
        ]);
    }

    /**
     * Indicate that this is a restore entry.
     */
    public function restore(): static
    {
        return $this->state(fn (array $attributes) => [
            'change_type' => 'restore',
        ]);
    }

    /**
     * Indicate that this is an update entry.
     */
    public function update(): static
    {
        return $this->state(fn (array $attributes) => [
            'change_type' => 'update',
        ]);
    }

    /**
     * Create a minimal configuration.
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'configuration_data' => [
                'system' => [
                    'identity' => $this->faker->domainWord(),
                ],
                'interfaces' => [
                    'ether1' => [
                        'name' => 'ether1',
                        'type' => 'ethernet',
                    ],
                ],
            ],
        ]);
    }
}