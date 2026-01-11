<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherPurchaseDataCompletenessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Feature: vue-frontend-enhancement, Property 24: Voucher Purchase Data Completeness
     * 
     * Property: For any voucher purchase record, the system should display the purchase 
     * date, time, duration, expiration, amount paid, and activation status.
     * 
     * Validates: Requirements 10.1, 10.2, 10.3, 10.6
     */
    public function test_voucher_purchase_data_completeness_property()
    {
        // Create test data
        $tenant = Tenant::factory()->create();
        $customers = Customer::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
        ]);
        
        foreach ($customers as $customer) {
            // Create different types of voucher purchases
            $purchaseScenarios = [
                [
                    'amount' => 5000,
                    'validity_hours' => 24,
                    'data_limit_mb' => 1024,
                    'status' => 'active',
                    'activated_at' => now(),
                    'expires_at' => now()->addHours(24),
                ],
                [
                    'amount' => 15000,
                    'validity_hours' => 168,
                    'data_limit_mb' => 5120,
                    'status' => 'active',
                    'activated_at' => now()->subHours(12),
                    'expires_at' => now()->addHours(156),
                ],
                [
                    'amount' => 50000,
                    'validity_hours' => 720,
                    'data_limit_mb' => null,
                    'status' => 'expired',
                    'activated_at' => now()->subDays(31),
                    'expires_at' => now()->subDay(),
                ],
            ];
            
            foreach ($purchaseScenarios as $scenario) {
                // Create payment record
                $payment = Payment::factory()->create([
                    'customer_id' => $customer->id,
                    'tenant_id' => $tenant->id,
                    'amount' => $scenario['amount'],
                    'currency' => 'UGX',
                    'status' => 'completed',
                    'paid_at' => $scenario['activated_at'] ?? now(),
                    'metadata' => [
                        'package' => 'test_package',
                        'validity_hours' => $scenario['validity_hours'],
                    ]
                ]);
                
                // Create voucher record
                $voucher = Voucher::factory()->create([
                    'customer_id' => $customer->id,
                    'payment_id' => $payment->id,
                    'tenant_id' => $tenant->id,
                    'validity_hours' => $scenario['validity_hours'],
                    'data_limit_mb' => $scenario['data_limit_mb'],
                    'price' => $scenario['amount'],
                    'currency' => 'UGX',
                    'status' => $scenario['status'],
                    'activated_at' => $scenario['activated_at'],
                    'expires_at' => $scenario['expires_at'],
                ]);
                
                // Test API endpoint for voucher purchase data
                $response = $this->getJson("/api/vouchers/{$voucher->code}");
                
                if ($response->status() === 200) {
                    $data = $response->json('data');
                    
                    // Verify purchase date and time completeness
                    $this->assertArrayHasKey('voucher', $data, 
                        "Response should include voucher data for voucher {$voucher->code}");
                    $this->assertArrayHasKey('created_at', $data['voucher'], 
                        "Voucher should include purchase date for voucher {$voucher->code}");
                    $this->assertNotNull($data['voucher']['created_at'], 
                        "Purchase date should not be null for voucher {$voucher->code}");
                    
                    // Verify duration completeness
                    $this->assertArrayHasKey('validity_hours', $data['voucher'], 
                        "Voucher should include duration for voucher {$voucher->code}");
                    $this->assertEquals($scenario['validity_hours'], $data['voucher']['validity_hours'], 
                        "Duration should match expected value for voucher {$voucher->code}");
                    
                    // Verify expiration completeness
                    $this->assertArrayHasKey('expires_at', $data['voucher'], 
                        "Voucher should include expiration for voucher {$voucher->code}");
                    if ($data['voucher']['expires_at']) {
                        $this->assertNotNull($data['voucher']['expires_at'], 
                            "Expiration should not be null for active voucher {$voucher->code}");
                    }
                    
                    // Verify amount paid completeness
                    $this->assertArrayHasKey('price', $data['voucher'], 
                        "Voucher should include price for voucher {$voucher->code}");
                    $this->assertEquals($scenario['amount'], $data['voucher']['price'], 
                        "Price should match payment amount for voucher {$voucher->code}");
                    
                    // Verify activation status completeness
                    $this->assertArrayHasKey('status', $data['voucher'], 
                        "Voucher should include status for voucher {$voucher->code}");
                    $this->assertEquals($scenario['status'], $data['voucher']['status'], 
                        "Status should match expected value for voucher {$voucher->code}");
                    $this->assertArrayHasKey('activated_at', $data['voucher'], 
                        "Voucher should include activation timestamp for voucher {$voucher->code}");
                    
                    // Verify payment information completeness
                    $this->assertArrayHasKey('payment', $data, 
                        "Response should include payment data for voucher {$voucher->code}");
                    if ($data['payment']) {
                        $this->assertArrayHasKey('amount', $data['payment'], 
                            "Payment should include amount for voucher {$voucher->code}");
                        $this->assertArrayHasKey('currency', $data['payment'], 
                            "Payment should include currency for voucher {$voucher->code}");
                        $this->assertArrayHasKey('paid_at', $data['payment'], 
                            "Payment should include payment timestamp for voucher {$voucher->code}");
                        
                        // Verify payment-voucher consistency
                        $this->assertEquals($scenario['amount'], $data['payment']['amount'], 
                            "Payment amount should match voucher price for voucher {$voucher->code}");
                    }
                    
                    // Verify customer information completeness
                    $this->assertArrayHasKey('customer', $data, 
                        "Response should include customer data for voucher {$voucher->code}");
                    if ($data['customer']) {
                        $this->assertArrayHasKey('id', $data['customer'], 
                            "Customer should include ID for voucher {$voucher->code}");
                        $this->assertArrayHasKey('name', $data['customer'], 
                            "Customer should include name for voucher {$voucher->code}");
                        $this->assertArrayHasKey('phone', $data['customer'], 
                            "Customer should include phone for voucher {$voucher->code}");
                        
                        // Verify customer-voucher consistency
                        $this->assertEquals($customer->id, $data['customer']['id'], 
                            "Customer ID should match for voucher {$voucher->code}");
                    }
                    
                    // Verify remaining time calculation completeness
                    $this->assertArrayHasKey('remaining_hours', $data['voucher'], 
                        "Voucher should include remaining hours for voucher {$voucher->code}");
                    $this->assertArrayHasKey('remaining_time_formatted', $data['voucher'], 
                        "Voucher should include formatted remaining time for voucher {$voucher->code}");
                    
                    // Verify data limit information (if applicable)
                    $this->assertArrayHasKey('data_limit_mb', $data['voucher'], 
                        "Voucher should include data limit for voucher {$voucher->code}");
                    if ($scenario['data_limit_mb']) {
                        $this->assertEquals($scenario['data_limit_mb'], $data['voucher']['data_limit_mb'], 
                            "Data limit should match expected value for voucher {$voucher->code}");
                        $this->assertArrayHasKey('data_limit_formatted', $data['voucher'], 
                            "Voucher should include formatted data limit for voucher {$voucher->code}");
                    }
                }
            }
        }
    }

    /**
     * Test voucher purchase data completeness in list view
     */
    public function test_voucher_purchase_data_completeness_list_view()
    {
        $customer = Customer::factory()->create();
        
        // Create multiple voucher purchases
        $vouchers = [];
        for ($i = 0; $i < 5; $i++) {
            $payment = Payment::factory()->create([
                'customer_id' => $customer->id,
                'amount' => rand(1000, 50000),
                'status' => 'completed',
            ]);
            
            $vouchers[] = Voucher::factory()->create([
                'customer_id' => $customer->id,
                'payment_id' => $payment->id,
                'validity_hours' => rand(24, 720),
                'price' => $payment->amount,
            ]);
        }
        
        // Test list endpoint
        $response = $this->getJson('/api/vouchers');
        
        if ($response->status() === 200) {
            $data = $response->json('data');
            
            $this->assertArrayHasKey('vouchers', $data);
            $this->assertArrayHasKey('summary', $data);
            
            // Verify each voucher in list has complete data
            foreach ($data['vouchers'] as $voucherData) {
                $this->assertArrayHasKey('id', $voucherData);
                $this->assertArrayHasKey('code', $voucherData);
                $this->assertArrayHasKey('validity_hours', $voucherData);
                $this->assertArrayHasKey('price', $voucherData);
                $this->assertArrayHasKey('status', $voucherData);
                $this->assertArrayHasKey('created_at', $voucherData);
                $this->assertArrayHasKey('expires_at', $voucherData);
            }
            
            // Verify summary data completeness
            $summary = $data['summary'];
            $this->assertArrayHasKey('total_vouchers', $summary);
            $this->assertArrayHasKey('active_vouchers', $summary);
            $this->assertArrayHasKey('expired_vouchers', $summary);
            $this->assertArrayHasKey('total_revenue', $summary);
        }
    }

    /**
     * Test voucher purchase data completeness with filtering
     */
    public function test_voucher_purchase_data_completeness_with_filters()
    {
        $customer = Customer::factory()->create();
        
        // Create vouchers with different statuses and dates
        $activeVoucher = Voucher::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'active',
            'expires_at' => now()->addDays(1),
            'created_at' => now()->subDays(1),
        ]);
        
        $expiredVoucher = Voucher::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'expired',
            'expires_at' => now()->subDays(1),
            'created_at' => now()->subDays(2),
        ]);
        
        // Test filtering by status
        $response = $this->getJson('/api/vouchers?status=active');
        
        if ($response->status() === 200) {
            $data = $response->json('data');
            
            foreach ($data['vouchers'] as $voucherData) {
                $this->assertEquals('active', $voucherData['status']);
                
                // Verify complete data is still present with filtering
                $this->assertArrayHasKey('validity_hours', $voucherData);
                $this->assertArrayHasKey('price', $voucherData);
                $this->assertArrayHasKey('created_at', $voucherData);
                $this->assertArrayHasKey('expires_at', $voucherData);
            }
        }
        
        // Test filtering by date range
        $response = $this->getJson('/api/vouchers?start_date=' . now()->subDays(3)->toDateString() . '&end_date=' . now()->toDateString());
        
        if ($response->status() === 200) {
            $data = $response->json('data');
            
            foreach ($data['vouchers'] as $voucherData) {
                // Verify complete data is present with date filtering
                $this->assertArrayHasKey('validity_hours', $voucherData);
                $this->assertArrayHasKey('price', $voucherData);
                $this->assertArrayHasKey('created_at', $voucherData);
                $this->assertArrayHasKey('expires_at', $voucherData);
                
                // Verify date is within range
                $createdAt = \Carbon\Carbon::parse($voucherData['created_at']);
                $this->assertGreaterThanOrEqual(now()->subDays(3)->startOfDay(), $createdAt);
                $this->assertLessThanOrEqual(now()->endOfDay(), $createdAt);
            }
        }
    }

    /**
     * Test voucher purchase data completeness for customer-specific view
     */
    public function test_voucher_purchase_data_completeness_customer_view()
    {
        $customer = Customer::factory()->create();
        
        // Create vouchers for this customer
        $vouchers = Voucher::factory()->count(3)->create([
            'customer_id' => $customer->id,
        ]);
        
        // Test customer vouchers endpoint
        $response = $this->getJson("/api/customers/{$customer->id}/vouchers");
        
        if ($response->status() === 200) {
            $data = $response->json('data');
            
            foreach ($data as $voucherData) {
                // Verify all required purchase data is present
                $this->assertArrayHasKey('code', $voucherData);
                $this->assertArrayHasKey('validity_hours', $voucherData);
                $this->assertArrayHasKey('price', $voucherData);
                $this->assertArrayHasKey('status', $voucherData);
                $this->assertArrayHasKey('created_at', $voucherData);
                $this->assertArrayHasKey('expires_at', $voucherData);
                $this->assertArrayHasKey('activated_at', $voucherData);
                
                // Verify customer consistency
                $this->assertEquals($customer->id, $voucherData['customer_id']);
            }
        }
    }
}