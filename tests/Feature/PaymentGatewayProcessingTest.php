<?php

namespace Tests\Feature;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\DTOs\Payment\PaymentRequestDTO;
use App\DTOs\Payment\PaymentResponseDTO;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\Payment\CollectUgService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentGatewayProcessingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Feature: vue-frontend-enhancement, Property 27: Payment Gateway Processing
     * 
     * Property: For any payment request, the system should route it through the correct 
     * configured gateway and track the transaction status appropriately.
     * 
     * Validates: Requirements 11.4
     */
    public function test_payment_gateway_processing_property()
    {
        // Generate random test data for property-based testing
        for ($i = 0; $i < 100; $i++) {
            $this->runPaymentGatewayProcessingTest();
        }
    }

    private function runPaymentGatewayProcessingTest(): void
    {
        // Generate random payment data
        $amount = fake()->randomFloat(2, 1000, 100000); // UGX 1,000 to 100,000
        $currency = fake()->randomElement(['UGX']);
        $customerPhone = '256' . fake()->numberBetween(700000000, 799999999);
        $customerEmail = fake()->optional()->email();
        $description = fake()->sentence();
        $metadata = [
            'package' => fake()->randomElement(['basic', 'premium', 'enterprise']),
            'validity_hours' => fake()->randomElement([24, 48, 72, 168]),
            'customer_id' => fake()->uuid(),
        ];

        // Mock HTTP responses for CollectUG
        Http::fake([
            '*/api/v1/payments/collect' => Http::response([
                'success' => true,
                'message' => 'Payment initiated successfully',
                'transaction' => [
                    'transaction_id' => 'CUG-' . fake()->uuid(),
                    'status' => 'pending',
                    'amount' => $amount,
                    'currency' => $currency,
                ]
            ], 200),
            '*/api/v1/payments/verify/*' => Http::response([
                'success' => true,
                'message' => 'Payment verified',
                'transaction' => [
                    'transaction_id' => 'CUG-' . fake()->uuid(),
                    'status' => fake()->randomElement(['completed', 'pending', 'failed']),
                    'amount' => $amount,
                    'currency' => $currency,
                    'paid_at' => now()->toISOString(),
                ]
            ], 200),
        ]);

        // Create payment gateway service
        $gatewayConfig = [
            'api_key' => 'test-api-key-' . fake()->uuid(),
            'base_url' => 'https://api.collectug.com',
            'callback_url' => 'https://example.com/callback',
        ];

        $gateway = new CollectUgService($gatewayConfig);

        // Create payment request DTO
        $paymentRequest = new PaymentRequestDTO(
            amount: $amount,
            currency: $currency,
            customerPhone: $customerPhone,
            customerEmail: $customerEmail,
            description: $description,
            metadata: $metadata
        );

        // Test payment initialization
        $response = $gateway->initializePayment($paymentRequest);

        // Verify response structure and correctness
        $this->assertInstanceOf(PaymentResponseDTO::class, $response);
        $this->assertIsBool($response->success);
        $this->assertIsString($response->transactionId);
        $this->assertIsString($response->message);
        $this->assertIsArray($response->providerResponse);

        // If successful, verify transaction tracking
        if ($response->success) {
            $this->assertNotNull($response->reference);
            $this->assertTrue($response->requiresMobileConfirmation);
            
            // Test payment verification
            $verificationResponse = $gateway->verifyPayment($response->reference);
            $this->assertInstanceOf(PaymentResponseDTO::class, $verificationResponse);
            $this->assertIsBool($verificationResponse->success);
            $this->assertIsString($verificationResponse->message);
        }

        // Test that gateway routes requests correctly
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        
        // Verify supported currencies are returned
        $supportedCurrencies = $gateway->getSupportedCurrencies();
        $this->assertIsArray($supportedCurrencies);
        $this->assertContains($currency, $supportedCurrencies);
    }

    /**
     * Test payment gateway routing with multiple gateways
     */
    public function test_multiple_gateway_routing()
    {
        // Test that different payment requests can be routed to different gateways
        $gateways = ['collectug', 'stripe', 'paypal'];
        
        foreach ($gateways as $gatewayType) {
            // Mock different gateway responses
            Http::fake([
                '*/api/v1/payments/collect' => Http::response([
                    'success' => true,
                    'message' => "Payment initiated via {$gatewayType}",
                    'transaction' => [
                        'transaction_id' => strtoupper($gatewayType) . '-' . fake()->uuid(),
                        'status' => 'pending',
                        'gateway_type' => $gatewayType,
                    ]
                ], 200),
            ]);

            // Only test CollectUG for now since it's the implemented gateway
            if ($gatewayType === 'collectug') {
                $gateway = new CollectUgService([
                    'api_key' => 'test-key',
                    'base_url' => 'https://api.collectug.com',
                    'callback_url' => 'https://example.com/callback',
                ]);

                $paymentRequest = new PaymentRequestDTO(
                    amount: fake()->randomFloat(2, 1000, 50000),
                    currency: 'UGX',
                    customerPhone: '256700000000',
                    customerEmail: fake()->email(),
                    description: "Test payment via {$gatewayType}",
                    metadata: ['gateway_type' => $gatewayType]
                );

                $response = $gateway->initializePayment($paymentRequest);
                
                // Verify gateway-specific processing
                $this->assertInstanceOf(PaymentResponseDTO::class, $response);
                $this->assertStringContainsString($gatewayType, strtolower($response->message));
            }
        }
    }

    /**
     * Test payment status tracking accuracy
     */
    public function test_payment_status_tracking()
    {
        // Test completed status
        Http::fake([
            '*/api/v1/payments/verify/*' => Http::response([
                'success' => true,
                'message' => "Payment is completed",
                'transaction' => [
                    'transaction_id' => 'TEST-' . fake()->uuid(),
                    'status' => 'completed',
                    'amount' => fake()->randomFloat(2, 1000, 50000),
                    'currency' => 'UGX',
                ]
            ], 200),
        ]);

        $gateway = new CollectUgService([
            'api_key' => 'test-key',
            'base_url' => 'https://api.collectug.com',
            'callback_url' => 'https://example.com/callback',
        ]);

        $response = $gateway->verifyPayment('test-transaction-id');
        $this->assertTrue($response->success);
        $this->assertStringContainsString('completed', strtolower($response->message));

        // Test failed status
        Http::fake([
            '*/api/v1/payments/verify/*' => Http::response([
                'success' => true,
                'message' => "Payment is failed",
                'transaction' => [
                    'transaction_id' => 'TEST-' . fake()->uuid(),
                    'status' => 'failed',
                    'amount' => fake()->randomFloat(2, 1000, 50000),
                    'currency' => 'UGX',
                ]
            ], 200),
        ]);

        $response = $gateway->verifyPayment('test-transaction-id');
        $this->assertFalse($response->success);
        $this->assertStringContainsString('failed', strtolower($response->message));

        // Test API failure
        Http::fake([
            '*/api/v1/payments/verify/*' => Http::response([
                'success' => false,
                'message' => "Verification failed",
            ], 400),
        ]);

        $response = $gateway->verifyPayment('test-transaction-id');
        $this->assertFalse($response->success);
        $this->assertStringContainsString('verification failed', strtolower($response->message));
    }
}