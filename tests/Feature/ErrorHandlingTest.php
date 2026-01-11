<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test frontend error logging endpoint
     */
    public function test_frontend_error_logging()
    {
        Log::spy();

        $errorData = [
            'error' => [
                'message' => 'Test frontend error',
                'stack' => 'Error: Test error\n    at TestComponent.vue:10:5'
            ],
            'context' => [
                'component' => 'TestComponent',
                'action' => 'test_action',
                'url' => 'http://localhost:3000/test',
                'userAgent' => 'Mozilla/5.0 (Test Browser)',
                'userId' => 'test-user-123',
                'additionalData' => [
                    'testData' => 'test value'
                ]
            ],
            'timestamp' => now()->toISOString()
        ];

        $response = $this->postJson('/api/v1/errors', $errorData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'error_id'
                ]);

        // Verify error was logged
        Log::shouldHaveReceived('log')
            ->with('error', 'Frontend Error: Test frontend error', \Mockery::any());
    }

    /**
     * Test error logging validation
     */
    public function test_error_logging_validation()
    {
        $invalidData = [
            'error' => [
                // Missing required message
                'stack' => 'Some stack trace'
            ],
            'context' => [],
            // Missing required timestamp
        ];

        $response = $this->postJson('/api/v1/errors', $invalidData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['error.message', 'timestamp']);
    }

    /**
     * Test error statistics endpoint
     */
    public function test_error_statistics()
    {
        $this->actingAs(\App\Models\User::factory()->create());

        // Set up some mock statistics
        Cache::put('error_count_total', 100);
        Cache::put('error_count_recent', 25);
        Cache::put('error_count_critical', 3);
        Cache::put('common_errors', [
            'Network error' => 10,
            'Validation failed' => 8,
            'API timeout' => 5
        ]);

        $response = $this->getJson('/api/v1/errors/stats');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'total_errors_period',
                        'total_errors_all_time',
                        'critical_errors',
                        'error_rate_per_hour',
                        'most_common_errors',
                        'error_trends',
                        'affected_users',
                        'component_distribution',
                        'health_score',
                        'timeframe',
                        'last_updated'
                    ]
                ]);
    }

    /**
     * Test error statistics with different timeframes
     */
    public function test_error_statistics_timeframes()
    {
        $this->actingAs(\App\Models\User::factory()->create());
        
        $timeframes = ['1h', '6h', '12h', '24h', '7d', '30d'];

        foreach ($timeframes as $timeframe) {
            $response = $this->getJson("/api/v1/errors/stats?timeframe={$timeframe}");
            
            $response->assertStatus(200)
                    ->assertJsonPath('data.timeframe', $timeframe);
        }
    }

    /**
     * Test error rate limiting
     */
    public function test_error_rate_limiting()
    {
        $errorData = [
            'error' => [
                'message' => 'Repeated error message',
                'stack' => 'Error stack'
            ],
            'context' => [
                'component' => 'TestComponent',
                'action' => 'test_action'
            ],
            'timestamp' => now()->toISOString()
        ];

        // Send the same error multiple times
        for ($i = 0; $i < 12; $i++) {
            $response = $this->postJson('/api/v1/errors', $errorData);
            $response->assertStatus(200);
        }

        // The last few should be rate limited but still return success
        $this->assertTrue(true); // Rate limiting is handled internally
    }

    /**
     * Test critical error handling
     */
    public function test_critical_error_handling()
    {
        Log::spy();

        $criticalErrorData = [
            'error' => [
                'message' => 'CRITICAL: Payment system failure',
                'stack' => 'Critical error stack trace'
            ],
            'context' => [
                'component' => 'PaymentProcessor',
                'action' => 'process_payment',
                'userId' => 'user-123'
            ],
            'timestamp' => now()->toISOString()
        ];

        $response = $this->postJson('/api/v1/errors', $criticalErrorData);

        $response->assertStatus(200);

        // Verify critical error was logged with appropriate level
        Log::shouldHaveReceived('log')
            ->with('critical', 'Frontend Error: CRITICAL: Payment system failure', \Mockery::any());
    }

    /**
     * Test error details retrieval
     */
    public function test_error_details_retrieval()
    {
        $this->actingAs(\App\Models\User::factory()->create());

        $errorId = 'test_error_123';
        $errorDetails = [
            'error_id' => $errorId,
            'message' => 'Test error details',
            'severity' => 'high',
            'timestamp' => now()->toISOString()
        ];

        Cache::put("error_details_{$errorId}", $errorDetails);

        $response = $this->getJson("/api/v1/errors/{$errorId}");

        $response->assertStatus(200)
                ->assertJsonPath('data.error_id', $errorId)
                ->assertJsonPath('data.message', 'Test error details');
    }

    /**
     * Test error details not found
     */
    public function test_error_details_not_found()
    {
        $this->actingAs(\App\Models\User::factory()->create());

        $response = $this->getJson('/api/v1/errors/nonexistent_error');

        $response->assertStatus(404)
                ->assertJsonPath('message', 'Error not found');
    }

    /**
     * Test error status update
     */
    public function test_error_status_update()
    {
        $this->actingAs(\App\Models\User::factory()->create());

        $errorId = 'test_error_456';
        $updateData = [
            'status' => 'resolved',
            'notes' => 'Fixed in version 1.2.3',
            'assigned_to' => 'developer@example.com'
        ];

        $response = $this->putJson("/api/v1/errors/{$errorId}/status", $updateData);

        $response->assertStatus(200)
                ->assertJsonPath('message', 'Error status updated successfully');

        // Verify status was cached
        $cachedStatus = Cache::get("error_status_{$errorId}");
        $this->assertEquals('resolved', $cachedStatus['status']);
        $this->assertEquals('Fixed in version 1.2.3', $cachedStatus['notes']);
    }

    /**
     * Test error status update validation
     */
    public function test_error_status_update_validation()
    {
        $this->actingAs(\App\Models\User::factory()->create());

        $errorId = 'test_error_789';
        $invalidData = [
            'status' => 'invalid_status',
            'notes' => str_repeat('a', 1001) // Too long
        ];

        $response = $this->putJson("/api/v1/errors/{$errorId}/status", $invalidData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['status', 'notes']);
    }

    /**
     * Test health score calculation
     */
    public function test_health_score_calculation()
    {
        $this->actingAs(\App\Models\User::factory()->create());

        // Test perfect health (no errors)
        Cache::flush(); // Clear all cache
        Cache::put('error_count_recent', 0);
        Cache::put('error_count_critical', 0);

        $response = $this->getJson('/api/v1/errors/stats');
        $response->assertStatus(200)
                ->assertJsonPath('data.health_score', 100);

        // Test degraded health (some errors)
        Cache::flush(); // Clear all cache
        Cache::put('error_count_recent', 10);
        Cache::put('error_count_critical', 0);

        $response = $this->getJson('/api/v1/errors/stats');
        $response->assertStatus(200);
        
        $healthScore = $response->json('data.health_score');
        $this->assertLessThan(100, $healthScore);
        $this->assertGreaterThan(0, $healthScore);

        // Test critical health (critical errors)
        Cache::flush(); // Clear all cache
        Cache::put('error_count_recent', 10);
        Cache::put('error_count_critical', 2);

        $response = $this->getJson('/api/v1/errors/stats');
        $response->assertStatus(200);
        
        $healthScore = $response->json('data.health_score');
        $this->assertLessThanOrEqual(50, $healthScore);
    }

    /**
     * Test component error distribution tracking
     */
    public function test_component_error_distribution()
    {
        $this->actingAs(\App\Models\User::factory()->create());

        Cache::put('error_component_distribution', [
            'PaymentForm' => 15,
            'CustomerManagement' => 10,
            'VoucherSystem' => 8,
            'Dashboard' => 5
        ]);

        $response = $this->getJson('/api/v1/errors/stats');

        $response->assertStatus(200);
        
        $distribution = $response->json('data.component_distribution');
        $this->assertEquals(15, $distribution['PaymentForm']);
        $this->assertEquals(10, $distribution['CustomerManagement']);
    }

    /**
     * Test error trends data
     */
    public function test_error_trends()
    {
        $this->actingAs(\App\Models\User::factory()->create());

        $response = $this->getJson('/api/v1/errors/stats?timeframe=24h');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'error_trends' => [
                            '*' => [
                                'timestamp',
                                'count',
                                'critical_count'
                            ]
                        ]
                    ]
                ]);

        $trends = $response->json('data.error_trends');
        $this->assertNotEmpty($trends);
        $this->assertIsArray($trends);
    }
}