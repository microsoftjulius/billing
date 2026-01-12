<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class PaymentGatewayConnectivityTestingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Feature: vue-frontend-enhancement, Property 55: Payment Gateway Connectivity Testing
     * 
     * Property: For any payment gateway configuration, connectivity testing should 
     * verify the gateway connection and report results.
     * 
     * @test
     */
    public function test_payment_gateway_connectivity_testing_property()
    {
        // Create authenticated user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        
        // Create gateways with different configurations
        $collectugGateway = PaymentGateway::factory()->create([
            'name' => 'CollectUG Gateway',
            'provider' => 'collectug',
            'is_active' => true,
            'configuration' => [
                'api_key' => encrypt('test-api-key'),
                'base_url' => 'https://api.collectug.com'
            ]
        ]);
        
        $stripeGateway = PaymentGateway::factory()->create([
            'name' => 'Stripe Gateway',
            'provider' => 'stripe',
            'is_active' => true,
            'configuration' => [
                'secret_key' => encrypt('sk_test_123456789')
            ]
        ]);
        
        // Mock HTTP responses for different providers
        Http::fake([
            'api.collectug.com/*' => Http::response([
                'account_status' => 'active',
                'available_balance' => 1000000,
                'currency' => 'UGX'
            ], 200),
            'api.stripe.com/*' => Http::response([
                'object' => 'account',
                'charges_enabled' => true
            ], 200)
        ]);
        
        // Test individual gateway connectivity
        $response = $this->postJson("/api/v1/payment-gateways/{$collectugGateway->id}/test");
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'details'
        ]);
        
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('successful', strtolower($data['message']));
        $this->assertArrayHasKey('details', $data);
        
        // Test Stripe gateway
        $response = $this->postJson("/api/v1/payment-gateways/{$stripeGateway->id}/test");
        
        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('successful', strtolower($data['message']));
    }

    /**
     * Feature: vue-frontend-enhancement, Property 55: Payment Gateway Connectivity Testing
     * 
     * Property: For any invalid gateway configuration, connectivity testing should 
     * fail and provide meaningful error messages.
     * 
     * @test
     */
    public function test_payment_gateway_connectivity_testing_failure_property()
    {
        // Create authenticated user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        
        // Create gateway with invalid configuration
        $invalidGateway = PaymentGateway::factory()->create([
            'name' => 'Invalid Gateway',
            'provider' => 'collectug',
            'is_active' => true,
            'configuration' => [
                'api_key' => encrypt('invalid-key'),
                'base_url' => 'https://api.collectug.com'
            ]
        ]);
        
        // Mock failed HTTP response
        Http::fake([
            'api.collectug.com/*' => Http::response([
                'error' => 'Invalid API key'
            ], 401)
        ]);
        
        // Test connectivity with invalid configuration
        $response = $this->postJson("/api/v1/payment-gateways/{$invalidGateway->id}/test");
        
        $response->assertStatus(200); // API should return 200 but with success: false
        $data = $response->json();
        
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('failed', strtolower($data['message']));
    }

    /**
     * Feature: vue-frontend-enhancement, Property 55: Payment Gateway Connectivity Testing
     * 
     * Property: For any new gateway configuration (not yet saved), connectivity 
     * testing should work with the provided configuration.
     * 
     * @test
     */
    public function test_new_gateway_configuration_testing_property()
    {
        // Create authenticated user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        
        // Mock successful HTTP response
        Http::fake([
            'api.collectug.com/*' => Http::response([
                'account_status' => 'active',
                'available_balance' => 500000,
                'currency' => 'UGX'
            ], 200)
        ]);
        
        // Test new gateway configuration without saving
        $response = $this->postJson('/api/v1/payment-gateways/test', [
            'provider' => 'collectug',
            'configuration' => [
                'api_key' => 'new-test-key',
                'base_url' => 'https://api.collectug.com'
            ]
        ]);
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'details'
        ]);
        
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('successful', strtolower($data['message']));
        $this->assertArrayHasKey('details', $data);
        $this->assertEquals('active', $data['details']['account_status']);
    }

    /**
     * Feature: vue-frontend-enhancement, Property 55: Payment Gateway Connectivity Testing
     * 
     * Property: For any multiple gateway test request, all specified gateways 
     * should be tested and results should be returned for each.
     * 
     * @test
     */
    public function test_multiple_gateway_connectivity_testing_property()
    {
        // Create authenticated user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        
        // Create multiple gateways
        $gateway1 = PaymentGateway::factory()->create([
            'name' => 'Gateway 1',
            'provider' => 'collectug',
            'configuration' => [
                'api_key' => encrypt('key1'),
                'base_url' => 'https://api.collectug.com'
            ]
        ]);
        
        $gateway2 = PaymentGateway::factory()->create([
            'name' => 'Gateway 2',
            'provider' => 'stripe',
            'configuration' => [
                'secret_key' => encrypt('sk_test_123')
            ]
        ]);
        
        $gateway3 = PaymentGateway::factory()->create([
            'name' => 'Gateway 3',
            'provider' => 'paypal',
            'configuration' => [
                'client_id' => encrypt('paypal_client'),
                'client_secret' => encrypt('paypal_secret'),
                'environment' => 'sandbox'
            ]
        ]);
        
        // Mock responses for all providers
        Http::fake([
            'api.collectug.com/*' => Http::response(['account_status' => 'active'], 200),
            'api.stripe.com/*' => Http::response(['charges_enabled' => true], 200),
            'api.paypal.com/*' => Http::response(['account_status' => 'VERIFIED'], 200)
        ]);
        
        // Test multiple gateways
        $response = $this->postJson('/api/v1/payment-gateways/test', [
            'gateway_ids' => [$gateway1->id, $gateway2->id, $gateway3->id],
            'test_transaction' => false
        ]);
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'gateway_id',
                    'gateway_name',
                    'provider',
                    'success',
                    'message',
                    'response_time',
                    'details'
                ]
            ]
        ]);
        
        $data = $response->json('data');
        
        // Verify all gateways were tested
        $this->assertCount(3, $data);
        
        // Verify each result has required fields
        foreach ($data as $result) {
            $this->assertArrayHasKey('gateway_id', $result);
            $this->assertArrayHasKey('gateway_name', $result);
            $this->assertArrayHasKey('provider', $result);
            $this->assertArrayHasKey('success', $result);
            $this->assertArrayHasKey('message', $result);
            $this->assertArrayHasKey('response_time', $result);
            
            // Verify data types
            $this->assertIsInt($result['gateway_id']);
            $this->assertIsString($result['gateway_name']);
            $this->assertIsString($result['provider']);
            $this->assertIsBool($result['success']);
            $this->assertIsString($result['message']);
            $this->assertIsNumeric($result['response_time']);
        }
        
        // Verify gateway IDs match
        $testedGatewayIds = array_column($data, 'gateway_id');
        $this->assertContains($gateway1->id, $testedGatewayIds);
        $this->assertContains($gateway2->id, $testedGatewayIds);
        $this->assertContains($gateway3->id, $testedGatewayIds);
    }
}