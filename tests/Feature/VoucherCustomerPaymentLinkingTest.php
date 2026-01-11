<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherCustomerPaymentLinkingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Feature: vue-frontend-enhancement, Property 26: Voucher-Customer-Payment Linking
     * 
     * Property: For any voucher purchase, the system should maintain proper relationships 
     * between the voucher, customer account, and payment method used.
     * 
     * Validates: Requirements 10.5
     */
    public function test_voucher_customer_payment_linking_property()
    {
        // Create test data
        $tenant = Tenant::factory()->create();
        $customers = Customer::factory()->count(3)->create([
            'tenant_id' => $tenant->id,
        ]);
        
        foreach ($customers as $customer) {
            // Create different payment scenarios
            $paymentScenarios = [
                [
                    'amount' => 5000,
                    'currency' => 'UGX',
                    'status' => 'completed',
                    'gateway' => 'collectug',
                    'transaction_id' => 'TXN-' . \Str::random(10),
                ],
                [
                    'amount' => 15000,
                    'currency' => 'UGX',
                    'status' => 'completed',
                    'gateway' => 'mtn_momo',
                    'transaction_id' => 'MTN-' . \Str::random(10),
                ],
                [
                    'amount' => 25000,
                    'currency' => 'UGX',
                    'status' => 'completed',
                    'gateway' => 'airtel_money',
                    'transaction_id' => 'ATL-' . \Str::random(10),
                ],
            ];
            
            foreach ($paymentScenarios as $scenario) {
                // Create payment record
                $payment = Payment::create([
                    'uuid' => \Str::orderedUuid(),
                    'customer_id' => $customer->id,
                    'tenant_id' => $tenant->id,
                    'amount' => $scenario['amount'],
                    'currency' => $scenario['currency'],
                    'status' => $scenario['status'],
                    'gateway' => $scenario['gateway'],
                    'transaction_id' => $scenario['transaction_id'],
                    'paid_at' => now(),
                    'metadata' => [
                        'gateway' => $scenario['gateway'],
                        'package' => 'test_package',
                    ],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Create voucher linked to payment and customer
                $voucher = Voucher::create([
                    'uuid' => \Str::orderedUuid(),
                    'customer_id' => $customer->id,
                    'payment_id' => $payment->id,
                    'tenant_id' => $tenant->id,
                    'code' => 'BIL-' . strtoupper(\Str::random(4)) . '-' . strtoupper(\Str::random(4)),
                    'password' => \Str::random(8),
                    'profile' => '1GB-DAILY',
                    'validity_hours' => 24,
                    'price' => $scenario['amount'],
                    'currency' => $scenario['currency'],
                    'status' => 'active',
                    'activated_at' => now(),
                    'expires_at' => now()->addHours(24),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Test voucher-customer linking
                $this->assertEquals($customer->id, $voucher->customer_id, 
                    "Voucher should be linked to correct customer for voucher {$voucher->code}");
                
                // Test voucher-payment linking
                $this->assertEquals($payment->id, $voucher->payment_id, 
                    "Voucher should be linked to correct payment for voucher {$voucher->code}");
                
                // Test payment-customer linking
                $this->assertEquals($customer->id, $payment->customer_id, 
                    "Payment should be linked to correct customer for payment {$payment->transaction_id}");
                
                // Test data consistency across relationships
                $this->assertEquals($voucher->price, $payment->amount, 
                    "Voucher price should match payment amount for voucher {$voucher->code}");
                $this->assertEquals($voucher->currency, $payment->currency, 
                    "Voucher currency should match payment currency for voucher {$voucher->code}");
                
                // Test relationship loading through Eloquent
                $voucherWithRelations = Voucher::with(['customer', 'payment'])->find($voucher->id);
                
                $this->assertNotNull($voucherWithRelations->customer, 
                    "Voucher should load customer relationship for voucher {$voucher->code}");
                $this->assertNotNull($voucherWithRelations->payment, 
                    "Voucher should load payment relationship for voucher {$voucher->code}");
                
                // Verify customer relationship data
                $this->assertEquals($customer->id, $voucherWithRelations->customer->id, 
                    "Loaded customer should match expected customer for voucher {$voucher->code}");
                $this->assertEquals($customer->name, $voucherWithRelations->customer->name, 
                    "Customer name should be accessible through relationship for voucher {$voucher->code}");
                $this->assertEquals($customer->phone, $voucherWithRelations->customer->phone, 
                    "Customer phone should be accessible through relationship for voucher {$voucher->code}");
                
                // Verify payment relationship data
                $this->assertEquals($payment->id, $voucherWithRelations->payment->id, 
                    "Loaded payment should match expected payment for voucher {$voucher->code}");
                $this->assertEquals($payment->transaction_id, $voucherWithRelations->payment->transaction_id, 
                    "Payment transaction ID should be accessible through relationship for voucher {$voucher->code}");
                $this->assertEquals($payment->gateway, $voucherWithRelations->payment->gateway, 
                    "Payment gateway should be accessible through relationship for voucher {$voucher->code}");
                
                // Test reverse relationships
                $customerWithVouchers = Customer::with('vouchers')->find($customer->id);
                $customerVoucherIds = $customerWithVouchers->vouchers->pluck('id')->toArray();
                $this->assertContains($voucher->id, $customerVoucherIds, 
                    "Customer should have voucher in relationship for customer {$customer->id}");
                
                $customerWithPayments = Customer::with('payments')->find($customer->id);
                $customerPaymentIds = $customerWithPayments->payments->pluck('id')->toArray();
                $this->assertContains($payment->id, $customerPaymentIds, 
                    "Customer should have payment in relationship for customer {$customer->id}");
                
                $paymentWithVoucher = Payment::with('voucher')->find($payment->id);
                if ($paymentWithVoucher->voucher) {
                    $this->assertEquals($voucher->id, $paymentWithVoucher->voucher->id, 
                        "Payment should have correct voucher relationship for payment {$payment->transaction_id}");
                }
            }
        }
    }

    /**
     * Test linking integrity with multiple vouchers per customer
     */
    public function test_multiple_vouchers_per_customer_linking()
    {
        $customer = Customer::factory()->create();
        
        // Create multiple payments and vouchers for the same customer
        $voucherCount = 5;
        $createdVouchers = [];
        $createdPayments = [];
        
        for ($i = 0; $i < $voucherCount; $i++) {
            $payment = Payment::create([
                'uuid' => \Str::orderedUuid(),
                'customer_id' => $customer->id,
                'amount' => rand(1000, 50000),
                'currency' => 'UGX',
                'status' => 'completed',
                'transaction_id' => 'TXN-' . $i . '-' . \Str::random(8),
                'paid_at' => now()->subMinutes($i * 10),
                'created_at' => now()->subMinutes($i * 10),
                'updated_at' => now()->subMinutes($i * 10),
            ]);
            
            $voucher = Voucher::create([
                'uuid' => \Str::orderedUuid(),
                'customer_id' => $customer->id,
                'payment_id' => $payment->id,
                'code' => 'MULTI-' . $i . '-' . strtoupper(\Str::random(4)),
                'password' => \Str::random(8),
                'profile' => '1GB-DAILY',
                'validity_hours' => 24,
                'price' => $payment->amount,
                'currency' => 'UGX',
                'status' => 'active',
                'created_at' => now()->subMinutes($i * 10),
                'updated_at' => now()->subMinutes($i * 10),
            ]);
            
            $createdVouchers[] = $voucher;
            $createdPayments[] = $payment;
        }
        
        // Verify all vouchers are linked to the same customer
        foreach ($createdVouchers as $voucher) {
            $this->assertEquals($customer->id, $voucher->customer_id, 
                "All vouchers should be linked to the same customer");
        }
        
        // Verify each voucher has a unique payment
        $paymentIds = array_column($createdVouchers->toArray(), 'payment_id');
        $this->assertEquals(count($paymentIds), count(array_unique($paymentIds)), 
            "Each voucher should have a unique payment");
        
        // Verify customer can access all vouchers
        $customerWithVouchers = Customer::with('vouchers')->find($customer->id);
        $this->assertCount($voucherCount, $customerWithVouchers->vouchers, 
            "Customer should have all created vouchers");
        
        // Verify customer can access all payments
        $customerWithPayments = Customer::with('payments')->find($customer->id);
        $this->assertCount($voucherCount, $customerWithPayments->payments, 
            "Customer should have all created payments");
    }

    /**
     * Test linking integrity with payment method variations
     */
    public function test_payment_method_linking_variations()
    {
        $customer = Customer::factory()->create();
        
        // Test different payment methods and their linking
        $paymentMethods = [
            ['gateway' => 'collectug', 'method' => 'mobile_money'],
            ['gateway' => 'mtn_momo', 'method' => 'mtn_mobile_money'],
            ['gateway' => 'airtel_money', 'method' => 'airtel_mobile_money'],
            ['gateway' => 'bank_transfer', 'method' => 'bank_account'],
            ['gateway' => 'cash', 'method' => 'cash_payment'],
        ];
        
        foreach ($paymentMethods as $method) {
            $payment = Payment::create([
                'uuid' => \Str::orderedUuid(),
                'customer_id' => $customer->id,
                'amount' => 10000,
                'currency' => 'UGX',
                'status' => 'completed',
                'gateway' => $method['gateway'],
                'transaction_id' => strtoupper($method['gateway']) . '-' . \Str::random(8),
                'paid_at' => now(),
                'metadata' => [
                    'payment_method' => $method['method'],
                    'gateway' => $method['gateway'],
                ],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $voucher = Voucher::create([
                'uuid' => \Str::orderedUuid(),
                'customer_id' => $customer->id,
                'payment_id' => $payment->id,
                'code' => strtoupper($method['gateway']) . '-' . strtoupper(\Str::random(4)),
                'password' => \Str::random(8),
                'profile' => '1GB-DAILY',
                'validity_hours' => 24,
                'price' => 10000,
                'currency' => 'UGX',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Verify linking regardless of payment method
            $this->assertEquals($customer->id, $voucher->customer_id, 
                "Voucher should be linked to customer for {$method['gateway']} payment");
            $this->assertEquals($payment->id, $voucher->payment_id, 
                "Voucher should be linked to payment for {$method['gateway']} payment");
            
            // Verify payment method information is preserved
            $voucherWithPayment = Voucher::with('payment')->find($voucher->id);
            $this->assertEquals($method['gateway'], $voucherWithPayment->payment->gateway, 
                "Payment gateway should be preserved for {$method['gateway']} payment");
            
            if (isset($voucherWithPayment->payment->metadata['payment_method'])) {
                $this->assertEquals($method['method'], $voucherWithPayment->payment->metadata['payment_method'], 
                    "Payment method should be preserved for {$method['gateway']} payment");
            }
        }
    }

    /**
     * Test linking integrity with failed payments
     */
    public function test_linking_with_failed_payments()
    {
        $customer = Customer::factory()->create();
        
        // Create a failed payment
        $failedPayment = Payment::create([
            'uuid' => \Str::orderedUuid(),
            'customer_id' => $customer->id,
            'amount' => 10000,
            'currency' => 'UGX',
            'status' => 'failed',
            'gateway' => 'collectug',
            'transaction_id' => 'FAILED-' . \Str::random(8),
            'metadata' => [
                'failure_reason' => 'insufficient_funds',
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Verify no voucher should be created for failed payment
        $vouchersForFailedPayment = Voucher::where('payment_id', $failedPayment->id)->count();
        $this->assertEquals(0, $vouchersForFailedPayment, 
            "No vouchers should exist for failed payments");
        
        // Create a successful payment after the failed one
        $successfulPayment = Payment::create([
            'uuid' => \Str::orderedUuid(),
            'customer_id' => $customer->id,
            'amount' => 10000,
            'currency' => 'UGX',
            'status' => 'completed',
            'gateway' => 'collectug',
            'transaction_id' => 'SUCCESS-' . \Str::random(8),
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $voucher = Voucher::create([
            'uuid' => \Str::orderedUuid(),
            'customer_id' => $customer->id,
            'payment_id' => $successfulPayment->id,
            'code' => 'SUCCESS-' . strtoupper(\Str::random(4)),
            'password' => \Str::random(8),
            'profile' => '1GB-DAILY',
            'validity_hours' => 24,
            'price' => 10000,
            'currency' => 'UGX',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        // Verify voucher is linked to successful payment only
        $this->assertEquals($successfulPayment->id, $voucher->payment_id, 
            "Voucher should be linked to successful payment");
        $this->assertNotEquals($failedPayment->id, $voucher->payment_id, 
            "Voucher should not be linked to failed payment");
        
        // Verify customer has both payments but only one voucher
        $customerWithRelations = Customer::with(['payments', 'vouchers'])->find($customer->id);
        $this->assertCount(2, $customerWithRelations->payments, 
            "Customer should have both failed and successful payments");
        $this->assertCount(1, $customerWithRelations->vouchers, 
            "Customer should have only one voucher for successful payment");
    }
}