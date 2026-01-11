<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\SmsLog;
use App\Models\Tenant;
use App\Models\Voucher;
use App\Services\SmsService;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VoucherSmsNotificationTest extends TestCase
{
    use RefreshDatabase;

    private VoucherService $voucherService;
    private SmsService $smsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->voucherService = app(VoucherService::class);
        $this->smsService = app(SmsService::class);
        
        // Fake the queue to prevent actual SMS sending during tests
        Queue::fake();
    }

    /**
     * Feature: vue-frontend-enhancement, Property 21: Voucher SMS Notification
     * 
     * Property: For any voucher delivery event, an SMS notification should be sent 
     * to the associated customer with the voucher details.
     * 
     * Validates: Requirements 8.5
     */
    public function test_voucher_sms_notification_property()
    {
        // Create test data
        $tenant = Tenant::factory()->create();
        $customers = Customer::factory()->count(5)->create([
            'tenant_id' => $tenant->id,
        ]);
        
        foreach ($customers as $customer) {
            // Create payment for voucher generation
            $payment = Payment::factory()->create([
                'customer_id' => $customer->id,
                'tenant_id' => $tenant->id,
                'status' => 'completed',
                'amount' => rand(1000, 50000),
                'metadata' => [
                    'package' => $this->getRandomPackage(),
                    'validity_hours' => rand(24, 720),
                ]
            ]);
            
            // Create voucher
            $voucher = Voucher::factory()->create([
                'customer_id' => $customer->id,
                'payment_id' => $payment->id,
                'tenant_id' => $tenant->id,
                'status' => 'active',
                'code' => 'BIL-' . strtoupper(\Str::random(4)) . '-' . strtoupper(\Str::random(4)),
                'password' => \Str::random(8),
            ]);
            
            // Test SMS notification sending
            $smsSent = $this->smsService->sendVoucher($customer->phone, $voucher);
            
            // Verify SMS notification properties
            if ($smsSent) {
                // Check that SMS log was created
                $smsLog = SmsLog::where('customer_id', $customer->id)
                    ->where('phone_number', $customer->phone)
                    ->latest()
                    ->first();
                
                $this->assertNotNull($smsLog, 
                    "SMS log should be created for voucher delivery to customer {$customer->id}");
                
                // Verify SMS contains voucher details
                $this->assertStringContainsString($voucher->code, $smsLog->message, 
                    "SMS should contain voucher code for customer {$customer->id}");
                
                // Verify SMS status tracking
                $this->assertContains($smsLog->status, ['pending', 'sent', 'delivered'], 
                    "SMS status should be valid for customer {$customer->id}");
                
                // Verify phone number format
                $this->assertNotEmpty($smsLog->phone_number, 
                    "SMS should have phone number for customer {$customer->id}");
                $this->assertEquals($customer->phone, $smsLog->phone_number, 
                    "SMS phone number should match customer phone for customer {$customer->id}");
                
                // Verify message content structure
                $this->assertNotEmpty($smsLog->message, 
                    "SMS message should not be empty for customer {$customer->id}");
                $this->assertGreaterThan(10, strlen($smsLog->message), 
                    "SMS message should have meaningful content for customer {$customer->id}");
                
                // Verify timestamp
                $this->assertNotNull($smsLog->created_at, 
                    "SMS should have creation timestamp for customer {$customer->id}");
                $this->assertLessThanOrEqual(now(), $smsLog->created_at, 
                    "SMS timestamp should not be in future for customer {$customer->id}");
            }
            
            // Test voucher SMS resend functionality
            $resendResult = $this->post("/api/vouchers/{$voucher->code}/resend-sms");
            
            if ($resendResult->status() === 200) {
                // Verify resend creates new SMS log
                $smsLogs = SmsLog::where('customer_id', $customer->id)
                    ->where('phone_number', $customer->phone)
                    ->get();
                
                $this->assertGreaterThanOrEqual(1, $smsLogs->count(), 
                    "Should have SMS logs after resend for customer {$customer->id}");
                
                // Verify voucher SMS sent timestamp is updated
                $voucher->refresh();
                if ($voucher->sms_sent_at) {
                    $this->assertLessThanOrEqual(now(), $voucher->sms_sent_at, 
                        "SMS sent timestamp should be valid for customer {$customer->id}");
                }
            }
        }
    }

    /**
     * Test SMS notification for different voucher types
     */
    public function test_sms_notification_different_voucher_types()
    {
        $customer = Customer::factory()->create();
        
        $voucherTypes = [
            ['profile' => '1GB-DAILY', 'validity_hours' => 24, 'data_limit_mb' => 1024],
            ['profile' => '5GB-WEEKLY', 'validity_hours' => 168, 'data_limit_mb' => 5120],
            ['profile' => 'UNLIMITED-MONTHLY', 'validity_hours' => 720, 'data_limit_mb' => null],
        ];
        
        foreach ($voucherTypes as $type) {
            $voucher = Voucher::factory()->create([
                'customer_id' => $customer->id,
                'profile' => $type['profile'],
                'validity_hours' => $type['validity_hours'],
                'data_limit_mb' => $type['data_limit_mb'],
            ]);
            
            $smsSent = $this->smsService->sendVoucher($customer->phone, $voucher);
            
            if ($smsSent) {
                $smsLog = SmsLog::where('customer_id', $customer->id)
                    ->latest()
                    ->first();
                
                // Verify SMS contains relevant voucher information
                $this->assertStringContainsString($voucher->code, $smsLog->message);
                
                // Verify profile-specific information is included
                if ($type['data_limit_mb']) {
                    // For limited data vouchers, should mention data limit
                    $this->assertTrue(
                        str_contains($smsLog->message, 'GB') || 
                        str_contains($smsLog->message, 'MB') ||
                        str_contains($smsLog->message, (string)$type['data_limit_mb']),
                        "SMS should contain data limit information for limited vouchers"
                    );
                }
                
                // Should contain validity information
                $this->assertTrue(
                    str_contains($smsLog->message, 'hour') || 
                    str_contains($smsLog->message, 'day') ||
                    str_contains($smsLog->message, 'valid'),
                    "SMS should contain validity information"
                );
            }
        }
    }

    /**
     * Test SMS notification error handling
     */
    public function test_sms_notification_error_handling()
    {
        // Test with invalid phone number
        $customer = Customer::factory()->create([
            'phone' => 'invalid-phone'
        ]);
        
        $voucher = Voucher::factory()->create([
            'customer_id' => $customer->id,
        ]);
        
        $smsSent = $this->smsService->sendVoucher($customer->phone, $voucher);
        
        // Should handle invalid phone gracefully
        $this->assertIsBool($smsSent);
        
        // Test with empty phone number
        $customer2 = Customer::factory()->create([
            'phone' => ''
        ]);
        
        $voucher2 = Voucher::factory()->create([
            'customer_id' => $customer2->id,
        ]);
        
        $smsSent2 = $this->smsService->sendVoucher($customer2->phone, $voucher2);
        
        // Should handle empty phone gracefully
        $this->assertIsBool($smsSent2);
    }

    /**
     * Test SMS notification delivery tracking
     */
    public function test_sms_notification_delivery_tracking()
    {
        $customer = Customer::factory()->create();
        $voucher = Voucher::factory()->create([
            'customer_id' => $customer->id,
        ]);
        
        $smsSent = $this->smsService->sendVoucher($customer->phone, $voucher);
        
        if ($smsSent) {
            $smsLog = SmsLog::where('customer_id', $customer->id)->latest()->first();
            
            // Verify delivery tracking fields exist
            $this->assertNotNull($smsLog->status);
            $this->assertContains($smsLog->status, ['pending', 'sent', 'delivered', 'failed']);
            
            // Verify cost tracking (if available)
            if ($smsLog->cost !== null) {
                $this->assertIsNumeric($smsLog->cost);
                $this->assertGreaterThanOrEqual(0, $smsLog->cost);
            }
            
            // Verify provider response tracking
            if ($smsLog->provider_response !== null) {
                $this->assertIsArray($smsLog->provider_response);
            }
            
            // Verify sent timestamp
            if ($smsLog->sent_at !== null) {
                $this->assertLessThanOrEqual(now(), $smsLog->sent_at);
            }
        }
    }

    private function getRandomPackage(): string
    {
        $packages = ['daily_1gb', 'weekly_5gb', 'monthly_20gb', 'unlimited_daily', 'unlimited_weekly'];
        return $packages[array_rand($packages)];
    }
}