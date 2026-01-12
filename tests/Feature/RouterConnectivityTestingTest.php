<?php

use Tests\TestCase;
use App\Models\User;
use App\Models\MikroTikDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Property 38: Router Connectivity Testing
 * Feature: vue-frontend-enhancement, Property 38: Router Connectivity Testing
 * 
 * For any router being added to the system, connectivity testing should be performed and the results should be reported to the user.
 * Validates: Requirements 14.4
 */
class RouterConnectivityTestingTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable tenancy for testing to avoid database issues
        Config::set('tenancy.features', []);
        
        // Create a simple user for authentication
        $this->user = User::factory()->create([
            'role' => 'admin'
        ]);
        
        $this->actingAs($this->user);
    }

    /**
     * Property test for router connectivity testing
     */
    public function test_router_connectivity_testing_property()
    {
        // Generate random test data for property-based testing
        for ($i = 0; $i < 100; $i++) {
            $this->runRouterConnectivityTestingTest();
        }
    }

    /**
     * Run a single iteration of router connectivity testing test
     */
    private function runRouterConnectivityTestingTest(): void
    {
        // Generate random router configuration data
        $routerConfig = $this->generateRandomRouterConfig();
        
        // Mock RouterOS client behavior for testing
        $shouldConnect = fake()->boolean(70); // 70% chance of successful connection
        
        if ($shouldConnect) {
            // Mock successful connection
            $this->mockSuccessfulConnection($routerConfig);
            
            // Test that connectivity testing is performed and router is added
            $response = $this->postJson('/api/v1/router-management', $routerConfig);
            
            // Should succeed with connectivity test
            $this->assertResponseIndicatesConnectivityTesting($response, true);
            
            if ($response->status() === 201) {
                // Verify router was saved with online status (indicating successful connectivity test)
                $this->assertDatabaseHas('mikrotik_devices', [
                    'name' => $routerConfig['name'],
                    'ip_address' => $routerConfig['ip_address'],
                    'status' => 'online' // Should be online after successful connectivity test
                ]);
                
                // Verify last_seen was set (indicating connectivity test was performed)
                $router = MikroTikDevice::where('name', $routerConfig['name'])->first();
                $this->assertNotNull($router->last_seen, 'last_seen should be set after successful connectivity test');
                
                // Clean up
                $router->delete();
            }
            
        } else {
            // Mock failed connection
            $this->mockFailedConnection($routerConfig);
            
            // Test that connectivity testing is performed and failure is reported
            $response = $this->postJson('/api/v1/router-management', $routerConfig);
            
            // Should fail due to connectivity test failure
            $this->assertResponseIndicatesConnectivityTesting($response, false);
            
            // Verify router was NOT saved due to failed connectivity test
            $this->assertDatabaseMissing('mikrotik_devices', [
                'name' => $routerConfig['name']
            ]);
        }
    }

    /**
     * Test that connectivity testing is always performed during router addition
     */
    public function test_connectivity_testing_always_performed_during_add()
    {
        $testScenarios = [
            'successful_connection' => [
                'config' => $this->generateValidRouterConfig(),
                'should_connect' => true,
                'expected_status' => 201,
                'expected_router_status' => 'online'
            ],
            'connection_timeout' => [
                'config' => $this->generateValidRouterConfig(),
                'should_connect' => false,
                'connection_error' => 'Connection timeout',
                'expected_status' => 400
            ],
            'authentication_failure' => [
                'config' => array_merge($this->generateValidRouterConfig(), ['password' => 'wrong_password']),
                'should_connect' => false,
                'connection_error' => 'Authentication failed',
                'expected_status' => 400
            ],
            'network_unreachable' => [
                'config' => array_merge($this->generateValidRouterConfig(), ['ip_address' => '192.168.99.99']),
                'should_connect' => false,
                'connection_error' => 'Network unreachable',
                'expected_status' => 400
            ],
            'invalid_port' => [
                'config' => array_merge($this->generateValidRouterConfig(), ['api_port' => 9999]),
                'should_connect' => false,
                'connection_error' => 'Connection refused',
                'expected_status' => 400
            ]
        ];

        foreach ($testScenarios as $scenarioName => $scenario) {
            if ($scenario['should_connect']) {
                $this->mockSuccessfulConnection($scenario['config']);
            } else {
                $this->mockFailedConnection($scenario['config'], $scenario['connection_error'] ?? 'Connection failed');
            }

            $response = $this->postJson('/api/v1/router-management', $scenario['config']);

            // Verify expected HTTP status
            $this->assertEquals($scenario['expected_status'], $response->status(), 
                "Scenario '{$scenarioName}' should return status {$scenario['expected_status']}");

            // Verify connectivity testing was performed (check response structure)
            $this->assertResponseIndicatesConnectivityTesting($response, $scenario['should_connect']);

            if ($scenario['should_connect']) {
                // Verify router was saved with correct status
                $this->assertDatabaseHas('mikrotik_devices', [
                    'name' => $scenario['config']['name'],
                    'status' => $scenario['expected_router_status']
                ]);

                // Clean up
                MikroTikDevice::where('name', $scenario['config']['name'])->delete();
            } else {
                // Verify router was not saved
                $this->assertDatabaseMissing('mikrotik_devices', [
                    'name' => $scenario['config']['name']
                ]);

                // Verify error message indicates connectivity test failure
                $responseData = $response->json();
                $this->assertStringContainsString('connection', strtolower($responseData['message'] ?? ''),
                    "Error message should indicate connection test failure for scenario: {$scenarioName}");
            }
        }
    }

    /**
     * Test connectivity testing with various router configurations
     */
    public function test_connectivity_testing_with_different_configurations()
    {
        $configurations = [
            'standard_mikrotik' => [
                'api_port' => 8728,
                'username' => 'admin',
                'should_connect' => true
            ],
            'custom_port' => [
                'api_port' => 8729,
                'username' => 'admin',
                'should_connect' => true
            ],
            'different_username' => [
                'api_port' => 8728,
                'username' => 'custom_admin',
                'should_connect' => true
            ],
            'secure_setup' => [
                'api_port' => 8729,
                'username' => 'secure_user',
                'should_connect' => true
            ]
        ];

        foreach ($configurations as $configName => $config) {
            $routerConfig = array_merge($this->generateValidRouterConfig(), [
                'name' => "Test-Router-{$configName}-" . fake()->bothify('##??##'),
                'api_port' => $config['api_port'],
                'username' => $config['username']
            ]);

            if ($config['should_connect']) {
                $this->mockSuccessfulConnection($routerConfig);
            } else {
                $this->mockFailedConnection($routerConfig);
            }

            $response = $this->postJson('/api/v1/router-management', $routerConfig);

            // Verify connectivity testing was performed
            $this->assertResponseIndicatesConnectivityTesting($response, $config['should_connect']);

            if ($config['should_connect']) {
                $this->assertEquals(201, $response->status(), 
                    "Configuration '{$configName}' should succeed");

                // Verify router status indicates successful connectivity test
                $this->assertDatabaseHas('mikrotik_devices', [
                    'name' => $routerConfig['name'],
                    'status' => 'online'
                ]);

                // Clean up
                MikroTikDevice::where('name', $routerConfig['name'])->delete();
            }
        }
    }

    /**
     * Test that connectivity testing results are properly reported to user
     */
    public function test_connectivity_testing_results_reported_to_user()
    {
        // Test successful connectivity
        $successConfig = $this->generateValidRouterConfig();
        $this->mockSuccessfulConnection($successConfig, [
            'identity' => 'Test-MikroTik-Router',
            'version' => '7.1.5',
            'response_time' => 'Fast'
        ]);

        $response = $this->postJson('/api/v1/router-management', $successConfig);

        $this->assertEquals(201, $response->status());
        $responseData = $response->json();
        
        // Verify success message indicates connectivity test was performed
        $this->assertStringContainsString('successfully', strtolower($responseData['message']));
        $this->assertTrue($responseData['success']);

        // Clean up
        MikroTikDevice::where('name', $successConfig['name'])->delete();

        // Test failed connectivity with specific error
        $failConfig = $this->generateValidRouterConfig();
        $errorMessage = 'Connection timeout after 10 seconds';
        $this->mockFailedConnection($failConfig, $errorMessage);

        $response = $this->postJson('/api/v1/router-management', $failConfig);

        $this->assertEquals(400, $response->status());
        $responseData = $response->json();
        
        // Verify error message indicates connectivity test failure and includes specific error
        $this->assertStringContainsString('connection test failed', strtolower($responseData['message']));
        $this->assertStringContainsString(strtolower($errorMessage), strtolower($responseData['error']));
        $this->assertFalse($responseData['success']);
    }

    /**
     * Test standalone connectivity testing endpoint
     */
    public function test_standalone_connectivity_testing_endpoint()
    {
        $testData = [
            'ip_address' => '192.168.1.1',
            'api_port' => 8728,
            'username' => 'admin',
            'password' => 'testpassword'
        ];

        // Test successful connection
        $this->mockSuccessfulConnection($testData, [
            'identity' => 'Test-Router',
            'version' => '7.1.5'
        ]);

        $response = $this->postJson('/api/v1/router-management/test-connection', $testData);

        $this->assertEquals(200, $response->status());
        $responseData = $response->json();
        
        $this->assertTrue($responseData['success']);
        $this->assertStringContainsString('successful', strtolower($responseData['message']));
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('identity', $responseData['data']);

        // Test failed connection
        $this->mockFailedConnection($testData, 'Authentication failed');

        $response = $this->postJson('/api/v1/router-management/test-connection', $testData);

        // The controller returns 200 with success: false for connection test failures
        $this->assertEquals(200, $response->status());
        $responseData = $response->json();
        
        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('authentication failed', strtolower($responseData['error']));
    }

    /**
     * Test that connectivity testing validates input parameters
     */
    public function test_connectivity_testing_validates_input_parameters()
    {
        $invalidInputs = [
            'missing_ip' => [
                'api_port' => 8728,
                'username' => 'admin',
                'password' => 'password'
            ],
            'invalid_ip' => [
                'ip_address' => 'invalid-ip',
                'api_port' => 8728,
                'username' => 'admin',
                'password' => 'password'
            ],
            'missing_port' => [
                'ip_address' => '192.168.1.1',
                'username' => 'admin',
                'password' => 'password'
            ],
            'invalid_port' => [
                'ip_address' => '192.168.1.1',
                'api_port' => 99999,
                'username' => 'admin',
                'password' => 'password'
            ],
            'missing_username' => [
                'ip_address' => '192.168.1.1',
                'api_port' => 8728,
                'password' => 'password'
            ],
            'missing_password' => [
                'ip_address' => '192.168.1.1',
                'api_port' => 8728,
                'username' => 'admin'
            ]
        ];

        foreach ($invalidInputs as $scenarioName => $input) {
            $response = $this->postJson('/api/v1/router-management/test-connection', $input);

            $this->assertEquals(422, $response->status(), 
                "Scenario '{$scenarioName}' should return validation error");

            $responseData = $response->json();
            $this->assertFalse($responseData['success']);
            $this->assertArrayHasKey('errors', $responseData);
        }
    }

    /**
     * Mock successful RouterOS connection
     */
    private function mockSuccessfulConnection(array $config, array $responseData = null): void
    {
        // In a real implementation, we would mock the RouterOS client
        // For this test, we'll assume the controller handles the mocking
        // or we would use a service that can be mocked
        
        $defaultResponse = [
            'identity' => 'MikroTik-' . fake()->bothify('##??##'),
            'version' => fake()->randomElement(['7.1.5', '7.2.1', '6.49.7']),
            'response_time' => fake()->randomElement(['Fast', 'Normal', 'Slow'])
        ];
        
        $mockData = array_merge($defaultResponse, $responseData ?? []);
        
        // Store mock data for the controller to use
        cache()->put("mock_connection_{$config['ip_address']}_{$config['api_port']}", [
            'success' => true,
            'data' => $mockData
        ], 60);
    }

    /**
     * Mock failed RouterOS connection
     */
    private function mockFailedConnection(array $config, string $errorMessage = 'Connection failed'): void
    {
        // Store mock failure data for the controller to use
        cache()->put("mock_connection_{$config['ip_address']}_{$config['api_port']}", [
            'success' => false,
            'error' => $errorMessage
        ], 60);
    }

    /**
     * Assert that response indicates connectivity testing was performed
     */
    private function assertResponseIndicatesConnectivityTesting($response, bool $shouldSucceed): void
    {
        $responseData = $response->json();
        
        if ($shouldSucceed) {
            // Successful connectivity test should result in successful router creation
            $this->assertTrue($responseData['success'] ?? false, 
                'Response should indicate success when connectivity test passes');
            
            if (isset($responseData['message'])) {
                $this->assertStringContainsString('successfully', strtolower($responseData['message']),
                    'Success message should indicate successful operation');
            }
        } else {
            // Failed connectivity test should result in error response
            $this->assertFalse($responseData['success'] ?? true, 
                'Response should indicate failure when connectivity test fails');
            
            if (isset($responseData['message'])) {
                $this->assertStringContainsString('connection', strtolower($responseData['message']),
                    'Error message should mention connection test failure');
            }
        }
        
        // Response should always have a success field (indicating test was performed)
        $this->assertArrayHasKey('success', $responseData, 
            'Response should always include success field indicating connectivity test was performed');
    }

    /**
     * Generate random router configuration data
     */
    private function generateRandomRouterConfig(): array
    {
        return [
            'name' => 'Router-' . fake()->bothify('##??##'),
            'ip_address' => fake()->ipv4(),
            'api_port' => fake()->randomElement([8728, 8729, 8730, fake()->numberBetween(1024, 65535)]),
            'username' => fake()->randomElement(['admin', 'user', fake()->userName()]),
            'password' => fake()->randomElement(['adminpass', 'password123', fake()->password(8, 20)]), // Ensure minimum 6 chars
            'location' => [
                'region' => fake()->randomElement(['Central', 'Eastern', 'Western', 'Northern']),
                'district' => fake()->city(),
                'coordinates' => fake()->optional()->passthrough([
                    'lat' => fake()->latitude(),
                    'lng' => fake()->longitude()
                ])
            ]
        ];
    }

    /**
     * Generate a valid router configuration
     */
    private function generateValidRouterConfig(): array
    {
        return [
            'name' => 'Valid-Router-' . fake()->bothify('##??##'),
            'ip_address' => fake()->ipv4(),
            'api_port' => 8728,
            'username' => 'admin',
            'password' => 'validpassword123',
            'location' => [
                'region' => 'Central',
                'district' => 'Kampala'
            ]
        ];
    }
}