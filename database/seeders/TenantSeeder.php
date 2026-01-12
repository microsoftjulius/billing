<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating demo tenants...');

        // Create demo tenants with different plans
        $tenants = [
            [
                'id' => 'demo-isp',
                'name' => 'Demo ISP Solutions',
                'slug' => 'demo-isp',
                'email' => 'admin@demo-isp.com',
                'phone' => '+256700000001',
                'address' => 'Plot 123, Kampala Road, Kampala, Uganda',
                'plan' => 'professional',
                'max_users' => 1000,
                'max_vouchers_per_day' => 500,
                'data_retention_days' => 365,
                'billing_cycle' => 'monthly',
                'next_billing_date' => now()->addMonth(),
                'is_active' => true,
                'metadata' => [
                    'industry' => 'ISP',
                    'location' => 'Kampala',
                    'setup_completed' => true,
                    'features' => [
                        'advanced_sms' => true,
                        'multiple_gateways' => true,
                        'api_access' => true,
                        'custom_branding' => true,
                        'priority_support' => true,
                    ],
                    'contact_person' => 'John Doe',
                    'registration_date' => now()->subMonths(6)->toISOString(),
                ],
            ],
            [
                'id' => 'startup-wifi',
                'name' => 'Startup WiFi Hub',
                'slug' => 'startup-wifi',
                'email' => 'admin@startup-wifi.com',
                'phone' => '+256700000002',
                'address' => 'Plot 456, Entebbe Road, Entebbe, Uganda',
                'plan' => 'starter',
                'max_users' => 100,
                'max_vouchers_per_day' => 50,
                'data_retention_days' => 90,
                'billing_cycle' => 'monthly',
                'next_billing_date' => now()->addMonth(),
                'is_active' => true,
                'metadata' => [
                    'industry' => 'Hotspot',
                    'location' => 'Entebbe',
                    'setup_completed' => true,
                    'features' => [
                        'advanced_sms' => false,
                        'multiple_gateways' => false,
                        'api_access' => false,
                        'custom_branding' => false,
                        'priority_support' => false,
                    ],
                    'contact_person' => 'Jane Smith',
                    'registration_date' => now()->subMonths(2)->toISOString(),
                ],
            ],
            [
                'id' => 'enterprise-net',
                'name' => 'Enterprise Network Solutions',
                'slug' => 'enterprise-net',
                'email' => 'admin@enterprise-net.com',
                'phone' => '+256700000003',
                'address' => 'Plot 789, Industrial Area, Kampala, Uganda',
                'plan' => 'enterprise',
                'max_users' => -1, // Unlimited
                'max_vouchers_per_day' => -1, // Unlimited
                'data_retention_days' => 1095, // 3 years
                'billing_cycle' => 'yearly',
                'next_billing_date' => now()->addYear(),
                'is_active' => true,
                'metadata' => [
                    'industry' => 'Enterprise ISP',
                    'location' => 'Kampala',
                    'setup_completed' => true,
                    'features' => [
                        'advanced_sms' => true,
                        'multiple_gateways' => true,
                        'api_access' => true,
                        'custom_branding' => true,
                        'white_label' => true,
                        'priority_support' => true,
                        'dedicated_support' => true,
                        'sla_guarantee' => true,
                    ],
                    'contact_person' => 'Robert Johnson',
                    'registration_date' => now()->subYear()->toISOString(),
                    'custom_domain' => 'billing.enterprise-net.com',
                ],
            ],
            [
                'id' => 'campus-connect',
                'name' => 'Campus Connect WiFi',
                'slug' => 'campus-connect',
                'email' => 'admin@campus-connect.com',
                'phone' => '+256700000004',
                'address' => 'Makerere University, Kampala, Uganda',
                'plan' => 'professional',
                'max_users' => 1000,
                'max_vouchers_per_day' => 200,
                'data_retention_days' => 180,
                'billing_cycle' => 'monthly',
                'next_billing_date' => now()->addMonth(),
                'is_active' => true,
                'metadata' => [
                    'industry' => 'Educational WiFi',
                    'location' => 'Kampala',
                    'setup_completed' => true,
                    'features' => [
                        'advanced_sms' => true,
                        'multiple_gateways' => true,
                        'api_access' => true,
                        'custom_branding' => true,
                        'priority_support' => true,
                        'student_discounts' => true,
                    ],
                    'contact_person' => 'Dr. Sarah Wilson',
                    'registration_date' => now()->subMonths(4)->toISOString(),
                    'special_pricing' => 'educational_discount',
                ],
            ],
            [
                'id' => 'rural-connect',
                'name' => 'Rural Connect ISP',
                'slug' => 'rural-connect',
                'email' => 'admin@rural-connect.com',
                'phone' => '+256700000005',
                'address' => 'Mbarara Town, Mbarara, Uganda',
                'plan' => 'starter',
                'max_users' => 100,
                'max_vouchers_per_day' => 30,
                'data_retention_days' => 60,
                'billing_cycle' => 'monthly',
                'next_billing_date' => now()->addMonth(),
                'is_active' => true,
                'metadata' => [
                    'industry' => 'Rural ISP',
                    'location' => 'Mbarara',
                    'setup_completed' => false, // Still setting up
                    'features' => [
                        'advanced_sms' => false,
                        'multiple_gateways' => false,
                        'api_access' => false,
                        'custom_branding' => false,
                        'priority_support' => false,
                    ],
                    'contact_person' => 'Michael Tumusiime',
                    'registration_date' => now()->subWeeks(2)->toISOString(),
                    'setup_assistance' => true,
                ],
            ],
        ];

        foreach ($tenants as $tenantData) {
            // Check if tenant already exists
            if (Tenant::where('id', $tenantData['id'])->exists()) {
                $this->command->info("Tenant {$tenantData['name']} already exists. Skipping...");
                continue;
            }

            $tenant = Tenant::create($tenantData);

            // Create primary domain for the tenant
            $tenant->domains()->create([
                'domain' => $tenant->slug . '.netbillpro.com',
            ]);

            // Create fallback domain
            $tenant->domains()->create([
                'domain' => $tenant->id . '.tenants.netbillpro.com',
            ]);

            // For enterprise tenant, create custom domain if specified
            if (isset($tenantData['metadata']['custom_domain'])) {
                $tenant->domains()->create([
                    'domain' => $tenantData['metadata']['custom_domain'],
                ]);
            }

            $this->command->info("✓ Created tenant: {$tenant->name} ({$tenant->id}) - Plan: {$tenant->plan}");
        }

        $this->command->info('Tenant seeding completed successfully!');
    }
}
