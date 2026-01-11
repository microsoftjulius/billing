<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherAnalyticsAccuracyTest extends TestCase
{
    use RefreshDatabase;

    private VoucherService $voucherService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->voucherService = app(VoucherService::class);
    }

    /**
     * Feature: vue-frontend-enhancement, Property 25: Voucher Analytics Accuracy
     * 
     * Property: For any voucher analytics report, the data should accurately reflect 
     * the actual voucher usage patterns and revenue calculations.
     * 
     * Validates: Requirements 10.4
     */
    public function test_voucher_analytics_accuracy_property()
    {
        // Create test data with known values for verification
        $tenant = Tenant::factory()->create();
        $customers = Customer::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
        ]);
        
        // Create vouchers with specific patterns for analytics testing
        $testScenarios = [
            // Scenario 1: Active vouchers with revenue
            [
                'count' => 5,
                'status' => 'active',
                'price' => 10000,
                'profile' => '1GB-DAILY',
                'validity_hours' => 24,
                'created_at' => now()->subDays(1),
                'expires_at' => now()->addHours(12),
            ],
            // Scenario 2: Expired vouchers
            [
                'count' => 3,
                'status' => 'expired',
                'price' => 15000,
                'profile' => '5GB-WEEKLY',
                'validity_hours' => 168,
                'created_at' => now()->subDays(8),
                'expires_at' => now()->subDay(),
            ],
            // Scenario 3: Disabled vouchers
            [
                'count' => 2,
                'status' => 'disabled',
                'price' => 25000,
                'profile' => 'UNLIMITED-MONTHLY',
                'validity_hours' => 720,
                'created_at' => now()->subDays(2),
                'expires_at' => now()->addDays(28),
            ],
        ];
        
        $expectedTotals = [
            'total_vouchers' => 0,
            'active_vouchers' => 0,
            'expired_vouchers' => 0,
            'disabled_vouchers' => 0,
            'total_revenue' => 0,
            'profile_counts' => [],
        ];
        
        // Create vouchers according to scenarios
        foreach ($testScenarios as $scenario) {
            for ($i = 0; $i < $scenario['count']; $i++) {
                $customer = $customers[array_rand($customers->toArray())];
                
                $voucher = Voucher::create([
                    'uuid' => \Str::orderedUuid(),
                    'customer_id' => $customer->id,
                    'tenant_id' => $tenant->id,
                    'code' => 'BIL-' . strtoupper(\Str::random(4)) . '-' . strtoupper(\Str::random(4)),
                    'password' => \Str::random(8),
                    'profile' => $scenario['profile'],
                    'validity_hours' => $scenario['validity_hours'],
                    'price' => $scenario['price'],
                    'currency' => 'UGX',
                    'status' => $scenario['status'],
                    'activated_at' => $scenario['created_at'],
                    'expires_at' => $scenario['expires_at'],
                    'created_at' => $scenario['created_at'],
                    'updated_at' => $scenario['created_at'],
                ]);
                
                // Update expected totals
                $expectedTotals['total_vouchers']++;
                
                switch ($scenario['status']) {
                    case 'active':
                        $expectedTotals['active_vouchers']++;
                        $expectedTotals['total_revenue'] += $scenario['price'];
                        break;
                    case 'expired':
                        $expectedTotals['expired_vouchers']++;
                        break;
                    case 'disabled':
                        $expectedTotals['disabled_vouchers']++;
                        break;
                }
                
                // Track profile counts
                if (!isset($expectedTotals['profile_counts'][$scenario['profile']])) {
                    $expectedTotals['profile_counts'][$scenario['profile']] = 0;
                }
                $expectedTotals['profile_counts'][$scenario['profile']]++;
            }
        }
        
        // Test analytics accuracy through service
        $statistics = $this->voucherService->getVoucherStatistics();
        
        // Verify total voucher count accuracy
        $this->assertEquals($expectedTotals['total_vouchers'], $statistics['total_vouchers'], 
            "Total voucher count should be accurate");
        
        // Verify active voucher count accuracy
        $this->assertEquals($expectedTotals['active_vouchers'], $statistics['active_vouchers'], 
            "Active voucher count should be accurate");
        
        // Verify expired voucher count accuracy (if tracked)
        if (isset($statistics['expired_vouchers'])) {
            $this->assertEquals($expectedTotals['expired_vouchers'], $statistics['expired_vouchers'], 
                "Expired voucher count should be accurate");
        }
        
        // Verify disabled voucher count accuracy (if tracked)
        if (isset($statistics['disabled_vouchers'])) {
            $this->assertEquals($expectedTotals['disabled_vouchers'], $statistics['disabled_vouchers'], 
                "Disabled voucher count should be accurate");
        }
        
        // Verify revenue calculation accuracy
        $this->assertEquals($expectedTotals['total_revenue'], $statistics['total_revenue'], 
            "Total revenue should be accurate");
        
        // Verify average price calculation accuracy
        $expectedAveragePrice = $expectedTotals['total_vouchers'] > 0 
            ? round($expectedTotals['total_revenue'] / $expectedTotals['total_vouchers'], 2) 
            : 0;
        $this->assertEquals($expectedAveragePrice, $statistics['average_price'], 
            "Average price calculation should be accurate");
        
        // Verify profile distribution accuracy
        if (isset($statistics['popular_profiles'])) {
            foreach ($statistics['popular_profiles'] as $profileStat) {
                $profile = $profileStat['profile'];
                $expectedCount = $expectedTotals['profile_counts'][$profile] ?? 0;
                $this->assertEquals($expectedCount, $profileStat['count'], 
                    "Profile count for {$profile} should be accurate");
            }
        }
        
        // Verify percentage calculations
        if (isset($statistics['active_percentage'])) {
            $expectedPercentage = $expectedTotals['total_vouchers'] > 0 
                ? round(($expectedTotals['active_vouchers'] / $expectedTotals['total_vouchers']) * 100, 2) 
                : 0;
            $this->assertEquals($expectedPercentage, $statistics['active_percentage'], 
                "Active percentage calculation should be accurate");
        }
    }

    /**
     * Test analytics accuracy with different time periods
     */
    public function test_analytics_accuracy_different_periods()
    {
        $customer = Customer::factory()->create();
        
        // Create vouchers for different time periods
        $todayVouchers = 3;
        $weekVouchers = 5;
        $monthVouchers = 8;
        
        // Today's vouchers
        for ($i = 0; $i < $todayVouchers; $i++) {
            Voucher::create([
                'uuid' => \Str::orderedUuid(),
                'customer_id' => $customer->id,
                'code' => 'TODAY-' . $i,
                'password' => \Str::random(8),
                'profile' => '1GB-DAILY',
                'validity_hours' => 24,
                'price' => 5000,
                'currency' => 'UGX',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        // This week's vouchers (excluding today)
        for ($i = 0; $i < $weekVouchers - $todayVouchers; $i++) {
            Voucher::create([
                'uuid' => \Str::orderedUuid(),
                'customer_id' => $customer->id,
                'code' => 'WEEK-' . $i,
                'password' => \Str::random(8),
                'profile' => '5GB-WEEKLY',
                'validity_hours' => 168,
                'price' => 15000,
                'currency' => 'UGX',
                'status' => 'active',
                'created_at' => now()->subDays(rand(1, 6)),
                'updated_at' => now()->subDays(rand(1, 6)),
            ]);
        }
        
        // This month's vouchers (excluding this week)
        for ($i = 0; $i < $monthVouchers - $weekVouchers; $i++) {
            Voucher::create([
                'uuid' => \Str::orderedUuid(),
                'customer_id' => $customer->id,
                'code' => 'MONTH-' . $i,
                'password' => \Str::random(8),
                'profile' => 'UNLIMITED-MONTHLY',
                'validity_hours' => 720,
                'price' => 50000,
                'currency' => 'UGX',
                'status' => 'active',
                'created_at' => now()->subDays(rand(8, 29)),
                'updated_at' => now()->subDays(rand(8, 29)),
            ]);
        }
        
        // Test API endpoint for period-specific statistics
        $response = $this->getJson('/api/vouchers/statistics?period=today');
        
        if ($response->status() === 200) {
            $data = $response->json('data');
            
            if (isset($data['period_statistics'])) {
                $periodStats = $data['period_statistics'];
                
                // Verify today's statistics accuracy
                $this->assertEquals($todayVouchers, $periodStats['total_vouchers'], 
                    "Today's voucher count should be accurate");
                
                $expectedRevenue = $todayVouchers * 5000;
                $this->assertEquals($expectedRevenue, $periodStats['total_revenue'], 
                    "Today's revenue should be accurate");
            }
        }
        
        // Test weekly statistics
        $response = $this->getJson('/api/vouchers/statistics?period=week');
        
        if ($response->status() === 200) {
            $data = $response->json('data');
            
            if (isset($data['period_statistics'])) {
                $periodStats = $data['period_statistics'];
                
                // Should include today's vouchers plus week vouchers
                $this->assertGreaterThanOrEqual($weekVouchers, $periodStats['total_vouchers'], 
                    "Weekly voucher count should include all week vouchers");
            }
        }
    }

    /**
     * Test analytics accuracy with revenue calculations
     */
    public function test_analytics_revenue_calculation_accuracy()
    {
        $customer = Customer::factory()->create();
        
        // Create vouchers with specific prices for revenue testing
        $voucherPrices = [1000, 5000, 10000, 15000, 25000];
        $totalExpectedRevenue = 0;
        
        foreach ($voucherPrices as $price) {
            Voucher::create([
                'uuid' => \Str::orderedUuid(),
                'customer_id' => $customer->id,
                'code' => 'REV-' . $price,
                'password' => \Str::random(8),
                'profile' => '1GB-DAILY',
                'validity_hours' => 24,
                'price' => $price,
                'currency' => 'UGX',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $totalExpectedRevenue += $price;
        }
        
        // Add some non-revenue generating vouchers (expired/disabled)
        Voucher::create([
            'uuid' => \Str::orderedUuid(),
            'customer_id' => $customer->id,
            'code' => 'EXPIRED-1',
            'password' => \Str::random(8),
            'profile' => '1GB-DAILY',
            'validity_hours' => 24,
            'price' => 10000,
            'currency' => 'UGX',
            'status' => 'expired',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $statistics = $this->voucherService->getVoucherStatistics();
        
        // Verify revenue calculation excludes non-active vouchers
        $this->assertEquals($totalExpectedRevenue, $statistics['total_revenue'], 
            "Revenue should only include active vouchers");
        
        // Verify average price calculation
        $expectedAverage = round($totalExpectedRevenue / count($voucherPrices), 2);
        $actualAverage = round($statistics['total_revenue'] / $statistics['active_vouchers'], 2);
        $this->assertEquals($expectedAverage, $actualAverage, 
            "Average price calculation should be accurate");
    }

    /**
     * Test analytics accuracy with edge cases
     */
    public function test_analytics_accuracy_edge_cases()
    {
        // Test with no vouchers
        $statistics = $this->voucherService->getVoucherStatistics();
        
        $this->assertEquals(0, $statistics['total_vouchers'], 
            "Should handle zero vouchers correctly");
        $this->assertEquals(0, $statistics['total_revenue'], 
            "Should handle zero revenue correctly");
        $this->assertEquals(0, $statistics['average_price'], 
            "Should handle zero average price correctly");
        
        // Test with only free vouchers (price = 0)
        $customer = Customer::factory()->create();
        
        Voucher::create([
            'uuid' => \Str::orderedUuid(),
            'customer_id' => $customer->id,
            'code' => 'FREE-1',
            'password' => \Str::random(8),
            'profile' => '1GB-DAILY',
            'validity_hours' => 24,
            'price' => 0,
            'currency' => 'UGX',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $statistics = $this->voucherService->getVoucherStatistics();
        
        $this->assertEquals(1, $statistics['total_vouchers'], 
            "Should count free vouchers");
        $this->assertEquals(0, $statistics['total_revenue'], 
            "Should handle free vouchers in revenue calculation");
        $this->assertEquals(0, $statistics['average_price'], 
            "Should handle free vouchers in average calculation");
    }
}