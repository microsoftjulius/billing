<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo tenants
        $tenants = [
            [
                'id' => 'demo-company',
                'name' => 'Demo Company',
                'slug' => 'demo-company',
                'email' => 'admin@demo-company.com',
                'phone' => '+256700000001',
                'plan' => 'premium',
                'max_users' => 50,
                'max_vouchers_per_day' => 1000,
                'metadata' => [
                    'industry' => 'ISP',
                    'location' => 'Kampala',
                    'setup_completed' => true,
                ],
            ],
            [
                'id' => 'test-business',
                'name' => 'Test Business',
                'slug' => 'test-business',
                'email' => 'admin@test-business.com',
                'phone' => '+256700000002',
                'plan' => 'basic',
                'max_users' => 10,
                'max_vouchers_per_day' => 100,
                'metadata' => [
                    'industry' => 'Hotspot',
                    'location' => 'Entebbe',
                    'setup_completed' => false,
                ],
            ],
        ];

        foreach ($tenants as $tenantData) {
            $tenant = Tenant::create($tenantData);

            // Create primary domain
            $tenant->domains()->create([
                'domain' => $tenant->slug . '.localhost',
                'is_primary' => true,
            ]);

            // Create fallback domain
            $tenant->domains()->create([
                'domain' => $tenant->id . '.tenants.localhost',
                'is_fallback' => true,
            ]);

            $this->command->info("Created tenant: {$tenant->name} ({$tenant->id})");
        }
    }
}
