<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Voucher;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        \App\Models\Voucher::truncate();
        \App\Models\Payment::truncate();
        \App\Models\Customer::truncate();
        \App\Models\User::truncate();
        // Check if admin user already exists
        if (!User::where('email', 'admin@billing.com')->exists()) {
            // Create admin user
            User::create([
                'uuid' => \Illuminate\Support\Str::orderedUuid(),
                'name' => 'Admin User',
                'email' => 'admin@billing.com',
                'password' => bcrypt('password'),
                'role' => 'admin',
                'phone' => '256700000000',
                'is_active' => true
            ]);
            $this->command->info('Admin user created.');
        } else {
            $this->command->info('Admin user already exists.');
        }

        // Check if we already have customers
        if (Customer::count() > 0) {
            $this->command->info('Customers already exist. Skipping customer creation.');
            return;
        }

        // Create 50 customers
        $customers = Customer::factory()->count(50)->create();
        $this->command->info('Created 50 customers.');

        // For each customer, create payments and vouchers
        $customers->each(function ($customer) {
            // Create 1-5 payments per customer
            $paymentCount = rand(1, 5);

            for ($i = 0; $i < $paymentCount; $i++) {
                $package = $this->getRandomPackage();
                $payment = Payment::create([
                    'uuid' => \Illuminate\Support\Str::orderedUuid(),
                    'customer_id' => $customer->id,
                    'transaction_id' => 'TXN-' . strtoupper(\Illuminate\Support\Str::random(10)),
                    'amount' => rand(1000, 50000),
                    'status' => $this->getRandomPaymentStatus(),
                    'payment_method' => 'mobile_money',
                    'provider' => 'collectug',
                    'paid_at' => now()->subDays(rand(0, 30)),
                    'metadata' => [
                        'package' => $package,
                        'device' => $this->getRandomDevice()
                    ]
                ]);

                // Create voucher for completed payments
                if ($payment->status === 'completed') {
                    Voucher::create([
                        'uuid' => \Illuminate\Support\Str::orderedUuid(),
                        'customer_id' => $customer->id,
                        'payment_id' => $payment->id,
                        'code' => 'VOUCH-' . strtoupper(\Illuminate\Support\Str::random(8)),
                        'password' => \Illuminate\Support\Str::random(8),
                        'profile' => $package,
                        'validity_hours' => $this->getValidityHours($package),
                        'price' => $payment->amount,
                        'status' => 'active',
                        'activated_at' => $payment->paid_at,
                        'expires_at' => $payment->paid_at->addHours($this->getValidityHours($package)),
                        'metadata' => [
                            'generated_by' => 'seeder',
                            'notes' => 'Test voucher'
                        ]
                    ]);
                }
            }
        });

        $this->command->info('Payments and vouchers created successfully.');
    }

    private function getRandomPaymentStatus(): string
    {
        $statuses = ['completed', 'completed', 'completed', 'completed', 'failed'];
        return $statuses[array_rand($statuses)];
    }

    private function getRandomPackage(): string
    {
        $packages = ['daily_1gb', 'weekly_5gb', 'monthly_20gb', 'unlimited_daily'];
        return $packages[array_rand($packages)];
    }

    private function getRandomDevice(): string
    {
        $devices = ['Android', 'iPhone', 'Windows', 'Mac', 'Linux'];
        return $devices[array_rand($devices)];
    }

    private function getValidityHours(string $package): int
    {
        return match($package) {
            'daily_1gb' => 24,
            'weekly_5gb' => 168,
            'monthly_20gb' => 720,
            'unlimited_daily' => 24,
            default => 24
        };
    }
}
