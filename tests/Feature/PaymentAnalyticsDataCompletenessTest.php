<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentAnalyticsDataCompletenessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Feature: vue-frontend-enhancement, Property 54: Payment Analytics Data Completeness
     * 
     * Property: For any payment analytics view, success rates, revenue trends, 
     * and gateway performance metrics should all be included.
     * 
     * @test
     */
    public function test_payment_analytics_data_completeness_property()
    {
        // Create authenticated user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        
        // Create test data with multiple gateways
        $gateway1 = PaymentGateway::factory()->create(['name' => 'Gateway 1', 'provider' => 'collectug']);
        $gateway2 = PaymentGateway::factory()->create(['name' => 'Gateway 2', 'provider' => 'stripe']);
        
        $customer = Customer::factory()->create();
        
        // Create payments for different gateways and statuses
        Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 50000,
            'status' => 'completed',
            'metadata' => ['gateway_id' => $gateway1->id]
        ]);
        
        Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 30000,
            'status' => 'failed',
            'metadata' => ['gateway_id' => $gateway1->id]
        ]);
        
        Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 75000,
            'status' => 'completed',
            'metadata' => ['gateway_id' => $gateway2->id]
        ]);
        
        // Test main analytics endpoint
        $response = $this->getJson('/api/v1/payments/statistics');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'period',
                'total_payments',
                'completed_payments',
                'pending_payments',
                'failed_payments',
                'success_rate',
                'total_revenue',
                'average_amount',
                'popular_packages'
            ]
        ]);
        
        $data = $response->json('data');
        
        // Verify all required metrics are present and calculated correctly
        $this->assertIsInt($data['total_payments']);
        $this->assertIsInt($data['completed_payments']);
        $this->assertIsInt($data['pending_payments']);
        $this->assertIsInt($data['failed_payments']);
        $this->assertIsNumeric($data['success_rate']);
        $this->assertIsNumeric($data['total_revenue']);
        $this->assertIsNumeric($data['average_amount']);
        $this->assertIsArray($data['popular_packages']);
        
        // Verify calculations
        $this->assertEquals(3, $data['total_payments']);
        $this->assertEquals(2, $data['completed_payments']);
        $this->assertEquals(0, $data['pending_payments']);
        $this->assertEquals(1, $data['failed_payments']);
        $this->assertEquals(66.67, $data['success_rate']); // 2/3 * 100
        $this->assertEquals(125000, $data['total_revenue']); // 50000 + 75000
        $this->assertEquals(62500, $data['average_amount']); // 125000 / 2
    }

    /**
     * Feature: vue-frontend-enhancement, Property 54: Payment Analytics Data Completeness
     * 
     * Property: For any gateway analytics request, all gateway performance metrics 
     * should be included in the response.
     * 
     * @test
     */
    public function test_gateway_analytics_completeness_property()
    {
        // Create authenticated user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        
        // Create gateways with different activity levels
        $activeGateway = PaymentGateway::factory()->create([
            'name' => 'Active Gateway',
            'provider' => 'collectug',
            'is_active' => true
        ]);
        
        $inactiveGateway = PaymentGateway::factory()->create([
            'name' => 'Inactive Gateway',
            'provider' => 'stripe',
            'is_active' => false
        ]);
        
        // Test gateway analytics endpoint
        $response = $this->getJson('/api/v1/payment-gateways/analytics');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'provider',
                    'is_active',
                    'success_rate',
                    'total_volume',
                    'total_fees',
                    'transaction_count',
                    'successful_transactions',
                    'failed_transactions',
                    'average_transaction_amount',
                    'last_transaction_date'
                ]
            ]
        ]);
        
        $data = $response->json('data');
        
        // Verify both gateways are included
        $this->assertCount(2, $data);
        
        // Verify each gateway has all required metrics
        foreach ($data as $gatewayData) {
            $this->assertArrayHasKey('id', $gatewayData);
            $this->assertArrayHasKey('name', $gatewayData);
            $this->assertArrayHasKey('provider', $gatewayData);
            $this->assertArrayHasKey('is_active', $gatewayData);
            $this->assertArrayHasKey('success_rate', $gatewayData);
            $this->assertArrayHasKey('total_volume', $gatewayData);
            $this->assertArrayHasKey('total_fees', $gatewayData);
            $this->assertArrayHasKey('transaction_count', $gatewayData);
            $this->assertArrayHasKey('successful_transactions', $gatewayData);
            $this->assertArrayHasKey('failed_transactions', $gatewayData);
            $this->assertArrayHasKey('average_transaction_amount', $gatewayData);
            $this->assertArrayHasKey('last_transaction_date', $gatewayData);
            
            // Verify data types
            $this->assertIsInt($gatewayData['id']);
            $this->assertIsString($gatewayData['name']);
            $this->assertIsString($gatewayData['provider']);
            $this->assertIsBool($gatewayData['is_active']);
            $this->assertIsNumeric($gatewayData['success_rate']);
            $this->assertIsNumeric($gatewayData['total_volume']);
            $this->assertIsNumeric($gatewayData['total_fees']);
            $this->assertIsInt($gatewayData['transaction_count']);
            $this->assertIsInt($gatewayData['successful_transactions']);
            $this->assertIsInt($gatewayData['failed_transactions']);
            $this->assertIsNumeric($gatewayData['average_transaction_amount']);
        }
    }

    /**
     * Feature: vue-frontend-enhancement, Property 54: Payment Analytics Data Completeness
     * 
     * Property: For any payment list request, all required payment information 
     * should be included for analytics purposes.
     * 
     * @test
     */
    public function test_payment_list_data_completeness_property()
    {
        // Create authenticated user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        
        $customer = Customer::factory()->create();
        
        // Create payments with various statuses and metadata
        Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 25000,
            'status' => 'completed',
            'payment_method' => 'mobile_money',
            'provider' => 'collectug',
            'metadata' => [
                'package' => 'basic',
                'validity_hours' => 24
            ]
        ]);
        
        Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 50000,
            'status' => 'pending',
            'payment_method' => 'card',
            'provider' => 'stripe',
            'metadata' => [
                'package' => 'premium',
                'validity_hours' => 168
            ]
        ]);
        
        // Test payment list endpoint
        $response = $this->getJson('/api/v1/payments');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'payments' => [
                    '*' => [
                        'id',
                        'transaction_id',
                        'amount',
                        'currency',
                        'status',
                        'payment_method',
                        'provider',
                        'created_at',
                        'customer'
                    ]
                ],
                'pagination',
                'summary' => [
                    'total_amount',
                    'completed_count',
                    'pending_count',
                    'failed_count'
                ]
            ]
        ]);
        
        $data = $response->json('data');
        
        // Verify payments data completeness
        $this->assertCount(2, $data['payments']);
        
        foreach ($data['payments'] as $payment) {
            $this->assertArrayHasKey('id', $payment);
            $this->assertArrayHasKey('transaction_id', $payment);
            $this->assertArrayHasKey('amount', $payment);
            $this->assertArrayHasKey('currency', $payment);
            $this->assertArrayHasKey('status', $payment);
            $this->assertArrayHasKey('payment_method', $payment);
            $this->assertArrayHasKey('provider', $payment);
            $this->assertArrayHasKey('created_at', $payment);
            $this->assertArrayHasKey('customer', $payment);
        }
        
        // Verify summary data completeness
        $this->assertArrayHasKey('total_amount', $data['summary']);
        $this->assertArrayHasKey('completed_count', $data['summary']);
        $this->assertArrayHasKey('pending_count', $data['summary']);
        $this->assertArrayHasKey('failed_count', $data['summary']);
        
        // Verify summary calculations
        $this->assertEquals(75000, $data['summary']['total_amount']);
        $this->assertEquals(1, $data['summary']['completed_count']);
        $this->assertEquals(1, $data['summary']['pending_count']);
        $this->assertEquals(0, $data['summary']['failed_count']);
    }
}