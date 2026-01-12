<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\MikroTikDevice;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Property 39: Router CRUD Operations
 * Feature: vue-frontend-enhancement, Property 39: Router CRUD Operations
 * 
 * For any router in the system, editing and deletion operations should work correctly and update the database appropriately.
 * **Validates: Requirements 14.5**
 */
class RouterCrudOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable tenancy for testing to avoid database issues
        Config::set('tenancy.features', []);
        
        $this->user = User::factory()->create([
            'role' => 'admin'
        ]);
        $this->actingAs($this->user, 'sanctum');
    }

    /**
     * Property test for router CRUD operations
     * **Validates: Requirements 14.5**
     */
    public function test_router_crud_operations_property()
    {
        // Generate random test data for property-based testing
        for ($i = 0; $i < 100; $i++) {
            $this->runRouterCrudOperationsTest();
        }
    }

    /**
     * Run a single iteration of router CRUD operations test
     */
    private function runRouterCrudOperationsTest(): void
    {
        // Generate random router configuration
        $routerConfig = $this->generateRandomRouterConfig();
        
        // Mock successful connection for testing
        $this->mockSuccessfulConnection($routerConfig['ip_address'], $routerConfig['api_port']);
        
        // Test CREATE operation
        $createResponse = $this->postJson('/api/v1/router-management', $routerConfig);
        
        $createResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Router created successfully'
            ]);
        
        $routerId = $createResponse->json('data.id');
        $this->assertNotNull($routerId, 'Router ID should be returned after creation');
        
        // Verify router exists in database
        $this->assertDatabaseHas('mikrotik_devices', [
            'id' => $routerId,
            'name' => $routerConfig['name'],
            'ip_address' => $routerConfig['ip_address'],
            'api_port' => $routerConfig['api_port'],
            'username' => $routerConfig['username']
        ]);
        
        // Test READ operation (show specific router)
        $readResponse = $this->getJson("/api/v1/router-management/{$routerId}");
        
        $readResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $routerId,
                    'name' => $routerConfig['name'],
                    'ip_address' => $routerConfig['ip_address'],
                    'api_port' => $routerConfig['api_port'],
                    'username' => $routerConfig['username']
                ]
            ]);
        
        // Test READ operation (list routers)
        $listResponse = $this->getJson('/api/v1/router-management');
        
        $listResponse->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
        
        $routers = $listResponse->json('data');
        $this->assertIsArray($routers, 'Router list should be an array');
        
        // Find our router in the list
        $foundRouter = collect($routers)->firstWhere('id', $routerId);
        $this->assertNotNull($foundRouter, 'Created router should appear in router list');
        
        // Test UPDATE operation
        $updateConfig = $this->generateRandomRouterUpdateConfig();
        
        // Mock successful connection for update
        $this->mockSuccessfulConnection(
            $updateConfig['ip_address'] ?? $routerConfig['ip_address'], 
            $updateConfig['api_port'] ?? $routerConfig['api_port']
        );
        
        $updateResponse = $this->putJson("/api/v1/router-management/{$routerId}", $updateConfig);
        
        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Router updated successfully'
            ]);
        
        // Verify database was updated
        $expectedData = array_merge(
            [
                'id' => $routerId,
                'name' => $routerConfig['name'],
                'ip_address' => $routerConfig['ip_address'],
                'api_port' => $routerConfig['api_port'],
                'username' => $routerConfig['username']
            ],
            array_intersect_key($updateConfig, array_flip(['name', 'ip_address', 'api_port', 'username']))
        );
        
        $this->assertDatabaseHas('mikrotik_devices', $expectedData);
        
        // Test DELETE operation (should fail if router has vouchers)
        $shouldHaveVouchers = fake()->boolean(30); // 30% chance of having vouchers
        
        if ($shouldHaveVouchers) {
            // Create vouchers associated with this router
            $voucherCount = fake()->numberBetween(1, 5);
            Voucher::factory()->count($voucherCount)->create([
                'mikrotik_device_id' => $routerId
            ]);
            
            // Attempt to delete router with vouchers (should fail)
            $deleteResponse = $this->deleteJson("/api/v1/router-management/{$routerId}");
            
            $deleteResponse->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'error' => 'Router has dependencies'
                ]);
            
            // Verify router still exists
            $this->assertDatabaseHas('mikrotik_devices', ['id' => $routerId]);
            
            // Clean up vouchers for successful deletion
            Voucher::where('mikrotik_device_id', $routerId)->delete();
        }
        
        // Test successful DELETE operation
        $deleteResponse = $this->deleteJson("/api/v1/router-management/{$routerId}");
        
        $deleteResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Router deleted successfully'
            ]);
        
        // Verify router was deleted from database
        $this->assertDatabaseMissing('mikrotik_devices', ['id' => $routerId]);
        
        // Test READ operation on deleted router (should fail)
        $readDeletedResponse = $this->getJson("/api/v1/router-management/{$routerId}");
        
        $readDeletedResponse->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Router not found'
            ]);
    }

    /**
     * Generate random router configuration for testing
     */
    private function generateRandomRouterConfig(): array
    {
        return [
            'name' => 'Test-Router-' . fake()->bothify('##??##'),
            'ip_address' => fake()->ipv4(),
            'api_port' => fake()->randomElement([8728, 8729, 8730]),
            'username' => fake()->randomElement(['admin', 'user', fake()->userName()]),
            'password' => fake()->password(8, 20),
            'location' => [
                'region' => fake()->randomElement(['Central', 'Eastern', 'Western', 'Northern']),
                'district' => fake()->city(),
                'coordinates' => [
                    'lat' => fake()->latitude(),
                    'lng' => fake()->longitude()
                ]
            ]
        ];
    }

    /**
     * Generate random router update configuration for testing
     */
    private function generateRandomRouterUpdateConfig(): array
    {
        $fields = ['name', 'ip_address', 'api_port', 'username', 'password', 'location'];
        $fieldsToUpdate = fake()->randomElements($fields, fake()->numberBetween(1, 3));
        
        $updateConfig = [];
        
        foreach ($fieldsToUpdate as $field) {
            switch ($field) {
                case 'name':
                    $updateConfig['name'] = 'Updated-Router-' . fake()->bothify('##??##');
                    break;
                case 'ip_address':
                    $updateConfig['ip_address'] = fake()->ipv4();
                    break;
                case 'api_port':
                    $updateConfig['api_port'] = fake()->randomElement([8728, 8729, 8730]);
                    break;
                case 'username':
                    $updateConfig['username'] = fake()->randomElement(['admin', 'user', fake()->userName()]);
                    break;
                case 'password':
                    $updateConfig['password'] = fake()->password(8, 20);
                    break;
                case 'location':
                    $updateConfig['location'] = [
                        'region' => fake()->randomElement(['Central', 'Eastern', 'Western', 'Northern']),
                        'district' => fake()->city(),
                        'coordinates' => [
                            'lat' => fake()->latitude(),
                            'lng' => fake()->longitude()
                        ]
                    ];
                    break;
            }
        }
        
        return $updateConfig;
    }

    /**
     * Mock successful router connection for testing
     */
    private function mockSuccessfulConnection(string $ipAddress, int $port): void
    {
        $mockKey = "mock_connection_{$ipAddress}_{$port}";
        Cache::put($mockKey, [
            'success' => true,
            'data' => [
                'identity' => 'Test-MikroTik-' . fake()->bothify('##??##'),
                'response_time' => 'Fast',
                'version' => '7.1.5'
            ]
        ], 60);
    }

    /**
     * Test edge case: Invalid router ID for read operation
     */
    public function test_read_invalid_router_id()
    {
        $invalidId = fake()->uuid();
        
        $response = $this->getJson("/api/v1/router-management/{$invalidId}");
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Router not found'
            ]);
    }

    /**
     * Test edge case: Invalid router ID for update operation
     */
    public function test_update_invalid_router_id()
    {
        $invalidId = fake()->uuid();
        $updateConfig = $this->generateRandomRouterUpdateConfig();
        
        $response = $this->putJson("/api/v1/router-management/{$invalidId}", $updateConfig);
        
        $response->assertStatus(404);
    }

    /**
     * Test edge case: Invalid router ID for delete operation
     */
    public function test_delete_invalid_router_id()
    {
        $invalidId = fake()->uuid();
        
        $response = $this->deleteJson("/api/v1/router-management/{$invalidId}");
        
        $response->assertStatus(404);
    }

    /**
     * Test edge case: Duplicate name validation during update
     */
    public function test_update_duplicate_name_validation()
    {
        // Create two routers
        $router1 = MikroTikDevice::factory()->create();
        $router2 = MikroTikDevice::factory()->create();
        
        // Try to update router2 with router1's name
        $response = $this->putJson("/api/v1/router-management/{$router2->id}", [
            'name' => $router1->name
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    /**
     * Test edge case: Duplicate IP validation during update
     */
    public function test_update_duplicate_ip_validation()
    {
        // Create two routers
        $router1 = MikroTikDevice::factory()->create();
        $router2 = MikroTikDevice::factory()->create();
        
        // Try to update router2 with router1's IP
        $response = $this->putJson("/api/v1/router-management/{$router2->id}", [
            'ip_address' => $router1->ip_address
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ip_address']);
    }
}