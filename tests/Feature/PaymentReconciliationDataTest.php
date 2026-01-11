<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentReconciliationDataTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Feature: vue-frontend-enhancement, Property 29: Payment Reconciliation Data
     * 
     * Property: For any payment transaction, the system should provide complete 
     * reconciliation data and maintain accurate transaction history.
     * 
     * Validates: Requirements 11.6
     */
    public function test_payment_reconciliation_data_property()
    {
        // Generate random test data for property-based testing
        for ($i = 0; $i < 100; $i++) {
            $this->runPaymentReconciliationTest();
        }
    }

    private function runPaymentReconciliationTest(): void
    {
        // Create random customer
        $customer = Customer::create([
            'uuid' => fake()->uuid(),
            'name' => fake()->name(),
            'phone' => '256' . fake()->numberBetween(700000000, 799999999),
            'email' => fake()->optional()->email(),
            'is_active' => true,
        ]);

        // Create random payment with comprehensive data
        $amount = fake()->randomFloat(2, 1000, 100000);
        $currency = fake()->randomElement(['UGX']);
        $status = fake()->randomElement(['pending', 'completed', 'failed', 'refunded']);
        $provider = fake()->randomElement(['collectug', 'stripe', 'paypal']);
        $paymentMethod = fake()->randomElement(['mobile_money', 'card', 'bank_transfer']);
        
        $gatewayResponse = [
            'transaction_id' => fake()->uuid(),
            'gateway_reference' => fake()->uuid(),
            'status' => $status,
            'amount' => $amount,
            'currency' => $currency,
            'timestamp' => now()->toISOString(),
            'gateway_fee' => fake()->randomFloat(2, 10, 500),
            'net_amount' => $amount - fake()->randomFloat(2, 10, 500),
        ];

        $metadata = [
            'package' => fake()->randomElement(['basic', 'premium', 'enterprise']),
            'validity_hours' => fake()->randomElement([24, 48, 72, 168]),
            'customer_ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'request_timestamp' => now()->toISOString(),
        ];

        $payment = Payment::create([
            'uuid' => fake()->uuid(),
            'customer_id' => $customer->id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'transaction_id' => 'TXN-' . fake()->uuid(),
            'reference' => 'REF-' . fake()->uuid(),
            'payment_method' => $paymentMethod,
            'provider' => $provider,
            'paid_at' => $status === 'completed' ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
            'refunded_at' => $status === 'refunded' ? now() : null,
            'gateway_response' => $gatewayResponse,
            'metadata' => $metadata,
        ]);

        // Test reconciliation data completeness
        $this->assertReconciliationDataCompleteness($payment);
        
        // Test transaction history accuracy
        $this->assertTransactionHistoryAccuracy($payment);
        
        // Test data integrity across relationships
        $this->assertDataIntegrityAcrossRelationships($payment, $customer);
    }

    private function assertReconciliationDataCompleteness(Payment $payment): void
    {
        // Verify all required reconciliation fields are present
        $this->assertNotNull($payment->transaction_id);
        $this->assertNotNull($payment->reference);
        $this->assertNotNull($payment->amount);
        $this->assertNotNull($payment->currency);
        $this->assertNotNull($payment->status);
        $this->assertNotNull($payment->payment_method);
        $this->assertNotNull($payment->provider);
        $this->assertNotNull($payment->created_at);

        // Verify gateway response contains reconciliation data
        $this->assertIsArray($payment->gateway_response);
        $this->assertArrayHasKey('transaction_id', $payment->gateway_response);
        $this->assertArrayHasKey('status', $payment->gateway_response);
        $this->assertArrayHasKey('amount', $payment->gateway_response);
        $this->assertArrayHasKey('currency', $payment->gateway_response);

        // Verify metadata contains audit trail information
        $this->assertIsArray($payment->metadata);
        $this->assertArrayHasKey('request_timestamp', $payment->metadata);

        // Verify status-specific timestamps
        switch ($payment->status) {
            case 'completed':
                $this->assertNotNull($payment->paid_at);
                break;
            case 'failed':
                $this->assertNotNull($payment->failed_at);
                break;
            case 'refunded':
                $this->assertNotNull($payment->refunded_at);
                break;
        }
    }

    private function assertTransactionHistoryAccuracy(Payment $payment): void
    {
        // Verify payment can be retrieved by various identifiers
        $foundByTransactionId = Payment::where('transaction_id', $payment->transaction_id)->first();
        $this->assertNotNull($foundByTransactionId);
        $this->assertEquals($payment->id, $foundByTransactionId->id);

        $foundByReference = Payment::where('reference', $payment->reference)->first();
        $this->assertNotNull($foundByReference);
        $this->assertEquals($payment->id, $foundByReference->id);

        // Verify amount consistency
        $this->assertEquals($payment->amount, $foundByTransactionId->amount);
        $this->assertEquals($payment->currency, $foundByTransactionId->currency);

        // Verify gateway response consistency
        $this->assertEquals($payment->gateway_response, $foundByTransactionId->gateway_response);

        // Verify timestamps are preserved
        $this->assertEquals($payment->created_at, $foundByTransactionId->created_at);
        $this->assertEquals($payment->updated_at, $foundByTransactionId->updated_at);
    }

    private function assertDataIntegrityAcrossRelationships(Payment $payment, Customer $customer): void
    {
        // Verify customer relationship integrity
        $this->assertEquals($customer->id, $payment->customer_id);
        
        $paymentCustomer = $payment->customer;
        $this->assertNotNull($paymentCustomer);
        $this->assertEquals($customer->id, $paymentCustomer->id);
        $this->assertEquals($customer->name, $paymentCustomer->name);
        $this->assertEquals($customer->phone, $paymentCustomer->phone);

        // Verify customer can access their payments
        $customerPayments = $customer->payments;
        $this->assertTrue($customerPayments->contains('id', $payment->id));

        // If payment is completed, verify voucher relationship if exists
        if ($payment->status === 'completed') {
            $voucher = $payment->voucher;
            if ($voucher) {
                $this->assertEquals($payment->id, $voucher->payment_id);
                $this->assertEquals($customer->id, $voucher->customer_id);
            }
        }
    }

    /**
     * Test reconciliation data for bulk payments
     */
    public function test_bulk_payment_reconciliation()
    {
        $customer = Customer::create([
            'uuid' => fake()->uuid(),
            'name' => fake()->name(),
            'phone' => '256700000000',
            'email' => fake()->email(),
            'is_active' => true,
        ]);

        $payments = collect();
        $totalAmount = 0;

        // Create multiple payments for reconciliation testing
        for ($i = 0; $i < 20; $i++) {
            $amount = fake()->randomFloat(2, 1000, 10000);
            $totalAmount += $amount;

            $payment = Payment::create([
                'uuid' => fake()->uuid(),
                'customer_id' => $customer->id,
                'amount' => $amount,
                'currency' => 'UGX',
                'status' => fake()->randomElement(['completed', 'pending', 'failed']),
                'transaction_id' => 'BULK-' . $i . '-' . fake()->uuid(),
                'reference' => 'REF-' . $i . '-' . fake()->uuid(),
                'payment_method' => 'mobile_money',
                'provider' => 'collectug',
                'paid_at' => fake()->boolean() ? now() : null,
                'gateway_response' => [
                    'batch_id' => 'BATCH-' . fake()->uuid(),
                    'sequence' => $i,
                    'amount' => $amount,
                ],
                'metadata' => [
                    'batch_processing' => true,
                    'batch_sequence' => $i,
                ],
            ]);

            $payments->push($payment);
        }

        // Test bulk reconciliation data
        $completedPayments = $payments->where('status', 'completed');
        $pendingPayments = $payments->where('status', 'pending');
        $failedPayments = $payments->where('status', 'failed');

        // Verify all payments are tracked
        $this->assertEquals(20, $payments->count());
        
        // Verify reconciliation totals
        $calculatedTotal = $payments->sum('amount');
        $this->assertEquals($totalAmount, $calculatedTotal);

        // Verify each payment has complete reconciliation data
        foreach ($payments as $payment) {
            $this->assertNotNull($payment->transaction_id);
            $this->assertNotNull($payment->reference);
            $this->assertIsArray($payment->gateway_response);
            $this->assertArrayHasKey('batch_id', $payment->gateway_response);
            $this->assertArrayHasKey('sequence', $payment->gateway_response);
        }

        // Verify customer relationship integrity for all payments
        $customerPayments = Payment::where('customer_id', $customer->id)->get();
        $this->assertEquals(20, $customerPayments->count());
    }

    /**
     * Test reconciliation data with payment updates
     */
    public function test_payment_update_reconciliation_tracking()
    {
        $customer = Customer::create([
            'uuid' => fake()->uuid(),
            'name' => fake()->name(),
            'phone' => '256700000000',
            'email' => fake()->email(),
            'is_active' => true,
        ]);

        // Create pending payment
        $payment = Payment::create([
            'uuid' => fake()->uuid(),
            'customer_id' => $customer->id,
            'amount' => 5000,
            'currency' => 'UGX',
            'status' => 'pending',
            'transaction_id' => 'UPDATE-TEST-' . fake()->uuid(),
            'reference' => 'REF-' . fake()->uuid(),
            'payment_method' => 'mobile_money',
            'provider' => 'collectug',
            'gateway_response' => [
                'initial_status' => 'pending',
                'created_at' => now()->toISOString(),
            ],
        ]);

        $originalUpdatedAt = $payment->updated_at;

        // Wait a moment to ensure timestamp difference
        sleep(1);

        // Update payment to completed
        $payment->update([
            'status' => 'completed',
            'paid_at' => now(),
            'gateway_response' => array_merge($payment->gateway_response, [
                'final_status' => 'completed',
                'completed_at' => now()->toISOString(),
                'gateway_fee' => 50,
                'net_amount' => 4950,
            ]),
        ]);

        $payment->refresh();

        // Verify reconciliation data tracks the update
        $this->assertEquals('completed', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertTrue($payment->updated_at->greaterThan($originalUpdatedAt));

        // Verify gateway response contains both initial and final data
        $this->assertArrayHasKey('initial_status', $payment->gateway_response);
        $this->assertArrayHasKey('final_status', $payment->gateway_response);
        $this->assertEquals('pending', $payment->gateway_response['initial_status']);
        $this->assertEquals('completed', $payment->gateway_response['final_status']);

        // Verify reconciliation calculations
        $this->assertEquals(50, $payment->gateway_response['gateway_fee']);
        $this->assertEquals(4950, $payment->gateway_response['net_amount']);
        $this->assertEquals(5000, $payment->amount); // Original amount unchanged
    }

    /**
     * Test reconciliation data export format
     */
    public function test_reconciliation_data_export_format()
    {
        $customer = Customer::create([
            'uuid' => fake()->uuid(),
            'name' => fake()->name(),
            'phone' => '256700000000',
            'email' => fake()->email(),
            'is_active' => true,
        ]);

        $payment = Payment::create([
            'uuid' => fake()->uuid(),
            'customer_id' => $customer->id,
            'amount' => 10000,
            'currency' => 'UGX',
            'status' => 'completed',
            'transaction_id' => 'EXPORT-TEST-' . fake()->uuid(),
            'reference' => 'REF-' . fake()->uuid(),
            'payment_method' => 'mobile_money',
            'provider' => 'collectug',
            'paid_at' => now(),
            'gateway_response' => [
                'gateway_fee' => 100,
                'net_amount' => 9900,
                'exchange_rate' => 1.0,
            ],
            'metadata' => [
                'package' => 'premium',
                'validity_hours' => 72,
            ],
        ]);

        // Test that all reconciliation fields are accessible for export
        $exportData = [
            'transaction_id' => $payment->transaction_id,
            'reference' => $payment->reference,
            'customer_name' => $payment->customer->name,
            'customer_phone' => $payment->customer->phone,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'payment_method' => $payment->payment_method,
            'provider' => $payment->provider,
            'gateway_fee' => $payment->gateway_response['gateway_fee'] ?? 0,
            'net_amount' => $payment->gateway_response['net_amount'] ?? $payment->amount,
            'created_at' => $payment->created_at->toISOString(),
            'paid_at' => $payment->paid_at?->toISOString(),
            'package' => $payment->metadata['package'] ?? null,
        ];

        // Verify all export fields are populated
        foreach ($exportData as $field => $value) {
            $this->assertNotNull($value, "Export field '{$field}' should not be null");
        }

        // Verify numeric fields are properly formatted
        $this->assertIsNumeric($exportData['amount']);
        $this->assertIsNumeric($exportData['gateway_fee']);
        $this->assertIsNumeric($exportData['net_amount']);

        // Verify reconciliation math
        $expectedNetAmount = $exportData['amount'] - $exportData['gateway_fee'];
        $this->assertEquals($expectedNetAmount, $exportData['net_amount']);
    }
}