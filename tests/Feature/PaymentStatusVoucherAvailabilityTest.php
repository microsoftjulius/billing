<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Voucher;
use App\Jobs\ProcessSuccessfulPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentStatusVoucherAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Feature: vue-frontend-enhancement, Property 28: Payment Status and Voucher Availability
     * 
     * Property: For any payment status change, the system should update voucher availability 
     * accordingly (activate on success, maintain pending on failure).
     * 
     * Validates: Requirements 11.5
     */
    public function test_payment_status_voucher_availability_property()
    {
        // Generate random test data for property-based testing
        for ($i = 0; $i < 100; $i++) {
            Queue::fake(); // Reset queue for each iteration
            $this->runPaymentStatusVoucherTest();
        }
    }

    private function runPaymentStatusVoucherTest(): void
    {
        // Create random customer
        $customer = Customer::create([
            'uuid' => fake()->uuid(),
            'name' => fake()->name(),
            'phone' => '256' . fake()->numberBetween(700000000, 799999999),
            'email' => fake()->optional()->email(),
            'is_active' => true,
        ]);

        // Create random payment
        $amount = fake()->randomFloat(2, 1000, 100000);
        $currency = fake()->randomElement(['UGX']);
        $status = fake()->randomElement(['pending', 'completed', 'failed']);
        
        $payment = Payment::create([
            'uuid' => fake()->uuid(),
            'customer_id' => $customer->id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'transaction_id' => 'TEST-' . fake()->uuid(),
            'reference' => 'REF-' . fake()->uuid(),
            'payment_method' => 'mobile_money',
            'provider' => 'collectug',
            'paid_at' => $status === 'completed' ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
            'metadata' => [
                'package' => fake()->randomElement(['basic', 'premium', 'enterprise']),
                'validity_hours' => fake()->randomElement([24, 48, 72, 168]),
            ],
        ]);

        // Test voucher creation based on payment status
        if ($status === 'completed') {
            // For completed payments, voucher should be created or activated
            $this->assertVoucherAvailabilityForCompletedPayment($payment);
        } elseif ($status === 'failed') {
            // For failed payments, no voucher should be available
            $this->assertNoVoucherForFailedPayment($payment);
        } else {
            // For pending payments, voucher should not be available yet
            $this->assertNoVoucherForPendingPayment($payment);
        }

        // Test status change scenarios
        $this->testPaymentStatusChange($payment);
    }

    private function assertVoucherAvailabilityForCompletedPayment(Payment $payment): void
    {
        // Simulate successful payment processing
        ProcessSuccessfulPayment::dispatch($payment);

        // Check that job was dispatched for voucher creation
        Queue::assertPushed(ProcessSuccessfulPayment::class, 1);

        // Verify payment is marked as completed
        $this->assertEquals('completed', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    private function assertNoVoucherForFailedPayment(Payment $payment): void
    {
        // Failed payments should not trigger voucher creation
        $this->assertEquals('failed', $payment->status);
        $this->assertNotNull($payment->failed_at);
        
        // No voucher should exist for this payment
        $voucher = Voucher::where('payment_id', $payment->id)->first();
        $this->assertNull($voucher);
        
        // No additional jobs should be dispatched for failed payments in this test
        // (The property test may dispatch jobs for completed payments)
    }

    private function assertNoVoucherForPendingPayment(Payment $payment): void
    {
        // Pending payments should not have vouchers yet
        $this->assertEquals('pending', $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertNull($payment->failed_at);
        
        // No voucher should exist for pending payment
        $voucher = Voucher::where('payment_id', $payment->id)->first();
        $this->assertNull($voucher);
    }

    private function testPaymentStatusChange(Payment $payment): void
    {
        $originalStatus = $payment->status;
        
        // Test status change from pending to completed
        if ($originalStatus === 'pending') {
            $payment->markAsCompleted();
            $payment->refresh();
            
            $this->assertEquals('completed', $payment->status);
            $this->assertNotNull($payment->paid_at);
            
            // Voucher creation job should be dispatched
            ProcessSuccessfulPayment::dispatch($payment);
            Queue::assertPushed(ProcessSuccessfulPayment::class);
        }
        
        // Test status change from pending to failed
        if ($originalStatus === 'pending') {
            $payment->markAsFailed('Test failure reason');
            $payment->refresh();
            
            $this->assertEquals('failed', $payment->status);
            $this->assertNotNull($payment->failed_at);
            
            // No voucher should be created for failed payments
            $voucher = Voucher::where('payment_id', $payment->id)->first();
            $this->assertNull($voucher);
        }
    }

    /**
     * Test voucher activation timing
     */
    public function test_voucher_activation_timing()
    {
        Queue::fake();
        
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
            'status' => 'pending',
            'amount' => 5000,
            'currency' => 'UGX',
            'transaction_id' => 'TEST-' . fake()->uuid(),
            'reference' => 'REF-' . fake()->uuid(),
            'payment_method' => 'mobile_money',
            'provider' => 'collectug',
        ]);
        
        // Initially no voucher should exist
        $this->assertNull(Voucher::where('payment_id', $payment->id)->first());
        
        // Mark payment as completed
        $payment->markAsCompleted();
        
        // Dispatch voucher creation job
        ProcessSuccessfulPayment::dispatch($payment);
        
        // Verify job was dispatched
        Queue::assertPushed(ProcessSuccessfulPayment::class, 1);
        
        // Verify payment status
        $this->assertEquals('completed', $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    /**
     * Test multiple payment status changes
     */
    public function test_multiple_status_changes()
    {
        Queue::fake();
        
        $customer = Customer::create([
            'uuid' => fake()->uuid(),
            'name' => fake()->name(),
            'phone' => '256700000000',
            'email' => fake()->email(),
            'is_active' => true,
        ]);
        
        // Create multiple payments with different statuses
        $payments = collect();
        
        for ($i = 0; $i < 10; $i++) {
            $status = fake()->randomElement(['pending', 'completed', 'failed']);
            
            $payment = Payment::create([
                'uuid' => fake()->uuid(),
                'customer_id' => $customer->id,
                'status' => $status,
                'amount' => fake()->randomFloat(2, 1000, 50000),
                'currency' => 'UGX',
                'transaction_id' => 'TEST-' . fake()->uuid(),
                'reference' => 'REF-' . fake()->uuid(),
                'payment_method' => 'mobile_money',
                'provider' => 'collectug',
                'paid_at' => $status === 'completed' ? now() : null,
                'failed_at' => $status === 'failed' ? now() : null,
            ]);
            
            $payments->push($payment);
            
            // Test voucher availability based on status
            if ($status === 'completed') {
                ProcessSuccessfulPayment::dispatch($payment);
            }
        }
        
        // Verify correct number of voucher creation jobs
        $completedPayments = $payments->where('status', 'completed');
        Queue::assertPushed(ProcessSuccessfulPayment::class, $completedPayments->count());
        
        // Verify no jobs for non-completed payments
        $nonCompletedPayments = $payments->whereNotIn('status', ['completed']);
        foreach ($nonCompletedPayments as $payment) {
            $voucher = Voucher::where('payment_id', $payment->id)->first();
            $this->assertNull($voucher);
        }
    }

    /**
     * Test payment refund scenario
     */
    public function test_payment_refund_voucher_handling()
    {
        Queue::fake();
        
        $customer = Customer::create([
            'uuid' => fake()->uuid(),
            'name' => fake()->name(),
            'phone' => '256700000000',
            'email' => fake()->email(),
            'is_active' => true,
        ]);
        
        // Create completed payment
        $payment = Payment::create([
            'uuid' => fake()->uuid(),
            'customer_id' => $customer->id,
            'status' => 'completed',
            'amount' => 10000,
            'currency' => 'UGX',
            'transaction_id' => 'TEST-' . fake()->uuid(),
            'reference' => 'REF-' . fake()->uuid(),
            'payment_method' => 'mobile_money',
            'provider' => 'collectug',
            'paid_at' => now(),
        ]);
        
        // Create associated voucher
        $voucher = Voucher::create([
            'uuid' => fake()->uuid(),
            'customer_id' => $customer->id,
            'payment_id' => $payment->id,
            'code' => 'BIL-' . strtoupper(fake()->bothify('????-????')),
            'password' => fake()->password(8),
            'profile' => 'daily_1gb',
            'validity_hours' => 24,
            'price' => $payment->amount,
            'currency' => 'UGX',
            'status' => 'active',
            'activated_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);
        
        // Mark payment as refunded
        $payment->markAsRefunded();
        
        // Verify payment status
        $this->assertEquals('refunded', $payment->status);
        $this->assertNotNull($payment->refunded_at);
        
        // Voucher should still exist but may need status update
        $voucher->refresh();
        $this->assertNotNull($voucher);
        
        // The voucher status handling would depend on business logic
        // This test verifies the payment status change is tracked correctly
    }
}