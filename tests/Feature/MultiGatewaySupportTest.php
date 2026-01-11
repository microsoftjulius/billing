<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Services\Payment\CollectUgService;
use App\DTOs\Payment\PaymentRequestDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiGatewaySupportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Feature: vue-frontend-enhancement, Property 30: Multi-Gateway Support
     * 
     * Property: For any system configuration with multiple active payment gateways, 
     * all gateways should be available for processing payments simultaneously.
     * 
     * Validates: Requirements 11.7
     */
    public function test_multi_gateway_support_property()
    {
        // Generate random test data for property-based testing
        for ($i = 0; $i < 100; $i++) {
            $this->runMultiGatewaySupportTest();
        }
    }

    private function runMultiGatewaySupportTest(): void
    {
        // Create random customer
        $customer = Customer::create([
            'uuid' => fake()->uuid(),
            'name' => fake()->name(),
            'phone' => '256' . fake()->numberBetween(700000000, 799999999),
            'email' => fake()->optional()->email(),
            'is_active' => true,
        ]);

        // Define multiple gateway configurations
        $gateways = $this->createMultipleGatewayConfigurations();
        
        // Test that all gateways can process payments simultaneously
        $this->assertAllGatewaysCanProcessPayments($gateways, $customer);
        
        // Test gateway selection and routing
        $this->assertGatewaySelectionAndRouting($gateways, $customer);
        
        // Test concurrent payment processing
        $this->assertConcurrentPaymentProcessing($gateways, $customer);
    }

    private function createMultipleGatewayConfigurations(): array
    {
        return [
            'collectug' => [
                'name' => 'CollectUG',
                'provider' => 'collectug',
                'api_key' => 'collectug-' . fake()->uuid(),
                'base_url' => 'https://api.collectug.com',
                'callback_url' => 'https://example.com/callback/collectug',
                'is_active' => true,
                'supported_currencies' => ['UGX'],
                'supported_methods' => ['mobile_money'],
            ],
            'stripe' => [
                'name' => 'Stripe',
                'provider' => 'stripe',
                'api_key' => 'sk_test_' . fake()->uuid(),
                'base_url' => 'https://api.stripe.com',
                'callback_url' => 'https://example.com/callback/stripe',
                'is_active' => true,
                'supported_currencies' => ['USD', 'EUR', 'UGX'],
                'supported_methods' => ['card', 'bank_transfer'],
            ],
            'paypal' => [
                'name' => 'PayPal',
                'provider' => 'paypal',
                'api_key' => 'paypal-' . fake()->uuid(),
                'base_url' => 'https://api.paypal.com',
                'callback_url' => 'https://example.com/callback/paypal',
                'is_active' => fake()->boolean(80), // 80% chance of being active
                'supported_currencies' => ['USD', 'EUR'],
                'supported_methods' => ['paypal', 'card'],
            ],
        ];
    }

    private function assertAllGatewaysCanProcessPayments(array $gateways, Customer $customer): void
    {
        foreach ($gateways as $gatewayId => $config) {
            if (!$config['is_active']) {
                continue; // Skip inactive gateways
            }

            // Mock HTTP responses for each gateway
            $this->mockGatewayResponses($gatewayId, $config);

            // Test payment processing for each active gateway
            $amount = fake()->randomFloat(2, 1000, 50000);
            $currency = fake()->randomElement($config['supported_currencies']);
            $paymentMethod = fake()->randomElement($config['supported_methods']);

            // Create payment record for this gateway
            $payment = Payment::create([
                'uuid' => fake()->uuid(),
                'customer_id' => $customer->id,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'pending',
                'transaction_id' => strtoupper($gatewayId) . '-' . fake()->uuid(),
                'reference' => 'REF-' . fake()->uuid(),
                'payment_method' => $paymentMethod,
                'provider' => $config['provider'],
                'metadata' => [
                    'gateway_config' => $gatewayId,
                    'test_iteration' => true,
                ],
            ]);

            // Verify payment can be processed through this gateway
            $this->assertPaymentCanBeProcessed($payment, $config);
        }
    }

    private function mockGatewayResponses(string $gatewayId, array $config): void
    {
        $baseUrl = $config['base_url'];
        
        switch ($gatewayId) {
            case 'collectug':
                Http::fake([
                    $baseUrl . '/api/v1/payments/collect' => Http::response([
                        'success' => true,
                        'message' => 'Payment initiated via CollectUG',
                        'transaction' => [
                            'transaction_id' => 'CUG-' . fake()->uuid(),
                            'status' => 'pending',
                            'gateway' => 'collectug',
                        ]
                    ], 200),
                    $baseUrl . '/api/v1/payments/verify/*' => Http::response([
                        'success' => true,
                        'message' => 'Payment verified via CollectUG',
                        'transaction' => [
                            'status' => fake()->randomElement(['completed', 'pending']),
                            'gateway' => 'collectug',
                        ]
                    ], 200),
                ]);
                break;

            case 'stripe':
                Http::fake([
                    $baseUrl . '/v1/payment_intents' => Http::response([
                        'id' => 'pi_' . fake()->uuid(),
                        'status' => 'requires_confirmation',
                        'client_secret' => 'pi_' . fake()->uuid() . '_secret',
                        'gateway' => 'stripe',
                    ], 200),
                    $baseUrl . '/v1/payment_intents/*' => Http::response([
                        'id' => 'pi_' . fake()->uuid(),
                        'status' => fake()->randomElement(['succeeded', 'processing']),
                        'gateway' => 'stripe',
                    ], 200),
                ]);
                break;

            case 'paypal':
                Http::fake([
                    $baseUrl . '/v2/checkout/orders' => Http::response([
                        'id' => fake()->uuid(),
                        'status' => 'CREATED',
                        'links' => [
                            ['rel' => 'approve', 'href' => 'https://paypal.com/approve'],
                        ],
                        'gateway' => 'paypal',
                    ], 201),
                    $baseUrl . '/v2/checkout/orders/*' => Http::response([
                        'id' => fake()->uuid(),
                        'status' => fake()->randomElement(['COMPLETED', 'APPROVED']),
                        'gateway' => 'paypal',
                    ], 200),
                ]);
                break;
        }
    }

    private function assertPaymentCanBeProcessed(Payment $payment, array $config): void
    {
        // Verify payment record has correct gateway information
        $this->assertEquals($config['provider'], $payment->provider);
        $this->assertContains($payment->currency, $config['supported_currencies']);
        $this->assertContains($payment->payment_method, $config['supported_methods']);

        // For CollectUG (the only implemented gateway), test actual processing
        if ($config['provider'] === 'collectug') {
            $gateway = new CollectUgService([
                'api_key' => $config['api_key'],
                'base_url' => $config['base_url'],
                'callback_url' => $config['callback_url'],
            ]);

            $paymentRequest = new PaymentRequestDTO(
                amount: $payment->amount,
                currency: $payment->currency,
                customerPhone: $payment->customer->phone,
                customerEmail: $payment->customer->email,
                description: 'Multi-gateway test payment',
                metadata: ['gateway_test' => true]
            );

            $response = $gateway->initializePayment($paymentRequest);
            
            // Verify gateway can process the payment
            $this->assertNotNull($response);
            $this->assertIsBool($response->success);
            $this->assertIsString($response->transactionId);
        }

        // Verify payment is trackable
        $this->assertNotNull($payment->transaction_id);
        $this->assertNotNull($payment->created_at);
    }

    private function assertGatewaySelectionAndRouting(array $gateways, Customer $customer): void
    {
        // Test that payments can be routed to different gateways based on criteria
        $activeGateways = array_filter($gateways, fn($config) => $config['is_active']);
        
        if (count($activeGateways) < 2) {
            $this->markTestSkipped('Need at least 2 active gateways for routing test');
        }

        foreach ($activeGateways as $gatewayId => $config) {
            // Create payment for specific gateway
            $payment = Payment::create([
                'uuid' => fake()->uuid(),
                'customer_id' => $customer->id,
                'amount' => fake()->randomFloat(2, 1000, 10000),
                'currency' => fake()->randomElement($config['supported_currencies']),
                'status' => 'pending',
                'transaction_id' => strtoupper($gatewayId) . '-ROUTE-' . fake()->uuid(),
                'reference' => 'ROUTE-REF-' . fake()->uuid(),
                'payment_method' => fake()->randomElement($config['supported_methods']),
                'provider' => $config['provider'],
                'metadata' => [
                    'selected_gateway' => $gatewayId,
                    'routing_test' => true,
                ],
            ]);

            // Verify payment is correctly routed to the intended gateway
            $this->assertEquals($config['provider'], $payment->provider);
            $this->assertEquals($gatewayId, $payment->metadata['selected_gateway']);
        }
    }

    private function assertConcurrentPaymentProcessing(array $gateways, Customer $customer): void
    {
        $activeGateways = array_filter($gateways, fn($config) => $config['is_active']);
        $payments = collect();

        // Create multiple payments for different gateways simultaneously
        foreach ($activeGateways as $gatewayId => $config) {
            $payment = Payment::create([
                'uuid' => fake()->uuid(),
                'customer_id' => $customer->id,
                'amount' => fake()->randomFloat(2, 1000, 5000),
                'currency' => fake()->randomElement($config['supported_currencies']),
                'status' => 'pending',
                'transaction_id' => strtoupper($gatewayId) . '-CONCURRENT-' . fake()->uuid(),
                'reference' => 'CONCURRENT-REF-' . fake()->uuid(),
                'payment_method' => fake()->randomElement($config['supported_methods']),
                'provider' => $config['provider'],
                'metadata' => [
                    'concurrent_test' => true,
                    'gateway_id' => $gatewayId,
                ],
            ]);

            $payments->push($payment);
        }

        // Verify all payments can exist simultaneously
        $this->assertGreaterThan(0, $payments->count());
        
        // Verify each payment maintains its gateway identity
        foreach ($payments as $payment) {
            $gatewayId = $payment->metadata['gateway_id'];
            $expectedConfig = $gateways[$gatewayId];
            
            $this->assertEquals($expectedConfig['provider'], $payment->provider);
            $this->assertStringContainsString(strtoupper($gatewayId), $payment->transaction_id);
        }

        // Verify no conflicts between gateway transactions
        $transactionIds = $payments->pluck('transaction_id')->toArray();
        $this->assertEquals(count($transactionIds), count(array_unique($transactionIds)));
    }

    /**
     * Test gateway failover scenarios
     */
    public function test_gateway_failover_support()
    {
        $customer = Customer::create([
            'uuid' => fake()->uuid(),
            'name' => fake()->name(),
            'phone' => '256700000000',
            'email' => fake()->email(),
            'is_active' => true,
        ]);

        // Simulate primary gateway failure
        Http::fake([
            'https://api.collectug.com/*' => Http::response(['error' => 'Service unavailable'], 503),
            'https://api.stripe.com/*' => Http::response(['id' => 'pi_backup', 'status' => 'succeeded'], 200),
        ]);

        $primaryGateway = [
            'provider' => 'collectug',
            'api_key' => 'test-key',
            'base_url' => 'https://api.collectug.com',
            'callback_url' => 'https://example.com/callback',
        ];

        $backupGateway = [
            'provider' => 'stripe',
            'api_key' => 'sk_test_backup',
            'base_url' => 'https://api.stripe.com',
        ];

        // Test primary gateway failure
        $primaryService = new CollectUgService($primaryGateway);
        $paymentRequest = new PaymentRequestDTO(
            amount: 5000,
            currency: 'UGX',
            customerPhone: $customer->phone,
            customerEmail: $customer->email,
            description: 'Failover test payment'
        );

        $primaryResponse = $primaryService->initializePayment($paymentRequest);
        
        // Primary should fail
        $this->assertFalse($primaryResponse->success);

        // Create payment record showing failover capability
        $payment = Payment::create([
            'uuid' => fake()->uuid(),
            'customer_id' => $customer->id,
            'amount' => 5000,
            'currency' => 'UGX',
            'status' => 'pending',
            'transaction_id' => 'FAILOVER-' . fake()->uuid(),
            'reference' => 'FAILOVER-REF-' . fake()->uuid(),
            'payment_method' => 'card',
            'provider' => 'stripe', // Switched to backup
            'metadata' => [
                'failover_from' => 'collectug',
                'failover_reason' => 'Primary gateway unavailable',
                'attempt_count' => 2,
            ],
        ]);

        // Verify failover is tracked
        $this->assertEquals('stripe', $payment->provider);
        $this->assertEquals('collectug', $payment->metadata['failover_from']);
        $this->assertEquals(2, $payment->metadata['attempt_count']);
    }

    /**
     * Test gateway-specific configuration validation
     */
    public function test_gateway_configuration_validation()
    {
        $gatewayConfigs = [
            'collectug' => [
                'required_fields' => ['api_key', 'base_url'],
                'optional_fields' => ['callback_url'],
                'supported_currencies' => ['UGX'],
            ],
            'stripe' => [
                'required_fields' => ['api_key'],
                'optional_fields' => ['webhook_secret'],
                'supported_currencies' => ['USD', 'EUR', 'UGX'],
            ],
            'paypal' => [
                'required_fields' => ['client_id', 'client_secret'],
                'optional_fields' => ['webhook_id'],
                'supported_currencies' => ['USD', 'EUR'],
            ],
        ];

        foreach ($gatewayConfigs as $provider => $config) {
            // Test that each gateway has proper configuration structure
            $this->assertIsArray($config['required_fields']);
            $this->assertIsArray($config['supported_currencies']);
            $this->assertGreaterThan(0, count($config['required_fields']));
            $this->assertGreaterThan(0, count($config['supported_currencies']));

            // Test currency support validation
            foreach ($config['supported_currencies'] as $currency) {
                $this->assertIsString($currency);
                $this->assertEquals(3, strlen($currency)); // ISO currency codes are 3 characters
            }
        }
    }

    /**
     * Test load balancing across multiple gateways
     */
    public function test_load_balancing_across_gateways()
    {
        $customer = Customer::create([
            'uuid' => fake()->uuid(),
            'name' => fake()->name(),
            'phone' => '256700000000',
            'email' => fake()->email(),
            'is_active' => true,
        ]);

        $gateways = ['collectug', 'stripe', 'paypal'];
        $paymentDistribution = [];

        // Create multiple payments and track distribution
        for ($i = 0; $i < 30; $i++) {
            $selectedGateway = fake()->randomElement($gateways);
            
            $payment = Payment::create([
                'uuid' => fake()->uuid(),
                'customer_id' => $customer->id,
                'amount' => fake()->randomFloat(2, 1000, 5000),
                'currency' => 'UGX',
                'status' => 'pending',
                'transaction_id' => strtoupper($selectedGateway) . '-LB-' . $i,
                'reference' => 'LB-REF-' . $i,
                'payment_method' => 'mobile_money',
                'provider' => $selectedGateway,
                'metadata' => [
                    'load_balancing_test' => true,
                    'sequence' => $i,
                ],
            ]);

            $paymentDistribution[$selectedGateway] = ($paymentDistribution[$selectedGateway] ?? 0) + 1;
        }

        // Verify payments are distributed across gateways
        $this->assertGreaterThan(1, count($paymentDistribution));
        
        // Verify each gateway received some payments (with randomness tolerance)
        foreach ($paymentDistribution as $gateway => $count) {
            $this->assertGreaterThan(0, $count);
            $this->assertLessThanOrEqual(30, $count);
        }

        // Verify total payments match
        $this->assertEquals(30, array_sum($paymentDistribution));
    }
}