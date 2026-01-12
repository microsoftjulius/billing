<?php

namespace Tests\Feature;

use App\Models\MikroTikDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class RouterManagementIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_it_can_add_router_through_modal_interface_and_sync_to_database()
    {
        $routerData = [
            'name' => 'Test Router',
            'ip_address' => '192.168.1.1',
            'api_port' => 8728,
            'username' => 'admin',
            'password' => 'password123',
            'location' => [
                'region' => 'Central',
                'district' => 'Kampala',
                'coordinates' => ['lat' => 0.3476, 'lng' => 32.5825]
            ]
        ];

        // Test router addition through API
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/router-management', $routerData);

        $response->assertStatus(201);
        
        // Verify router was created in database
        $this->assertDatabaseHas('mikrotik_devices', [
            'name' => 'Test Router',
            'ip_address' => '192.168.1.1',
            'api_port' => 8728,
            'username' => 'admin'
        ]);

        // Verify password is encrypted
        $router = MikroTikDevice::where('name', 'Test Router')->first();
        $this->assertNotNull($router);
        $this->assertNotEquals('password123', $router->password_encrypted);
    }

    public function test_it_can_edit_router_configuration_and_update_database()
    {
        $router = MikroTikDevice::factory()->create([
            'name' => 'Original Router',
            'ip_address' => '192.168.1.1'
        ]);

        $updateData = [
            'name' => 'Updated Router',
            'ip_address' => '192.168.1.2',
            'location' => [
                'region' => 'Western',
                'district' => 'Mbarara'
            ]
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/router-management/{$router->id}", $updateData);

        $response->assertStatus(200);
        
        // Verify database was updated
        $this->assertDatabaseHas('mikrotik_devices', [
            'id' => $router->id,
            'name' => 'Updated Router',
            'ip_address' => '192.168.1.2'
        ]);
    }

    public function test_it_can_delete_router_and_remove_from_database()
    {
        $router = MikroTikDevice::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/router-management/{$router->id}");

        $response->assertStatus(204);
        
        // Verify router was soft deleted or removed
        $this->assertDatabaseMissing('mikrotik_devices', [
            'id' => $router->id
        ]);
    }

    public function test_it_displays_router_status_and_configuration_in_management_interface()
    {
        $router = MikroTikDevice::factory()->create([
            'status' => 'online',
            'configuration' => [
                'interfaces' => ['ether1', 'ether2'],
                'users' => ['admin', 'user1']
            ]
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/router-management');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $router->id,
            'name' => $router->name,
            'status' => 'online'
        ]);
    }

    public function test_it_tests_router_connectivity_during_configuration()
    {
        $routerData = [
            'name' => 'Test Connectivity Router',
            'ip_address' => '192.168.1.100',
            'api_port' => 8728,
            'username' => 'admin',
            'password' => 'password123',
            'location' => ['region' => 'Central']
        ];

        // Test connectivity endpoint
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/router-management/test-connection', $routerData);

        // Should return connection status
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message'
        ]);
    }

    public function test_it_synchronizes_router_data_between_modal_and_database()
    {
        // Create router through modal interface
        $routerData = [
            'name' => 'Modal Test Router',
            'ip_address' => '192.168.1.50',
            'api_port' => 8728,
            'username' => 'admin',
            'password' => 'secure123',
            'location' => [
                'region' => 'Northern',
                'district' => 'Gulu'
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/router-management', $routerData);

        $response->assertStatus(201);
        $routerId = $response->json('data.id');

        // Fetch router data for modal display
        $getResponse = $this->actingAs($this->user)
            ->getJson("/api/v1/router-management/{$routerId}");

        $getResponse->assertStatus(200);
        $getResponse->assertJsonFragment([
            'name' => 'Modal Test Router',
            'ip_address' => '192.168.1.50'
        ]);

        // Verify location data is properly structured
        $routerData = $getResponse->json('data');
        $this->assertArrayHasKey('location', $routerData);
        $this->assertEquals('Northern', $routerData['location']['region']);
    }
}