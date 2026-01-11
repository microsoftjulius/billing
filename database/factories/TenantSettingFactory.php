<?php

namespace Database\Factories;

use App\Models\TenantSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantSettingFactory extends Factory
{
    protected $model = TenantSetting::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => $this->faker->randomElement([
                'payment.api_key',
                'payment.api_secret',
                'sms.api_key',
                'general.tenant_name',
                'general.currency',
            ]),
            'value' => $this->faker->uuid(),
            'data_type' => 'string',
            'updated_by' => User::factory(),
        ];
    }

    public function apiKey(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'payment.api_key',
            'value' => 'pk_test_' . $this->faker->uuid(),
        ]);
    }

    public function apiSecret(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'payment.api_secret',
            'value' => 'sk_test_' . $this->faker->uuid(),
        ]);
    }

    public function smsApiKey(): static
    {
        return $this->state(fn (array $attributes) => [
            'key' => 'sms.api_key',
            'value' => 'sms_' . $this->faker->bothify('################'),
        ]);
    }
}