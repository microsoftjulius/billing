<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PaymentEnhancementsIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_it_generates_payment_analytics_with_comprehensive_data()
    {
        // Create test payments
        Payment::factory()->count(10)->create([
            'provider' => 'collectug',
            'status' => 'completed',
            'amount' => 50000
        ]);

        Payment::factory()->count(5)->create([
            'provider' => 'collectug',
            'status' => 'failed',
            'amount' => 25000
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payments/statistics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_revenue',
            'success_rate',
            'failed_payments',
            'gateway_performance',
            'revenue_trends',
            'transaction_volume'
        ]);

        // Verify analytics data completeness
        $data = $response->json();
        $this->assertGreaterThan(0, $data['total_revenue']);
        $this->assertArrayHasKey('success_rate', $data);
        $this->assertArrayHasKey('gateway_performance', $data);
    }

    public function test_it_provides_payment_analytics_data_completeness()
    {
        // Create payments with different statuses and providers
        Payment::factory()->create([
            'provider' => 'collectug',
            'status' => 'completed',
            'amount' => 100000,
            'created_at' => now()->subDays(1)
        ]);

        Payment::factory()->create([
            'provider' => 'stripe',
            'status' => 'completed',
            'amount' => 75000,
            'created_at' => now()->subDays(2)
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payments/statistics');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Verify all required analytics components are present
        $this->assertArrayHasKey('success_rates', $data);
        $this->assertArrayHasKey('revenue_trends', $data);
        $this->assertArrayHasKey('gateway_performance', $data);
        $this->assertArrayHasKey('transaction_trends', $data);
        
        // Verify gateway-specific data
        $this->assertArrayHasKey('gateway_performance', $data);
        $this->assertIsArray($data['gateway_performance']);
    }

    public function test_it_tests_payment_gateway_connectivity()
    {
        $gateway = PaymentGateway::factory()->create([
            'name' => 'CollectUG',
            'provider' => 'collectug',
            'is_active' => true
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payment-gateways/{$gateway->id}/test");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'connection_time',
            'gateway_info'
        ]);
    }

    public function test_it_performs_gateway_test_transactions()
    {
        PaymentGateway::factory()->create([
            'name' => 'CollectUG',
            'provider' => 'collectug',
            'is_active' => true
        ]);

        $testData = [
            'amount' => 1000, // Test amount in cents
            'currency' => 'UGX',
            'test_mode' => true
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payment-gateways/test', $testData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'test_results' => [
                '*' => [
                    'gateway_id',
                    'success',
                    'response_time',
                    'transaction_id'
                ]
            ]
        ]);
    }

    public function test_it_allows_payment_record_editing_with_validation()
    {
        $payment = Payment::factory()->create([
            'provider' => 'collectug',
            'amount' => 50000,
            'status' => 'completed'
        ]);

        $updateData = [
            'amount' => 55000,
            'notes' => 'Corrected amount after customer inquiry',
            'reason' => 'amount_correction'
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/payments/{$payment->id}", $updateData);

        $response->assertStatus(200);
        
        // Verify payment was updated
        $payment->refresh();
        $this->assertEquals(55000, $payment->amount);
    }

    public function test_it_provides_payment_reconciliation_tools()
    {
        // Create payments with different reconciliation statuses
        Payment::factory()->count(5)->create([
            'provider' => 'collectug',
            'status' => 'completed'
        ]);

        Payment::factory()->count(3)->create([
            'provider' => 'collectug',
            'status' => 'completed'
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/reconciliation', [
                'date_from' => now()->subDays(7)->toDateString(),
                'date_to' => now()->toDateString()
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'reconciliation_summary' => [
                'total_transactions',
                'reconciled_count',
                'unreconciled_count',
                'discrepancies'
            ],
            'unreconciled_payments',
            'discrepancies'
        ]);
    }

    public function test_it_exports_reconciliation_data()
    {
        Payment::factory()->count(10)->create([
            'provider' => 'collectug',
            'status' => 'completed'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payments/reconciliation/export?' . http_build_query([
                'format' => 'csv',
                'date_from' => now()->subDays(7)->toDateString(),
                'date_to' => now()->toDateString()
            ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv');
        $response->assertHeader('Content-Disposition');
    }

    public function test_it_verifies_payment_transactions()
    {
        $payment = Payment::factory()->create([
            'provider' => 'collectug',
            'transaction_id' => 'TXN123456',
            'status' => 'completed'
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payments/{$payment->transaction_id}/verify");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'verified',
            'gateway_status',
            'amount_match',
            'verification_details'
        ]);
    }

    public function test_it_integrates_analytics_with_gateway_testing()
    {
        // Create payments for different providers
        Payment::factory()->count(5)->create(['provider' => 'collectug', 'status' => 'completed']);
        Payment::factory()->count(3)->create(['provider' => 'stripe', 'status' => 'completed']);
        Payment::factory()->count(2)->create(['provider' => 'collectug', 'status' => 'failed']);

        // Get analytics
        $analyticsResponse = $this->actingAs($this->user)
            ->getJson('/api/v1/payment-gateways/analytics');

        $analyticsResponse->assertStatus(200);
        
        // Test gateway connectivity
        $testResponse = $this->actingAs($this->user)
            ->postJson('/api/v1/payment-gateways/test');

        $testResponse->assertStatus(200);
        
        // Verify integration between analytics and testing
        $analyticsData = $analyticsResponse->json();
        $testData = $testResponse->json();
        
        $this->assertArrayHasKey('gateway_performance', $analyticsData);
        $this->assertArrayHasKey('test_results', $testData);
    }

    public function test_it_handles_payment_editing_with_business_rules()
    {
        $payment = Payment::factory()->create([
            'provider' => 'collectug',
            'status' => 'completed',
            'amount' => 50000,
            'created_at' => now()->subDays(30) // Old payment
        ]);

        // Try to edit old payment (should have restrictions)
        $updateData = [
            'amount' => 75000,
            'reason' => 'amount_correction'
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/payments/{$payment->id}", $updateData);

        // Should either succeed with audit trail or fail with business rule validation
        $this->assertContains($response->status(), [200, 422]);
        
        if ($response->status() === 422) {
            $response->assertJsonValidationErrors();
        }
    }
}