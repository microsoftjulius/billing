<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Voucher;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Starting database seeding...');

        // First, seed tenants
        $this->call(TenantSeeder::class);

        // Get all tenants
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Creating a default tenant...');
            $defaultTenant = Tenant::create([
                'id' => 'default-tenant',
                'name' => 'Default ISP',
                'slug' => 'default-isp',
                'email' => 'admin@default-isp.com',
                'phone' => '+256700000000',
                'plan' => 'professional',
                'max_users' => 1000,
                'max_vouchers_per_day' => 500,
                'is_active' => true,
                'metadata' => [
                    'industry' => 'ISP',
                    'location' => 'Kampala',
                    'setup_completed' => true,
                ],
            ]);
            $tenants = collect([$defaultTenant]);
        }

        // Create global admin user (not tenant-specific)
        if (!User::where('email', 'admin@billing.com')->exists()) {
            User::create([
                'uuid' => Str::orderedUuid(),
                'name' => 'Global Admin',
                'email' => 'admin@billing.com',
                'password' => bcrypt('password123'),
                'role' => 'admin',
                'phone' => '256700000000',
                'is_active' => true,
                'tenant_id' => null, // Global admin
            ]);
            $this->command->info('Global admin user created.');
        }

        // For each tenant, create tenant-specific data
        foreach ($tenants as $tenant) {
            $this->command->info("Seeding data for tenant: {$tenant->name}");
            $this->seedTenantData($tenant);
        }

        $this->command->info('Database seeding completed successfully!');
    }

    private function seedTenantData(Tenant $tenant): void
    {
        // Create tenant admin user
        if (!User::where('email', "admin@{$tenant->slug}.com")->exists()) {
            User::create([
                'uuid' => Str::orderedUuid(),
                'name' => "{$tenant->name} Admin",
                'email' => "admin@{$tenant->slug}.com",
                'password' => bcrypt('password123'),
                'role' => 'admin',
                'phone' => $tenant->phone,
                'is_active' => true,
                'tenant_id' => $tenant->id,
            ]);
            $this->command->info("Created admin user for {$tenant->name}");
        }

        // Create staff users for the tenant
        for ($i = 1; $i <= 2; $i++) {
            if (!User::where('email', "staff{$i}@{$tenant->slug}.com")->exists()) {
                User::create([
                    'uuid' => Str::orderedUuid(),
                    'name' => "{$tenant->name} Staff {$i}",
                    'email' => "staff{$i}@{$tenant->slug}.com",
                    'password' => bcrypt('password123'),
                    'role' => 'staff',
                    'phone' => '256700' . str_pad($tenant->id . $i, 6, '0', STR_PAD_LEFT),
                    'is_active' => true,
                    'tenant_id' => $tenant->id,
                ]);
            }
        }

        // Check if tenant already has customers
        if (Customer::where('tenant_id', $tenant->id)->count() > 0) {
            $this->command->info("Tenant {$tenant->name} already has customers. Skipping customer creation.");
            return;
        }

        // Create customers for this tenant
        $customerCount = $this->getCustomerCountForPlan($tenant->plan);
        $this->command->info("Creating {$customerCount} customers for {$tenant->name}...");

        // Add some specific Ugandan customers first
        $ugandanCustomers = [
            [
                'name' => 'Julius Mukasa',
                'email' => 'julius@' . $tenant->slug . '.com',
                'phone' => '256701' . substr($tenant->id, -6), // Make phone unique per tenant
                'address' => 'Kampala, Uganda',
            ],
            [
                'name' => 'Sarah Nakato',
                'email' => 'sarah.nakato@' . $tenant->slug . '.com',
                'phone' => '256702' . substr($tenant->id, -6),
                'address' => 'Entebbe, Uganda',
            ],
            [
                'name' => 'David Ssemakula',
                'email' => 'david.ssemakula@' . $tenant->slug . '.com',
                'phone' => '256703' . substr($tenant->id, -6),
                'address' => 'Jinja, Uganda',
            ],
            [
                'name' => 'Grace Namugga',
                'email' => 'grace.namugga@' . $tenant->slug . '.com',
                'phone' => '256704' . substr($tenant->id, -6),
                'address' => 'Mbarara, Uganda',
            ],
            [
                'name' => 'Robert Kiwanuka',
                'email' => 'robert.kiwanuka@' . $tenant->slug . '.com',
                'phone' => '256705' . substr($tenant->id, -6),
                'address' => 'Gulu, Uganda',
            ],
        ];

        // Create the specific Ugandan customers
        foreach ($ugandanCustomers as $ugandanData) {
            $customer = Customer::create([
                'uuid' => Str::orderedUuid(),
                'name' => $ugandanData['name'],
                'email' => $ugandanData['email'],
                'phone' => $ugandanData['phone'],
                'address' => $ugandanData['address'],
                'id_type' => 'national_id',
                'id_number' => 'CM' . fake()->unique()->numerify('##########'),
                'date_of_birth' => fake()->dateTimeBetween('-50 years', '-20 years'),
                'gender' => fake()->randomElement(['male', 'female']),
                'is_active' => true,
                'last_login_at' => fake()->boolean(80) ? \Carbon\Carbon::instance(fake()->dateTimeBetween('-7 days', 'now')) : null,
                'tenant_id' => $tenant->id,
                'metadata' => [
                    'registration_source' => 'web',
                    'preferred_language' => fake()->randomElement(['en', 'sw', 'lg']),
                    'marketing_consent' => true,
                    'country' => 'Uganda',
                    'city' => explode(',', $ugandanData['address'])[0],
                ],
            ]);

            // Create payments for Ugandan customers
            $this->createPaymentsForCustomer($customer, $tenant);
        }

        // Create remaining random customers
        $remainingCount = max(0, $customerCount - count($ugandanCustomers));
        
        for ($i = 1; $i <= $remainingCount; $i++) {
            $customer = Customer::create([
                'uuid' => Str::orderedUuid(),
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'phone' => '256' . fake()->unique()->numerify('7########'),
                'address' => fake()->address(),
                'id_type' => fake()->randomElement(['national_id', 'passport', 'driving_license']),
                'id_number' => fake()->unique()->numerify('##########'),
                'date_of_birth' => fake()->dateTimeBetween('-60 years', '-18 years'),
                'gender' => fake()->randomElement(['male', 'female']),
                'is_active' => fake()->boolean(90), // 90% active
                'last_login_at' => fake()->boolean(70) ? \Carbon\Carbon::instance(fake()->dateTimeBetween('-30 days', 'now')) : null,
                'tenant_id' => $tenant->id,
                'metadata' => [
                    'registration_source' => fake()->randomElement(['web', 'mobile', 'agent', 'walk_in']),
                    'preferred_language' => fake()->randomElement(['en', 'sw', 'lg']),
                    'marketing_consent' => fake()->boolean(60),
                ],
            ]);

            // Create payments for this customer
            $this->createPaymentsForCustomer($customer, $tenant);
        }

        $this->command->info("Completed seeding for tenant: {$tenant->name}");
    }

    private function createPaymentsForCustomer($customer, $tenant)
    {
        $paymentCount = fake()->numberBetween(1, 8);
        for ($j = 0; $j < $paymentCount; $j++) {
            $package = $this->getRandomPackage();
            $amount = $this->getPackagePrice($package);
            $createdAt = fake()->dateTimeBetween('-90 days', 'now');
            $createdAt = \Carbon\Carbon::instance($createdAt); // Convert to Carbon
            $status = $this->getRandomPaymentStatus();

            $payment = Payment::create([
                'uuid' => Str::orderedUuid(),
                'customer_id' => $customer->id,
                'transaction_id' => 'TXN-' . $tenant->slug . '-' . strtoupper(Str::random(8)),
                'reference' => 'REF-' . strtoupper(Str::random(12)),
                'amount' => $amount,
                'currency' => 'UGX',
                'status' => $status,
                'payment_method' => fake()->randomElement(['mobile_money', 'bank_transfer', 'cash']),
                'provider' => fake()->randomElement(['collectug', 'flutterwave', 'paypal']),
                'paid_at' => $status === 'completed' ? $createdAt : null,
                'failed_at' => $status === 'failed' ? $createdAt : null,
                'tenant_id' => $tenant->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'gateway_response' => [
                    'gateway_reference' => 'GW-' . strtoupper(Str::random(10)),
                    'gateway_message' => $status === 'completed' ? 'Payment successful' : 'Payment failed',
                    'processed_at' => $createdAt->toISOString(),
                ],
                'metadata' => [
                    'package' => $package,
                    'validity_hours' => $this->getValidityHours($package),
                    'device_type' => fake()->randomElement(['Android', 'iPhone', 'Windows', 'Mac', 'Linux']),
                    'ip_address' => fake()->ipv4(),
                    'user_agent' => fake()->userAgent(),
                    'tenant_code' => $tenant->slug,
                ],
            ]);

            // Create voucher for completed payments
            if ($payment->status === 'completed') {
                $expiresAt = $payment->paid_at->addHours($this->getValidityHours($package));
                $isExpired = $expiresAt->isPast();
                
                Voucher::create([
                    'uuid' => Str::orderedUuid(),
                    'customer_id' => $customer->id,
                    'payment_id' => $payment->id,
                    'code' => $this->generateVoucherCode($tenant->slug),
                    'password' => Str::random(8),
                    'profile' => $package,
                    'validity_hours' => $this->getValidityHours($package),
                    'data_limit_mb' => $this->getDataLimit($package),
                    'price' => $payment->amount,
                    'currency' => 'UGX',
                    'status' => $isExpired ? 'expired' : fake()->randomElement(['active', 'used', 'disabled']),
                    'activated_at' => $payment->paid_at,
                    'expires_at' => $expiresAt,
                    'used_at' => fake()->boolean(30) ? \Carbon\Carbon::instance(fake()->dateTimeBetween($payment->paid_at, 'now')) : null,
                    'sms_sent_at' => fake()->boolean(80) ? \Carbon\Carbon::instance(fake()->dateTimeBetween($payment->paid_at, $payment->paid_at->addMinutes(5))) : null,
                    'tenant_id' => $tenant->id,
                    'created_at' => $payment->paid_at,
                    'updated_at' => $payment->paid_at,
                    'router_metadata' => [
                        'router_id' => fake()->randomElement(['router-1', 'router-2', 'router-3']),
                        'interface' => fake()->randomElement(['wlan1', 'wlan2', 'bridge1']),
                        'assigned_ip' => fake()->localIpv4(),
                        'mac_address' => fake()->macAddress(),
                    ],
                    'usage_stats' => [
                        'bytes_uploaded' => fake()->numberBetween(1000000, 100000000),
                        'bytes_downloaded' => fake()->numberBetween(10000000, 1000000000),
                        'session_time' => fake()->numberBetween(300, 86400),
                        'last_activity' => \Carbon\Carbon::instance(fake()->dateTimeBetween($payment->paid_at, 'now'))->toISOString(),
                    ],
                    'metadata' => [
                        'generated_by' => 'seeder',
                        'sms_provider' => fake()->randomElement(['twilio', 'africas_talking', 'local_sms']),
                        'notes' => fake()->optional(0.3)->sentence(),
                    ],
                ]);
            }
        }
    }

    private function getCustomerCountForPlan(string $plan): int
    {
        return match($plan) {
            'starter' => fake()->numberBetween(20, 50),
            'professional' => fake()->numberBetween(50, 150),
            'enterprise' => fake()->numberBetween(100, 300),
            default => 30
        };
    }

    private function getRandomPaymentStatus(): string
    {
        // 70% completed, 20% failed, 10% pending
        $rand = fake()->numberBetween(1, 100);
        if ($rand <= 70) return 'completed';
        if ($rand <= 90) return 'failed';
        return 'pending';
    }

    private function getRandomPackage(): string
    {
        $packages = [
            'daily_1gb' => 30,
            'weekly_5gb' => 25,
            'monthly_20gb' => 25,
            'unlimited_daily' => 15,
            'unlimited_weekly' => 5
        ];
        
        $rand = fake()->numberBetween(1, 100);
        $cumulative = 0;
        
        foreach ($packages as $package => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $package;
            }
        }
        
        return 'daily_1gb';
    }

    private function getPackagePrice(string $package): int
    {
        return match($package) {
            'daily_1gb' => fake()->numberBetween(2000, 3000),
            'weekly_5gb' => fake()->numberBetween(10000, 15000),
            'monthly_20gb' => fake()->numberBetween(35000, 50000),
            'unlimited_daily' => fake()->numberBetween(5000, 8000),
            'unlimited_weekly' => fake()->numberBetween(25000, 35000),
            default => 2500
        };
    }

    private function getValidityHours(string $package): int
    {
        return match($package) {
            'daily_1gb' => 24,
            'weekly_5gb' => 168, // 7 days
            'monthly_20gb' => 720, // 30 days
            'unlimited_daily' => 24,
            'unlimited_weekly' => 168,
            default => 24
        };
    }

    private function getDataLimit(string $package): ?int
    {
        return match($package) {
            'daily_1gb' => 1024, // 1GB in MB
            'weekly_5gb' => 5120, // 5GB in MB
            'monthly_20gb' => 20480, // 20GB in MB
            'unlimited_daily' => null, // Unlimited
            'unlimited_weekly' => null, // Unlimited
            default => 1024
        };
    }

    private function generateVoucherCode(string $tenantSlug): string
    {
        $prefix = strtoupper(substr($tenantSlug, 0, 3));
        return $prefix . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
    }
}
