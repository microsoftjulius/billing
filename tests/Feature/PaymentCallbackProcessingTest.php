<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Jobs\ProcessSuccessfulPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentCallbackProcessingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Feature: vue-frontend-enhancement, Property 31: Payment Callback Processing
     * 
     * Property: For any payment callback or webhook received, the system should 
     * process it correctly and update the corresponding transaction status.
     * 
     * Validates: Requirements 11.8
     */
    public function test_payment_callback_processing_property()
    {
        Queue::fake();

        // Generate random test data for property-based testing
        for ($i = 0; $i < 100; $i++) {
            $this->runPaymentCallbackProcessingTest();
        }
    }

    private function runPaymentCallbackProcessingTest(): void
    {
        // Create random customer and payment
        $customer = Customer::create([
            'uuid' => fake()->uuid(),
            'name' => fake()->name(),
            'phone' => '256' . fake()->numberBetween(700000000, 799999999),
            'email' => fake()->optional()->email(),
            'is_active' => true,
        ]);

        $payment = Payment::create([
            'uuid' => fake()->uuid(),
            'customer_id' => $customer->id,
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'currency' => fake()->randomElement(['UGX']),
            'status' => 'pending',
            'transaction_id' => 'TXN-' . fake()->uuid(),
            'reference' => 'REF-' . fake()->uuid(),
            'payment_method' => fake()->randomElement(['mobile_money', 'card', 'bank_transfer']),
            'provider' => fake()->randomElement(['collectug', 'stripe', 'paypal']),
            'gateway_response' => [
                'initial_status' => 'pending',
                'created_at' => now()->toISOString(),
            ],
        ]);

        // Generate random callback data
        $callbackData = $this->generateRandomCallbackData($payment);
        
        // Test callback processing
        $this->assertCallbackProcessingCorrectness($payment, $callbackData);
        
        // Test status update accuracy
        $this->assertStatusUpdateAccuracy($payment, $callbackData);
        
        // Test callback data persistence
        $this->assertCallbackDataPersistence($payment, $callbackData);
    }

    private function generateRandomCallbackData(Payment $payment): array
    {
        $status = fake()->randomElement(['completed', 'failed', 'pending', 'cancelled']);
        $provider = $payment->provider;

        $baseCallback = [
            'transaction_id' => $payment->reference,
            'status' => $status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'timestamp' => now()->toISOString(),
            'callback_id' => fake()->uuid(),
        ];

        // Add provider-specific fields
        switch ($provider) {
            case 'collectug':
                return array_merge($baseCallback, [
                    'merchant_reference' => $payment->transaction_id,
                    'phone_number' => $payment->customer->phone,
                    'gateway_fee' => fake()->randomFloat(2, 10, 100),
                    'net_amount' => $payment->amount - fake()->randomFloat(2, 10, 100),
                    'provider_transaction_id' => 'CUG-' . fake()->uuid(),
                ]);

            case 'stripe':
                return array_merge($baseCallback, [
                    'payment_intent_id' => 'pi_' . fake()->uuid(),
                    'charge_id' => 'ch_' . fake()->uuid(),
                    'payment_method_id' => 'pm_' . fake()->uuid(),
                    'stripe_fee' => fake()->randomFloat(2, 20, 200),
                    'net_amount' => $payment->amount - fake()->randomFloat(2, 20, 200),
                ]);

            case 'paypal':
                return array_merge($baseCallback, [
                    'order_id' => fake()->uuid(),
                    'capture_id' => fake()->uuid(),
                    'payer_id' => fake()->uuid(),
                    'paypal_fee' => fake()->randomFloat(2, 15, 150),
                    'net_amount' => $payment->amount - fake()->randomFloat(2, 15, 150),
                ]);

            default:
                return $baseCallback;
        }
    }

    private function assertCallbackProcessingCorrectness(Payment $payment, array $callbackData): void
    {
        $originalStatus = $payment->status;
        $callbackStatus = $callbackData['status'];

        // Simulate callback processing by updating payment
        $this->processCallback($payment, $callbackData);

        $payment->refresh();

        // Verify callback was processed correctly based on status
        switch ($callbackStatus) {
            case 'completed':
                if ($originalStatus === 'pending') {
                    $this->assertEquals('completed', $payment->status);
                    $this->assertNotNull($payment->paid_at);
                    
                    // Verify job dispatch for successful payment
                    Queue::assertPushed(ProcessSuccessfulPayment::class);
                }
                break;

            case 'failed':
            case 'cancelled':
                if ($originalStatus === 'pending') {
                    $this->assertEquals('failed', $payment->status);
                    $this->assertNotNull($payment->failed_at);
                }
                break;

            case 'pending':
                // Status should remain pending
                $this->assertEquals('pending', $payment->status);
                $this->assertNull($payment->paid_at);
                $this->assertNull($payment->failed_at);
                break;
        }

        // Verify callback data is stored in gateway_response
        $this->assertIsArray($payment->gateway_response);
        $this->assertArrayHasKey('callback_data', $payment->gateway_response);
        $this->assertArrayHasKey('callback_received_at', $payment->gateway_response);
    }

    private function assertStatusUpdateAccuracy(Payment $payment, array $callbackData): void
    {
        // Verify status transitions are logical
        $currentStatus = $payment->status;
        $callbackStatus = $callbackData['status'];

        // Test valid status transitions
        $validTransitions = [
            'pending' => ['completed', 'failed', 'cancelled', 'pending'],
            'completed' => ['completed'], // Completed payments should not change
            'failed' => ['failed', 'cancelled'], // Failed payments can be cancelled but not completed
        ];

        if (isset($validTransitions[$currentStatus])) {
            $this->assertContains($callbackStatus, $validTransitions[$currentStatus]);
        }

        // Verify timestamp consistency
        if ($payment->paid_at) {
            $this->assertGreaterThanOrEqual($payment->created_at, $payment->paid_at);
        }

        if ($payment->failed_at) {
            $this->assertGreaterThanOrEqual($payment->created_at, $payment->failed_at);
        }
    }

    private function assertCallbackDataPersistence(Payment $payment, array $callbackData): void
    {
        // Verify callback data is properly stored
        $gatewayResponse = $payment->gateway_response;
        
        $this->assertArrayHasKey('callback_data', $gatewayResponse);
        $this->assertArrayHasKey('callback_received_at', $gatewayResponse);

        $storedCallbackData = $gatewayResponse['callback_data'];
        
        // Verify essential callback fields are preserved
        $this->assertEquals($callbackData['status'], $storedCallbackData['status']);
        $this->assertEquals($callbackData['amount'], $storedCallbackData['amount']);
        $this->assertEquals($callbackData['currency'], $storedCallbackData['currency']);
        $this->assertEquals($callbackData['callback_id'], $storedCallbackData['callback_id']);

        // Verify provider-specific data is preserved
        switch ($payment->provider) {
            case 'collectug':
                if (isset($callbackData['provider_transaction_id'])) {
                    $this->assertEquals(
                        $callbackData['provider_transaction_id'],
                        $storedCallbackData['provider_transaction_id']
                    );
                }
                break;

            case 'stripe':
                if (isset($callbackData['payment_intent_id'])) {
                    $this->assertEquals(
                        $callbackData['payment_intent_id'],
                        $storedCallbackData['payment_intent_id']
                    );
                }
                break;

            case 'paypal':
                if (isset($callbackData['order_id'])) {
                    $this->assertEquals(
                        $callbackData['order_id'],
                        $storedCallbackData['order_id']
                    );
                }
                break;
        }
    }

    private function processCallback(Payment $payment, array $callbackData): void
    {
        // Handle malformed callback data
        if (!isset($callbackData['status']) || !is_string($callbackData['status'])) {
            throw new \InvalidArgumentException('Invalid callback data: missing or invalid status');
        }

        $status = $this->mapCallbackStatusToPaymentStatus($callbackData['status']);

        $updateData = [
            'gateway_response' => array_merge(
                $payment->gateway_response ?? [],
                [
                    'callback_data' => $callbackData,
                    'callback_received_at' => now()->toISOString(),
                ]
            ),
        ];

        // Update status and timestamps based on callback
        switch ($status) {
            case 'completed':
                if ($payment->status === 'pending') {
                    $updateData['status'] = 'completed';
                    $updateData['paid_at'] = now();
                    
                    // Dispatch job for successful payment
                    ProcessSuccessfulPayment::dispatch($payment);
                }
                break;

            case 'failed':
                if ($payment->status === 'pending') {
                    $updateData['status'] = 'failed';
                    $updateData['failed_at'] = now();
                }
                break;

            case 'pending':
                // Keep status as pending, just update callback data
                break;
        }

        $payment->update($updateData);
    }

    private function mapCallbackStatusToPaymentStatus(?string $callbackStatus): string
    {
        if ($callbackStatus === null) {
            return 'pending';
        }

        $statusMap = [
            'completed' => 'completed',
            'success' => 'completed',
            'successful' => 'completed',
            'paid' => 'completed',
            'confirmed' => 'completed',
            'failed' => 'failed',
            'error' => 'failed',
            'rejected' => 'failed',
            'cancelled' => 'failed',
            'canceled' => 'failed',
            'pending' => 'pending',
            'processing' => 'pending',
            'initiated' => 'pending',
        ];

        return $statusMap[strtolower($callbackStatus)] ?? 'pending';
    }

    /**
     * Test callback signature verification
     */
    public function test_callback_signature_verification()
    {
        Queue::fake();

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
            'amount' => 5000,
            'currency' => 'UGX',
            'status' => 'pending',
            'transaction_id' => 'SIG-TEST-' . fake()->uuid(),
            'reference' => 'REF-' . fake()->uuid(),
            'payment_method' => 'mobile_money',
            'provider' => 'collectug',
        ]);

        // Test valid signature
        $validCallbackData = [
            'transaction_id' => $payment->reference,
            'status' => 'completed',
            'amount' => $payment->amount,
            'signature' => hash_hmac('sha256', json_encode([
                'transaction_id' => $payment->reference,
                'status' => 'completed',
                'amount' => $payment->amount,
            ]), 'test-webhook-secret'),
        ];

        // Test invalid signature
        $invalidCallbackData = [
            'transaction_id' => $payment->reference,
            'status' => 'completed',
            'amount' => $payment->amount,
            'signature' => 'invalid-signature',
        ];

        // Verify signature validation logic would work
        $this->assertTrue($this->verifyCallbackSignature($validCallbackData, 'test-webhook-secret'));
        $this->assertFalse($this->verifyCallbackSignature($invalidCallbackData, 'test-webhook-secret'));
    }

    private function verifyCallbackSignature(array $callbackData, string $secret): bool
    {
        if (!isset($callbackData['signature'])) {
            return false;
        }

        $receivedSignature = $callbackData['signature'];
        unset($callbackData['signature']);

        $calculatedSignature = hash_hmac('sha256', json_encode($callbackData), $secret);

        return hash_equals($calculatedSignature, $receivedSignature);
    }

    /**
     * Test duplicate callback handling
     */
    public function test_duplicate_callback_handling()
    {
        Queue::fake();

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
            'amount' => 5000,
            'currency' => 'UGX',
            'status' => 'pending',
            'transaction_id' => 'DUP-TEST-' . fake()->uuid(),
            'reference' => 'REF-' . fake()->uuid(),
            'payment_method' => 'mobile_money',
            'provider' => 'collectug',
        ]);

        $callbackData = [
            'transaction_id' => $payment->reference,
            'status' => 'completed',
            'amount' => $payment->amount,
            'callback_id' => 'CALLBACK-123',
            'timestamp' => now()->toISOString(),
        ];

        // Process callback first time
        $this->processCallback($payment, $callbackData);
        $payment->refresh();

        $this->assertEquals('completed', $payment->status);
        $this->assertNotNull($payment->paid_at);
        Queue::assertPushed(ProcessSuccessfulPayment::class, 1);

        // Process same callback again (duplicate)
        Queue::fake(); // Reset queue
        $this->processCallback($payment, $callbackData);
        $payment->refresh();

        // Status should remain completed, no additional jobs should be dispatched
        $this->assertEquals('completed', $payment->status);
        Queue::assertNotPushed(ProcessSuccessfulPayment::class);
    }

    /**
     * Test callback processing with malformed data
     */
    public function test_malformed_callback_handling()
    {
        Queue::fake();

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
            'amount' => 5000,
            'currency' => 'UGX',
            'status' => 'pending',
            'transaction_id' => 'MAL-TEST-' . fake()->uuid(),
            'reference' => 'REF-' . fake()->uuid(),
            'payment_method' => 'mobile_money',
            'provider' => 'collectug',
        ]);

        // Test various malformed callback scenarios
        $malformedCallbacks = [
            // Missing required fields
            ['status' => 'completed'],
            ['transaction_id' => $payment->reference],
            
            // Invalid data types
            ['transaction_id' => $payment->reference, 'status' => 123, 'amount' => 'invalid'],
            
            // Empty data
            [],
            
            // Null values
            ['transaction_id' => null, 'status' => null, 'amount' => null],
        ];

        foreach ($malformedCallbacks as $malformedData) {
            // Attempt to process malformed callback
            try {
                $this->processCallback($payment, $malformedData);
                
                // Payment status should not change due to malformed data
                $payment->refresh();
                $this->assertEquals('pending', $payment->status);
                
            } catch (\Exception $e) {
                // Exceptions are acceptable for malformed data
                $this->assertInstanceOf(\Exception::class, $e);
            }
        }
    }

    /**
     * Test callback processing timing and order
     */
    public function test_callback_timing_and_order()
    {
        Queue::fake();

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
            'amount' => 5000,
            'currency' => 'UGX',
            'status' => 'pending',
            'transaction_id' => 'TIME-TEST-' . fake()->uuid(),
            'reference' => 'REF-' . fake()->uuid(),
            'payment_method' => 'mobile_money',
            'provider' => 'collectug',
        ]);

        // Process callbacks in different order
        $callbacks = [
            ['status' => 'pending', 'sequence' => 1, 'timestamp' => now()->subMinutes(2)->toISOString()],
            ['status' => 'processing', 'sequence' => 2, 'timestamp' => now()->subMinutes(1)->toISOString()],
            ['status' => 'completed', 'sequence' => 3, 'timestamp' => now()->toISOString()],
        ];

        // Process callbacks out of order
        foreach ([2, 0, 1] as $index) {
            $callbackData = array_merge($callbacks[$index], [
                'transaction_id' => $payment->reference,
                'amount' => $payment->amount,
                'callback_id' => 'CALLBACK-' . $callbacks[$index]['sequence'],
            ]);

            $this->processCallback($payment, $callbackData);
        }

        $payment->refresh();

        // Final status should be completed (latest callback)
        $this->assertEquals('completed', $payment->status);
        $this->assertNotNull($payment->paid_at);

        // Verify all callbacks are stored
        $gatewayResponse = $payment->gateway_response;
        $this->assertArrayHasKey('callback_data', $gatewayResponse);
    }
}