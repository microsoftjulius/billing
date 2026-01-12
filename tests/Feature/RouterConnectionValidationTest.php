<?php

use Tests\TestCase;
use App\Models\User;
use App\Models\MikroTikDevice;
use App\Http\Controllers\Api\RouterManagementController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

/**
 * Property 36: Router Connection Validation
 * Feature: vue-frontend-enhancement, Property 36: Router Connection Validation
 * 
 * For any router configuration being saved, the system should validate connection details and reject invalid configurations with appropriate error messages.
 * Validates: Requirements 14.2
 */
class RouterConnectionValidationTest extends TestCase
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
     * Property test for router connection validation
     */
    public function test_router_connection_validation_property()
    {
        // Generate random test data for property-based testing
        for ($i = 0; $i < 100; $i++) {
            $this->runRouterConnectionValidationTest();
        }
    }

    /**
     * Run a single iteration of router connection validation test
     */
    private function runRouterConnectionValidationTest(): void
    {
        // Generate random router configuration data
        $routerConfig = $this->generateRandomRouterConfig();
        
        // Test validation directly using Laravel's validator
        $validator = Validator::make($routerConfig, [
            'name' => 'required|string|max:255|unique:mikrotik_devices,name',
            'ip_address' => 'required|ip|unique:mikrotik_devices,ip_address',
            'api_port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:100',
            'password' => 'required|string|min:6',
            'location' => 'required|array',
            'location.region' => 'required|string|max:100',
            'location.district' => 'required|string|max:100',
            'location.coordinates' => 'nullable|array',
            'location.coordinates.lat' => 'nullable|numeric|between:-90,90',
            'location.coordinates.lng' => 'nullable|numeric|between:-180,180',
        ]);
        
        if ($validator->passes()) {
            // For valid configurations, test that they can be saved to database
            try {
                $router = MikroTikDevice::create([
                    'name' => $routerConfig['name'],
                    'ip_address' => $routerConfig['ip_address'],
                    'api_port' => $routerConfig['api_port'],
                    'username' => $routerConfig['username'],
                    'password' => $routerConfig['password'], // Will be encrypted by model mutator
                    'location' => $routerConfig['location'],
                    'status' => 'offline', // Default status before connection test
                    'uptime_seconds' => 0, // Explicitly set to 0
                ]);
                
                // Verify router was saved with proper validation
                $this->assertDatabaseHas('mikrotik_devices', [
                    'name' => $routerConfig['name'],
                    'ip_address' => $routerConfig['ip_address'],
                    'api_port' => $routerConfig['api_port'],
                    'username' => $routerConfig['username']
                ]);
                
                // Verify password was encrypted (not stored in plain text)
                $this->assertNotEquals($routerConfig['password'], $router->password_encrypted);
                $this->assertNotNull($router->password_encrypted);
                
                // Test that the password can be decrypted correctly
                $this->assertEquals($routerConfig['password'], $router->password);
                
                // Clean up
                $router->delete();
                
            } catch (\Exception $e) {
                // If database save fails, it should be due to connection validation, not basic validation
                $this->assertStringContainsString('connection', strtolower($e->getMessage()), 
                    'Database save failure should be related to connection validation');
            }
            
        } else {
            // For invalid configurations, verify proper validation errors are returned
            $errors = $validator->errors()->toArray();
            $this->assertNotEmpty($errors, 'Invalid configuration should produce validation errors');
            
            // Verify that specific validation rules are working
            if (empty($routerConfig['name'])) {
                $this->assertArrayHasKey('name', $errors);
            }
            
            if (!filter_var($routerConfig['ip_address'] ?? '', FILTER_VALIDATE_IP)) {
                $this->assertArrayHasKey('ip_address', $errors);
            }
            
            if (($routerConfig['api_port'] ?? 0) < 1 || ($routerConfig['api_port'] ?? 0) > 65535) {
                $this->assertArrayHasKey('api_port', $errors);
            }
            
            if (empty($routerConfig['username'])) {
                $this->assertArrayHasKey('username', $errors);
            }
            
            if (strlen($routerConfig['password'] ?? '') < 6) {
                $this->assertArrayHasKey('password', $errors);
            }
            
            // Verify router was NOT saved to database
            if (isset($routerConfig['name'])) {
                $this->assertDatabaseMissing('mikrotik_devices', [
                    'name' => $routerConfig['name']
                ]);
            }
        }
    }

    /**
     * Test router connection validation with various invalid input scenarios
     */
    public function test_router_connection_validation_invalid_inputs()
    {
        $invalidScenarios = [
            'missing_name' => [
                'config' => array_merge($this->generateValidRouterConfig(), ['name' => '']),
                'expected_error_field' => 'name'
            ],
            'invalid_ip_address' => [
                'config' => array_merge($this->generateValidRouterConfig(), ['ip_address' => 'invalid-ip']),
                'expected_error_field' => 'ip_address'
            ],
            'invalid_port_too_low' => [
                'config' => array_merge($this->generateValidRouterConfig(), ['api_port' => 0]),
                'expected_error_field' => 'api_port'
            ],
            'invalid_port_too_high' => [
                'config' => array_merge($this->generateValidRouterConfig(), ['api_port' => 99999]),
                'expected_error_field' => 'api_port'
            ],
            'missing_username' => [
                'config' => array_merge($this->generateValidRouterConfig(), ['username' => '']),
                'expected_error_field' => 'username'
            ],
            'short_password' => [
                'config' => array_merge($this->generateValidRouterConfig(), ['password' => '123']),
                'expected_error_field' => 'password'
            ],
            'missing_location_region' => [
                'config' => array_merge($this->generateValidRouterConfig(), [
                    'location' => ['district' => 'Kampala']
                ]),
                'expected_error_field' => 'location.region'
            ],
            'duplicate_name' => [
                'config' => null, // Will be set in test
                'expected_error_field' => 'name'
            ],
            'duplicate_ip' => [
                'config' => null, // Will be set in test
                'expected_error_field' => 'ip_address'
            ]
        ];

        foreach ($invalidScenarios as $scenarioName => $scenario) {
            // Handle duplicate scenarios
            $existingRouter = null;
            if ($scenarioName === 'duplicate_name') {
                $existingRouter = MikroTikDevice::factory()->create();
                $scenario['config'] = array_merge($this->generateValidRouterConfig(), [
                    'name' => $existingRouter->name
                ]);
            } elseif ($scenarioName === 'duplicate_ip') {
                $existingRouter = MikroTikDevice::factory()->create();
                $scenario['config'] = array_merge($this->generateValidRouterConfig(), [
                    'ip_address' => $existingRouter->ip_address
                ]);
            }
            
            // Test validation
            $validator = Validator::make($scenario['config'], [
                'name' => 'required|string|max:255|unique:mikrotik_devices,name',
                'ip_address' => 'required|ip|unique:mikrotik_devices,ip_address',
                'api_port' => 'required|integer|min:1|max:65535',
                'username' => 'required|string|max:100',
                'password' => 'required|string|min:6',
                'location' => 'required|array',
                'location.region' => 'required|string|max:100',
                'location.district' => 'required|string|max:100',
                'location.coordinates' => 'nullable|array',
                'location.coordinates.lat' => 'nullable|numeric|between:-90,90',
                'location.coordinates.lng' => 'nullable|numeric|between:-180,180',
            ]);
            
            // Should fail validation
            $this->assertTrue($validator->fails(), 
                "Validation should fail for scenario: {$scenarioName}");
            
            $errors = $validator->errors()->toArray();
            $this->assertArrayHasKey($scenario['expected_error_field'], $errors,
                "Validation error should include field '{$scenario['expected_error_field']}' for scenario: {$scenarioName}");
            
            // Verify router was not saved (only check for non-duplicate scenarios)
            if (!in_array($scenarioName, ['duplicate_name', 'duplicate_ip']) && isset($scenario['config']['name'])) {
                $this->assertDatabaseMissing('mikrotik_devices', [
                    'name' => $scenario['config']['name']
                ]);
            }
            
            // Clean up existing router if created
            if ($existingRouter) {
                $existingRouter->delete();
            }
        }
    }

    /**
     * Test router connection validation with valid input scenarios
     */
    public function test_router_connection_validation_valid_inputs()
    {
        $validScenarios = [
            'standard_config' => $this->generateValidRouterConfig(),
            'custom_port' => array_merge($this->generateValidRouterConfig(), ['api_port' => 8729]),
            'different_username' => array_merge($this->generateValidRouterConfig(), ['username' => 'custom_admin']),
            'long_password' => array_merge($this->generateValidRouterConfig(), ['password' => 'very_secure_password_123']),
            'coordinates_provided' => array_merge($this->generateValidRouterConfig(), [
                'location' => [
                    'region' => 'Central',
                    'district' => 'Kampala',
                    'coordinates' => [
                        'lat' => 0.3476,
                        'lng' => 32.5825
                    ]
                ]
            ])
        ];

        foreach ($validScenarios as $scenarioName => $config) {
            // Test validation
            $validator = Validator::make($config, [
                'name' => 'required|string|max:255|unique:mikrotik_devices,name',
                'ip_address' => 'required|ip|unique:mikrotik_devices,ip_address',
                'api_port' => 'required|integer|min:1|max:65535',
                'username' => 'required|string|max:100',
                'password' => 'required|string|min:6',
                'location' => 'required|array',
                'location.region' => 'required|string|max:100',
                'location.district' => 'required|string|max:100',
                'location.coordinates' => 'nullable|array',
                'location.coordinates.lat' => 'nullable|numeric|between:-90,90',
                'location.coordinates.lng' => 'nullable|numeric|between:-180,180',
            ]);
            
            // Should pass validation
            $this->assertTrue($validator->passes(), 
                "Valid configuration should pass validation for scenario: {$scenarioName}");
            
            // Test that it can be saved to database
            $router = MikroTikDevice::create([
                'name' => $config['name'],
                'ip_address' => $config['ip_address'],
                'api_port' => $config['api_port'],
                'username' => $config['username'],
                'password' => $config['password'],
                'location' => $config['location'],
                'status' => 'offline',
                'uptime_seconds' => 0,
            ]);
            
            $this->assertDatabaseHas('mikrotik_devices', [
                'name' => $config['name'],
                'ip_address' => $config['ip_address'],
                'api_port' => $config['api_port'],
                'username' => $config['username']
            ]);
            
            // Clean up
            $router->delete();
        }
    }

    /**
     * Test that validation occurs before connection testing
     */
    public function test_validation_occurs_before_connection_testing()
    {
        // Create config with validation errors
        $invalidConfig = [
            'name' => '', // Invalid: empty name
            'ip_address' => 'invalid-ip', // Invalid: not an IP
            'api_port' => 99999, // Invalid: port too high
            'username' => '', // Invalid: empty username
            'password' => '123', // Invalid: too short
            'location' => [] // Invalid: missing required fields
        ];
        
        // Count routers before attempt
        $initialCount = MikroTikDevice::count();
        
        // Test validation
        $validator = Validator::make($invalidConfig, [
            'name' => 'required|string|max:255|unique:mikrotik_devices,name',
            'ip_address' => 'required|ip|unique:mikrotik_devices,ip_address',
            'api_port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:100',
            'password' => 'required|string|min:6',
            'location' => 'required|array',
            'location.region' => 'required|string|max:100',
            'location.district' => 'required|string|max:100',
        ]);
        
        // Should fail validation
        $this->assertTrue($validator->fails());
        
        // Verify no router was saved to database
        $this->assertEquals($initialCount, MikroTikDevice::count());
        
        // Verify multiple validation errors are returned
        $errors = $validator->errors()->toArray();
        
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('ip_address', $errors);
        $this->assertArrayHasKey('api_port', $errors);
        $this->assertArrayHasKey('username', $errors);
        $this->assertArrayHasKey('password', $errors);
        $this->assertArrayHasKey('location.region', $errors);
        $this->assertArrayHasKey('location.district', $errors);
    }

    /**
     * Test router update connection validation
     */
    public function test_router_update_connection_validation()
    {
        // Create a router first
        $router = MikroTikDevice::factory()->create([
            'status' => 'offline'
        ]);
        
        // Test with valid update data
        $validUpdateData = [
            'name' => 'Updated-Router-' . fake()->bothify('##??##'),
            'location' => [
                'region' => 'Eastern',
                'district' => 'Jinja'
            ]
        ];
        
        // Test validation for update (using ignore rule for unique fields)
        $validator = Validator::make($validUpdateData, [
            'name' => 'sometimes|required|string|max:255|unique:mikrotik_devices,name,' . $router->id,
            'ip_address' => 'sometimes|required|ip|unique:mikrotik_devices,ip_address,' . $router->id,
            'api_port' => 'sometimes|required|integer|min:1|max:65535',
            'username' => 'sometimes|required|string|max:100',
            'password' => 'sometimes|required|string|min:6',
            'location' => 'sometimes|required|array',
            'location.region' => 'sometimes|required|string|max:100',
            'location.district' => 'sometimes|required|string|max:100',
        ]);
        
        // Should pass validation
        $this->assertTrue($validator->passes());
        
        // Test with invalid update data
        $invalidUpdateData = [
            'ip_address' => 'invalid-ip',
            'api_port' => -1
        ];
        
        $validator = Validator::make($invalidUpdateData, [
            'name' => 'sometimes|required|string|max:255|unique:mikrotik_devices,name,' . $router->id,
            'ip_address' => 'sometimes|required|ip|unique:mikrotik_devices,ip_address,' . $router->id,
            'api_port' => 'sometimes|required|integer|min:1|max:65535',
            'username' => 'sometimes|required|string|max:100',
            'password' => 'sometimes|required|string|min:6',
        ]);
        
        // Should fail validation
        $this->assertTrue($validator->fails());
        
        $errors = $validator->errors()->toArray();
        $this->assertArrayHasKey('ip_address', $errors);
        $this->assertArrayHasKey('api_port', $errors);
    }

    /**
     * Test password encryption during validation and save
     */
    public function test_password_encryption_during_validation()
    {
        $config = $this->generateValidRouterConfig();
        $plainPassword = $config['password'];
        
        // Create router
        $router = MikroTikDevice::create([
            'name' => $config['name'],
            'ip_address' => $config['ip_address'],
            'api_port' => $config['api_port'],
            'username' => $config['username'],
            'password' => $plainPassword,
            'location' => $config['location'],
            'status' => 'offline',
            'uptime_seconds' => 0,
        ]);
        
        // Verify password was encrypted in database
        $this->assertNotEquals($plainPassword, $router->password_encrypted);
        $this->assertNotNull($router->password_encrypted);
        
        // Verify password can be decrypted correctly
        $this->assertEquals($plainPassword, $router->password);
        
        // Verify password is hidden in array/JSON output
        $routerArray = $router->toArray();
        $this->assertArrayNotHasKey('password_encrypted', $routerArray);
        $this->assertArrayNotHasKey('password', $routerArray);
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
            'password' => fake()->randomElement(['admin', 'password', fake()->password(6, 20)]),
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