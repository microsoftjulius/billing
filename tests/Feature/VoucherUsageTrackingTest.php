<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherUsageTrackingTest extends TestCase
{
    use RefreshDatabase;

    private VoucherService $voucherService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->voucherService = app(VoucherService::class);
    }

    /**
     * Feature: vue-frontend-enhancement, Property 20: Voucher Usage Tracking
     * 
     * Property: For any voucher activation or expiration event, the system should 
     * automatically update the voucher status and usage statistics.
     * 
     * Validates: Requirements 8.4
     */
    public function test_voucher_usage_tracking_property()
    {
        // Create test data
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        
        // Test with different voucher configurations
        $voucherConfigs = [
            ['validity_hours' => 24, 'data_limit_mb' => 1024, 'status' => 'active'],
            ['validity_hours' => 168, 'data_limit_mb' => 5120, 'status' => 'active'],
            ['validity_hours' => 720, 'data_limit_mb' => null, 'status' => 'active'],
            ['validity_hours' => 1, 'data_limit_mb' => 100, 'status' => 'expired'], // Already expired
        ];
        
        foreach ($voucherConfigs as $config) {
            // Create voucher with specific configuration
            $voucher = Voucher::factory()->create([
                'customer_id' => $customer->id,
                'tenant_id' => $tenant->id,
                'validity_hours' => $config['validity_hours'],
                'data_limit_mb' => $config['data_limit_mb'],
                'status' => $config['status'],
                'activated_at' => now(),
                'expires_at' => $config['status'] === 'expired' 
                    ? now()->subHours(1) 
                    : now()->addHours($config['validity_hours']),
            ]);
            
            // Test voucher usage tracking
            $usageData = $this->voucherService->getVoucherUsage($voucher->code);
            
            // Verify usage tracking structure
            $this->assertArrayHasKey('voucher', $usageData, 
                "Usage data should include voucher information for voucher {$voucher->code}");
            $this->assertArrayHasKey('usage', $usageData, 
                "Usage data should include usage statistics for voucher {$voucher->code}");
            
            // Verify voucher information is complete
            $voucherInfo = $usageData['voucher'];
            $this->assertEquals($voucher->id, $voucherInfo['id'], 
                "Voucher ID should match for voucher {$voucher->code}");
            $this->assertEquals($voucher->code, $voucherInfo['code'], 
                "Voucher code should match for voucher {$voucher->code}");
            $this->assertEquals($voucher->status, $voucherInfo['status'], 
                "Voucher status should match for voucher {$voucher->code}");
            $this->assertEquals($config['validity_hours'], $voucherInfo['validity_hours'], 
                "Validity hours should match for voucher {$voucher->code}");
            
            // Verify usage statistics structure
            $usageStats = $usageData['usage'];
            $this->assertArrayHasKey('active_connections', $usageStats, 
                "Usage should include active connections count for voucher {$voucher->code}");
            $this->assertArrayHasKey('total_data_used_bytes', $usageStats, 
                "Usage should include total data used for voucher {$voucher->code}");
            $this->assertArrayHasKey('is_expired', $usageStats, 
                "Usage should include expiration status for voucher {$voucher->code}");
            $this->assertArrayHasKey('is_active', $usageStats, 
                "Usage should include active status for voucher {$voucher->code}");
            
            // Verify usage statistics are properly typed
            $this->assertIsInt($usageStats['active_connections'], 
                "Active connections should be integer for voucher {$voucher->code}");
            $this->assertIsInt($usageStats['total_data_used_bytes'], 
                "Total data used should be integer for voucher {$voucher->code}");
            $this->assertIsBool($usageStats['is_expired'], 
                "Is expired should be boolean for voucher {$voucher->code}");
            $this->assertIsBool($usageStats['is_active'], 
                "Is active should be boolean for voucher {$voucher->code}");
            
            // Verify expiration tracking accuracy
            $expectedExpired = $voucher->expires_at->isPast();
            $this->assertEquals($expectedExpired, $usageStats['is_expired'], 
                "Expiration status should be accurate for voucher {$voucher->code}");
            
            // Verify active status logic
            $expectedActive = $voucher->status === 'active' && !$voucher->expires_at->isPast();
            $this->assertEquals($expectedActive, $usageStats['is_active'], 
                "Active status should be accurate for voucher {$voucher->code}");
            
            // Verify data usage percentage calculation (if data limit exists)
            if ($config['data_limit_mb']) {
                if (isset($usageStats['data_usage_percentage'])) {
                    $this->assertIsNumeric($usageStats['data_usage_percentage'], 
                        "Data usage percentage should be numeric for voucher {$voucher->code}");
                    $this->assertGreaterThanOrEqual(0, $usageStats['data_usage_percentage'], 
                        "Data usage percentage should be non-negative for voucher {$voucher->code}");
                    $this->assertLessThanOrEqual(100, $usageStats['data_usage_percentage'], 
                        "Data usage percentage should not exceed 100% for voucher {$voucher->code}");
                }
            }
            
            // Verify customer and payment information is included
            if (isset($usageData['customer'])) {
                $this->assertEquals($customer->id, $usageData['customer']['id'], 
                    "Customer ID should match for voucher {$voucher->code}");
            }
        }
    }

    /**
     * Test voucher status updates on expiration
     */
    public function test_voucher_status_update_on_expiration()
    {
        $customer = Customer::factory()->create();
        
        // Create voucher that expires in 1 second
        $voucher = Voucher::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'active',
            'activated_at' => now(),
            'expires_at' => now()->addSecond(),
        ]);
        
        // Initially should be active
        $usageData = $this->voucherService->getVoucherUsage($voucher->code);
        $this->assertTrue($usageData['usage']['is_active']);
        $this->assertFalse($usageData['usage']['is_expired']);
        
        // Wait for expiration (simulate time passage)
        $voucher->update(['expires_at' => now()->subSecond()]);
        
        // Should now be expired
        $usageData = $this->voucherService->getVoucherUsage($voucher->code);
        $this->assertFalse($usageData['usage']['is_active']);
        $this->assertTrue($usageData['usage']['is_expired']);
    }

    /**
     * Test usage tracking for non-existent voucher
     */
    public function test_voucher_usage_tracking_nonexistent_voucher()
    {
        $usageData = $this->voucherService->getVoucherUsage('NONEXISTENT-CODE');
        
        // Should return error information
        $this->assertArrayHasKey('error', $usageData);
        $this->assertArrayHasKey('voucher_code', $usageData);
        $this->assertEquals('NONEXISTENT-CODE', $usageData['voucher_code']);
    }

    /**
     * Test usage statistics consistency
     */
    public function test_usage_statistics_consistency()
    {
        $customer = Customer::factory()->create();
        
        // Create multiple vouchers with different statuses
        $vouchers = [
            Voucher::factory()->create([
                'customer_id' => $customer->id,
                'status' => 'active',
                'expires_at' => now()->addHours(24),
            ]),
            Voucher::factory()->create([
                'customer_id' => $customer->id,
                'status' => 'active',
                'expires_at' => now()->subHours(1), // Expired
            ]),
            Voucher::factory()->create([
                'customer_id' => $customer->id,
                'status' => 'disabled',
                'expires_at' => now()->addHours(24),
            ]),
        ];
        
        foreach ($vouchers as $voucher) {
            $usageData = $this->voucherService->getVoucherUsage($voucher->code);
            
            // Verify consistency between database and usage tracking
            $this->assertEquals($voucher->status, $usageData['voucher']['status']);
            
            // Verify expiration logic consistency
            $dbExpired = $voucher->expires_at->isPast();
            $usageExpired = $usageData['usage']['is_expired'];
            $this->assertEquals($dbExpired, $usageExpired);
            
            // Verify active logic consistency
            $dbActive = $voucher->status === 'active' && !$voucher->expires_at->isPast();
            $usageActive = $usageData['usage']['is_active'];
            $this->assertEquals($dbActive, $usageActive);
        }
    }
}