<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class PaymentAnalyticsGenerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Feature: vue-frontend-enhancement, Property 53: Payment Analytics Generation
     * 
     * Property: For any payment data in the system, detailed analytics with charts 
     * and reports should be generated correctly.
     * 
     * @test
     */
    public function test_payment_analytics_generation_property()
    {
        // Create authenticated user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        
        // Generate random test data
        $customerCount = rand(5, 20);
        $paymentCount = rand(10, 50);
        
        // Create customers
        $customers = Customer::factory($customerCount)->create();
        
        // Create payment gateway
        $gateway = PaymentGateway::factory()->create([
            'name' => 'Test Gateway',
            'provider' => 'collectug',
            'is_active' => true
        ]);
        
        // Create payments with random data
        $totalAmount = 0;
        $completedCount = 0;
        $pendingCount = 0;
        $failedCount = 0;
        
        for ($i = 0; $i < $paymentCount; $i++) {
            $amount = rand(1000, 100000);
            $status = ['completed', 'pending', 'failed'][rand(0, 2)];
            
            // Create payments within the current month to match the 'month' filter
            $daysInMonth = now()->daysInMonth;
            $randomDay = rand(1, min($daysInMonth, now()->day)); // Don't go beyond today
            
            Payment::factory()->create([
                'customer_id' => $customers->random()->id,
                'amount' => $amount,
                'status' => $status,
                'currency' => 'UGX',
                'created_at' => now()->startOfMonth()->addDays($randomDay - 1)
            ]);
            
            $totalAmount += $amount;
            
            switch ($status) {
                case 'completed':
                    $completedCount++;
                    break;
                case 'pending':
                    $pendingCount++;
                    break;
                case 'failed':
                    $failedCount++;
                    break;
            }
        }
        
        // Test analytics generation
        $response = $this->getJson('/api/v1/payments/statistics?period=month');
        
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
                'average_amount'
            ]
        ]);
        
        $data = $response->json('data');
        
        // Verify analytics accuracy
        $this->assertEquals($paymentCount, $data['total_payments']);
        $this->assertEquals($completedCount, $data['completed_payments']);
        $this->assertEquals($pendingCount, $data['pending_payments']);
        $this->assertEquals($failedCount, $data['failed_payments']);
        
        // Verify calculated fields
        $expectedSuccessRate = $paymentCount > 0 ? round(($completedCount / $paymentCount) * 100, 2) : 0;
        $this->assertEquals($expectedSuccessRate, $data['success_rate']);
        
        // Verify revenue calculation (only completed payments)
        $completedRevenue = Payment::where('status', 'completed')->sum('amount');
        $this->assertEquals($completedRevenue, $data['total_revenue']);
        
        // Verify average amount calculation
        $expectedAverage = $completedCount > 0 ? round($completedRevenue / $completedCount, 2) : 0;
        $this->assertEquals($expectedAverage, $data['average_amount']);
    }

    /**
     * Feature: vue-frontend-enhancement, Property 53: Payment Analytics Generation
     * 
     * Property: For any date range filter, analytics should only include payments 
     * within that specific range.
     * 
     * @test
     */
    public function test_payment_analytics_date_filtering_property()
    {
        // Create authenticated user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        
        // Create payments across different dates
        $oldPayment = Payment::factory()->create([
            'amount' => 10000,
            'status' => 'completed',
            'created_at' => now()->subDays(45) // This should be outside current month
        ]);
        
        $recentPayment = Payment::factory()->create([
            'amount' => 20000,
            'status' => 'completed',
            'created_at' => now()->startOfMonth()->addDays(5) // This should be within current month
        ]);
        
        $todayPayment = Payment::factory()->create([
            'amount' => 30000,
            'status' => 'completed',
            'created_at' => now()
        ]);
        
        // Test month filter (should include recent and today payments)
        $response = $this->getJson('/api/v1/payments/statistics?period=month');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should include payments from this month only
        $this->assertEquals(2, $data['total_payments']);
        $this->assertEquals(50000, $data['total_revenue']); // 20000 + 30000
        
        // Test today filter (should include only today's payment)
        $response = $this->getJson('/api/v1/payments/statistics?period=today');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals(1, $data['total_payments']);
        $this->assertEquals(30000, $data['total_revenue']);
    }

    /**
     * Feature: vue-frontend-enhancement, Property 53: Payment Analytics Generation
     * 
     * Property: For any custom date range, analytics should accurately reflect 
     * only the payments within that range.
     * 
     * @test
     */
    public function test_payment_analytics_custom_date_range_property()
    {
        // Create authenticated user
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
        
        $startDate = now()->subDays(10)->format('Y-m-d');
        $endDate = now()->subDays(5)->format('Y-m-d');
        
        // Create payments before, within, and after the range
        Payment::factory()->create([
            'amount' => 10000,
            'status' => 'completed',
            'created_at' => now()->subDays(15) // Before range
        ]);
        
        Payment::factory()->create([
            'amount' => 20000,
            'status' => 'completed',
            'created_at' => now()->subDays(7) // Within range
        ]);
        
        Payment::factory()->create([
            'amount' => 30000,
            'status' => 'completed',
            'created_at' => now()->subDays(2) // After range
        ]);
        
        // Test custom date range
        $response = $this->getJson("/api/v1/payments/statistics?period=custom&start_date={$startDate}&end_date={$endDate}");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should only include the payment within the range
        $this->assertEquals(1, $data['total_payments']);
        $this->assertEquals(20000, $data['total_revenue']);
    }
}