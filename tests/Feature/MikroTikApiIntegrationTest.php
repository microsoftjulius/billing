<?php

namespace Tests\Feature;

use App\Models\MikroTikDevice;
use App\Models\User;
use App\Services\MikroTikApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MikroTikApiIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_it_can_communicate_with_mikrotik_api()
    {
        $device = MikroTikDevice::factory()->create([
            'ip_address' => '192.168.1.1',
            'api_port' => 8728,
            'username' => 'admin',
            'status' => 'online'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/router/{$device->id}/statistics");

        // Should return statistics even if connection fails (graceful handling)
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'device_id',
            'status',
            'statistics'
        ]);
    }

    public function test_it_handles_api_connection_errors_gracefully()
    {
        $device = MikroTikDevice::factory()->create([
            'ip_address' => '192.168.1.254', // Unreachable IP
            'api_port' => 8728,
            'username' => 'admin',
            'status' => 'offline'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/router/{$device->id}/statistics");

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'status' => 'offline'
        ]);
    }

    public function test_it_can_monitor_device_status_in_real_time()
    {
        $device = MikroTikDevice::factory()->create([
            'status' => 'online'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/router/{$device->id}/monitor");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'device_id',
            'status',
            'uptime',
            'last_seen',
            'real_time_data'
        ]);
    }

    public function test_it_can_test_connectivity_to_devices()
    {
        $device = MikroTikDevice::factory()->create();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/router/{$device->id}/test-connectivity");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'connection_time',
            'device_info'
        ]);
    }

    public function test_it_can_manage_device_interfaces()
    {
        $device = MikroTikDevice::factory()->create([
            'configuration' => [
                'interfaces' => [
                    ['name' => 'ether1', 'status' => 'enabled'],
                    ['name' => 'ether2', 'status' => 'disabled']
                ]
            ]
        ]);

        // Get interfaces
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/router/{$device->id}/interfaces");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'interfaces' => [
                '*' => [
                    'name',
                    'status',
                    'type'
                ]
            ]
        ]);

        // Update interface
        $updateResponse = $this->actingAs($this->user)
            ->putJson("/api/v1/router/{$device->id}/interfaces/ether1", [
                'status' => 'disabled',
                'comment' => 'Disabled for maintenance'
            ]);

        $updateResponse->assertStatus(200);
    }

    public function test_it_can_manage_device_users()
    {
        $device = MikroTikDevice::factory()->create();

        // Get users
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/router/{$device->id}/users");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'users' => [
                '*' => [
                    'name',
                    'profile',
                    'active'
                ]
            ]
        ]);

        // Add user
        $addUserResponse = $this->actingAs($this->user)
            ->postJson("/api/v1/router/{$device->id}/users", [
                'name' => 'testuser',
                'password' => 'password123',
                'profile' => 'default'
            ]);

        $addUserResponse->assertStatus(201);
    }

    public function test_it_can_retrieve_device_logs()
    {
        $device = MikroTikDevice::factory()->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/router/{$device->id}/logs");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'logs' => [
                '*' => [
                    'time',
                    'topics',
                    'message'
                ]
            ]
        ]);
    }

    public function test_it_can_create_and_restore_backups()
    {
        $device = MikroTikDevice::factory()->create();

        // Create backup
        $backupResponse = $this->actingAs($this->user)
            ->postJson("/api/v1/router/{$device->id}/backup", [
                'name' => 'test-backup'
            ]);

        $backupResponse->assertStatus(201);
        $backupId = $backupResponse->json('backup_id');

        // Get backups list
        $listResponse = $this->actingAs($this->user)
            ->getJson("/api/v1/router/{$device->id}/backups");

        $listResponse->assertStatus(200);
        $listResponse->assertJsonStructure([
            'backups' => [
                '*' => [
                    'id',
                    'name',
                    'created_at',
                    'size'
                ]
            ]
        ]);

        // Restore backup
        $restoreResponse = $this->actingAs($this->user)
            ->postJson("/api/v1/router/{$device->id}/backups/{$backupId}/restore");

        $restoreResponse->assertStatus(200);
    }

    public function test_it_handles_api_rate_limiting()
    {
        $device = MikroTikDevice::factory()->create();

        // Make multiple rapid requests to test rate limiting
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->actingAs($this->user)
                ->getJson("/api/v1/router/{$device->id}/statistics");
        }

        // All requests should be handled gracefully
        foreach ($responses as $response) {
            $this->assertContains($response->status(), [200, 429]); // OK or Too Many Requests
        }
    }

    public function test_it_caches_api_responses_for_performance()
    {
        $device = MikroTikDevice::factory()->create();

        // First request
        $response1 = $this->actingAs($this->user)
            ->getJson("/api/v1/router/{$device->id}/statistics");

        $response1->assertStatus(200);

        // Second request should be faster (cached)
        $response2 = $this->actingAs($this->user)
            ->getJson("/api/v1/router/{$device->id}/statistics");

        $response2->assertStatus(200);
        
        // Verify cache headers or response time improvement
        $this->assertEquals($response1->json(), $response2->json());
    }

    public function test_it_clears_cache_when_requested()
    {
        $device = MikroTikDevice::factory()->create();

        // Make initial request to populate cache
        $this->actingAs($this->user)
            ->getJson("/api/v1/router/{$device->id}/statistics");

        // Clear cache
        $clearResponse = $this->actingAs($this->user)
            ->deleteJson("/api/v1/router/{$device->id}/cache");

        $clearResponse->assertStatus(200);
        $clearResponse->assertJsonFragment([
            'message' => 'Cache cleared successfully'
        ]);
    }

    public function test_it_handles_configuration_changes_through_api()
    {
        $device = MikroTikDevice::factory()->create([
            'configuration' => [
                'system_name' => 'Original Router'
            ]
        ]);

        // Update configuration through API
        $updateResponse = $this->actingAs($this->user)
            ->putJson("/api/v1/router-management/{$device->id}", [
                'configuration' => [
                    'system_name' => 'Updated Router',
                    'interfaces' => ['ether1', 'ether2', 'ether3']
                ]
            ]);

        $updateResponse->assertStatus(200);

        // Verify configuration was updated in database
        $device->refresh();
        $this->assertEquals('Updated Router', $device->configuration['system_name']);
    }

    public function test_it_maintains_connection_pooling()
    {
        $devices = MikroTikDevice::factory()->count(3)->create();

        // Make concurrent requests to multiple devices
        $responses = [];
        foreach ($devices as $device) {
            $responses[] = $this->actingAs($this->user)
                ->getJson("/api/v1/router/{$device->id}/statistics");
        }

        // All requests should be handled successfully
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
    }
}