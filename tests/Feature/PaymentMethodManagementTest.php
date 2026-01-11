<?php

namespace Tests\Feature;

use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentMethodManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /**
     * Feature: vue-frontend-enhancement, Property 32: Payment Method Management
     * 
     * Property: For any payment gateway configuration, individual payment methods should be 
     * able to be enabled or disabled independently.
     * 
     * Validates: Requirements 12.4
     */
    public function test_payment_method_management_property()
    {
        $providers = ['collectug', 'stripe', 'paypal'];
        $testIterations = 100;
        
        for ($i = 0; $i < $testIterations; $i++) {
            // Generate random gateway configuration
            $provider = fake()->randomElement($providers);
            $isActive = fake()->boolean();
            
            $configuration = match ($provider) {
                'collectug' => [
                    'api_key' => fake()->uuid(),
                    'base_url' => 'https://api.collectug.com'
                ],
                'stripe' => [
                    'secret_key' => 'sk_test_' . fake()->regexify('[A-Za-z0-9]{24}'),
                    'webhook_secret' => 'whsec_' . fake()->regexify('[A-Za-z0-9]{32}')
                ],
                'paypal' => [
                    'client_id' => fake()->regexify('[A-Za-z0-9]{80}'),
                    'client_secret' => fake()->regexify('[A-Za-z0-9]{80}'),
                    'environment' => fake()->randomElement(['sandbox', 'live'])
                ]
            };
            
            // Create gateway
            $gateway = PaymentGateway::factory()->create([
                'provider' => $provider,
                'is_active' => $isActive,
                'configuration' => $configuration
            ]);
            
            // Test: Individual payment methods should be able to be enabled or disabled independently
            
            // 1. Test enabling/disabling gateway using model methods
            $originalStatus = $gateway->is_active;
            
            if ($originalStatus) {
                $result = $gateway->deactivate();
                $this->assertTrue($result);
                $gateway->refresh();
                $this->assertFalse($gateway->is_active);
            } else {
                $result = $gateway->activate();
                $this->assertTrue($result);
                $gateway->refresh();
                $this->assertTrue($gateway->is_active);
            }
            
            // 2. Test that supported methods are correctly returned based on provider
            $expectedMethods = match ($provider) {
                'collectug' => ['mobile_money'],
                'stripe' => ['card', 'bank_transfer'],
                'paypal' => ['paypal', 'card'],
                default => []
            };
            
            $this->assertEquals($expectedMethods, $gateway->supported_methods);
            
            // 3. Test that supported currencies are correctly returned based on provider
            $expectedCurrencies = match ($provider) {
                'collectug' => ['UGX'],
                'stripe' => ['USD', 'EUR', 'UGX'],
                'paypal' => ['USD', 'EUR'],
                default => []
            };
            
            $this->assertEquals($expectedCurrencies, $gateway->supported_currencies);
            
            // 4. Test configuration validation
            $this->assertTrue($gateway->isConfigured());
            
            // 5. Test currency and method support
            foreach ($expectedCurrencies as $currency) {
                $this->assertTrue($gateway->canProcessCurrency($currency));
            }
            
            foreach ($expectedMethods as $method) {
                $this->assertTrue($gateway->canProcessMethod($method));
            }
            
            // Test unsupported currency/method
            $this->assertFalse($gateway->canProcessCurrency('INVALID'));
            $this->assertFalse($gateway->canProcessMethod('invalid_method'));
            
            // Clean up for next iteration
            $gateway->delete();
        }
    }

    /**
     * Feature: vue-frontend-enhancement, Property 32: Payment Method Management
     * 
     * Property: For any payment gateway configuration, configuration validation should work 
     * correctly and required fields should be enforced.
     * 
     * Validates: Requirements 12.4
     */
    public function test_payment_method_configuration_validation_property()
    {
        $testIterations = 50;
        
        for ($i = 0; $i < $testIterations; $i++) {
            $provider = fake()->randomElement(['collectug', 'stripe', 'paypal']);
            
            // Test with valid configuration
            $validConfig = match ($provider) {
                'collectug' => [
                    'api_key' => fake()->uuid(),
                    'base_url' => 'https://api.collectug.com'
                ],
                'stripe' => [
                    'secret_key' => 'sk_test_' . fake()->regexify('[A-Za-z0-9]{24}')
                ],
                'paypal' => [
                    'client_id' => fake()->regexify('[A-Za-z0-9]{80}'),
                    'client_secret' => fake()->regexify('[A-Za-z0-9]{80}'),
                    'environment' => fake()->randomElement(['sandbox', 'live'])
                ]
            };
            
            $gateway = PaymentGateway::factory()->create([
                'provider' => $provider,
                'configuration' => $validConfig
            ]);
            
            // Test: Valid configuration should be recognized as configured
            $this->assertTrue($gateway->isConfigured());
            
            // Test: Required configuration fields should be correct
            $requiredFields = $gateway->getRequiredConfigurationFields();
            $expectedFields = match ($provider) {
                'collectug' => ['api_key', 'base_url'],
                'stripe' => ['secret_key'],
                'paypal' => ['client_id', 'client_secret', 'environment'],
                default => []
            };
            
            $this->assertEquals($expectedFields, $requiredFields);
            
            // Test: Configuration update should work
            $newConfig = $validConfig;
            if ($provider === 'collectug') {
                $newConfig['api_key'] = fake()->uuid();
            } elseif ($provider === 'stripe') {
                $newConfig['secret_key'] = 'sk_test_' . fake()->regexify('[A-Za-z0-9]{24}');
            } elseif ($provider === 'paypal') {
                $newConfig['client_id'] = fake()->regexify('[A-Za-z0-9]{80}');
            }
            
            $result = $gateway->updateConfiguration($newConfig);
            $this->assertTrue($result);
            
            $gateway->refresh();
            $this->assertEquals($newConfig, $gateway->configuration);
            
            // Test: Incomplete configuration should be detected
            $incompleteConfig = [];
            $gateway->update(['configuration' => $incompleteConfig]);
            $gateway->refresh();
            $this->assertFalse($gateway->isConfigured());
            
            // Clean up
            $gateway->delete();
        }
    }

    /**
     * Feature: vue-frontend-enhancement, Property 32: Payment Method Management
     * 
     * Property: For any system configuration with multiple gateways, each gateway should be 
     * manageable independently without affecting others.
     * 
     * Validates: Requirements 12.4
     */
    public function test_multiple_gateways_independent_management_property()
    {
        $testIterations = 30;
        
        for ($i = 0; $i < $testIterations; $i++) {
            // Create multiple gateways with different providers
            $gateways = [];
            $providers = ['collectug', 'stripe', 'paypal'];
            
            foreach ($providers as $provider) {
                $configuration = match ($provider) {
                    'collectug' => [
                        'api_key' => fake()->uuid(),
                        'base_url' => 'https://api.collectug.com'
                    ],
                    'stripe' => [
                        'secret_key' => 'sk_test_' . fake()->regexify('[A-Za-z0-9]{24}')
                    ],
                    'paypal' => [
                        'client_id' => fake()->regexify('[A-Za-z0-9]{80}'),
                        'client_secret' => fake()->regexify('[A-Za-z0-9]{80}'),
                        'environment' => 'sandbox'
                    ]
                };
                
                $gateway = PaymentGateway::factory()->create([
                    'provider' => $provider,
                    'is_active' => true,
                    'configuration' => $configuration
                ]);
                
                $gateways[] = $gateway;
            }
            
            // Test: Disabling one gateway should not affect others
            $gatewayToDisable = fake()->randomElement($gateways);
            
            $result = $gatewayToDisable->deactivate();
            $this->assertTrue($result);
            
            // Verify the disabled gateway is inactive
            $gatewayToDisable->refresh();
            $this->assertFalse($gatewayToDisable->is_active);
            
            // Verify other gateways remain active
            foreach ($gateways as $gateway) {
                if ($gateway->id !== $gatewayToDisable->id) {
                    $gateway->refresh();
                    $this->assertTrue($gateway->is_active);
                }
            }
            
            // Test: Each gateway should maintain its own supported methods
            foreach ($gateways as $gateway) {
                $expectedMethods = match ($gateway->provider) {
                    'collectug' => ['mobile_money'],
                    'stripe' => ['card', 'bank_transfer'],
                    'paypal' => ['paypal', 'card'],
                    default => []
                };
                
                $this->assertEquals($expectedMethods, $gateway->supported_methods);
            }
            
            // Test: Gateway statistics should be independent
            foreach ($gateways as $gateway) {
                $stats = $gateway->statistics;
                if ($gateway->is_active) {
                    $this->assertIsArray($stats);
                    $this->assertArrayHasKey('success_rate', $stats);
                    $this->assertArrayHasKey('total_transactions', $stats);
                    $this->assertArrayHasKey('total_volume', $stats);
                } else {
                    $this->assertNull($stats);
                }
            }
            
            // Clean up
            foreach ($gateways as $gateway) {
                $gateway->delete();
            }
        }
    }
}