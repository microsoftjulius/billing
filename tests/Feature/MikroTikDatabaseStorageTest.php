<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\MikroTikDevice;
use App\Models\MikroTikConfigHistory;
use App\Models\MikroTikUser;
use App\Services\MikroTikDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

/**
 * Property 47: MikroTik Database Storage
 * Feature: vue-frontend-enhancement, Property 47: MikroTik Database Storage
 * 
 * For any MikroTik device configuration, it should be stored in the database and retrievable with all configuration details intact.
 * **Validates: Requirements 16.1**
 */
class MikroTikDatabaseStorageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected MikroTikDatabaseService $databaseService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable tenancy for testing to avoid database issues
        Config::set('tenancy.features', []);
        
        // Disable event broadcasting for testing
        Event::fake();
        
        $this->user = User::factory()->create([
            'id' => \Illuminate\Support\Str::orderedUuid(),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->actingAs($this->user, 'sanctum');
        
        $this->databaseService = new MikroTikDatabaseService();
    }

    /**
     * Property test for MikroTik database storage
     * **Validates: Requirements 16.1**
     */
    public function test_mikrotik_database_storage_property()
    {
        // Generate random test data for property-based testing
        for ($i = 0; $i < 100; $i++) {
            $this->runMikroTikDatabaseStorageTest();
        }
    }

    /**
     * Run a single iteration of MikroTik database storage test
     */
    private function runMikroTikDatabaseStorageTest(): void
    {
        // Generate random device and configuration data
        $deviceData = $this->generateRandomDeviceData();
        $configurationData = $this->generateRandomConfigurationData();
        
        // Create MikroTik device
        $device = MikroTikDevice::create($deviceData);
        $this->assertNotNull($device->id, 'Device should be created with valid ID');
        
        // Test 1: Store device configuration
        $configHistory = $this->databaseService->storeDeviceConfiguration(
            $device->id, 
            $configurationData, 
            'update'
        );
        
        $this->assertNotNull($configHistory, 'Configuration history should be created');
        $this->assertInstanceOf(MikroTikConfigHistory::class, $configHistory);
        
        // Verify device configuration is stored
        $device->refresh();
        $this->assertEquals($configurationData, $device->configuration, 'Device configuration should match stored data');
        
        // Verify configuration history is created
        $this->assertDatabaseHas('mikrotik_config_history', [
            'id' => $configHistory->id,
            'device_id' => $device->id,
            'change_type' => 'update',
            'changed_by' => $this->user->id,
        ]);
        
        // Test configuration data integrity
        $storedConfigHistory = MikroTikConfigHistory::find($configHistory->id);
        $this->assertEquals($configurationData, $storedConfigHistory->configuration_data, 'Configuration history data should match original');
        
        // Test 2: Store device status with additional data
        $statusData = $this->generateRandomStatusData();
        $statusUpdated = $this->databaseService->syncDeviceStatus(
            $device->id,
            $statusData['status'],
            $statusData['additional_data']
        );
        
        $this->assertTrue($statusUpdated, 'Device status should be updated successfully');
        
        // Verify status is stored correctly
        $device->refresh();
        $this->assertEquals($statusData['status'], $device->status, 'Device status should match');
        
        if (isset($statusData['additional_data']['uptime_seconds'])) {
            $this->assertEquals($statusData['additional_data']['uptime_seconds'], $device->uptime_seconds, 'Uptime should match');
        }
        
        if ($statusData['status'] === 'online') {
            $this->assertNotNull($device->last_seen, 'Last seen should be set for online devices');
        }
        
        // Test 3: Store MikroTik users
        $usersData = $this->generateRandomUsersData();
        $usersStored = $this->databaseService->syncMikroTikUsers($device->id, $usersData);
        
        $this->assertTrue($usersStored, 'MikroTik users should be stored successfully');
        
        // Verify users are stored correctly
        foreach ($usersData as $userData) {
            $this->assertDatabaseHas('mikrotik_users', [
                'device_id' => $device->id,
                'username' => $userData['username'],
                'profile' => $userData['profile'],
                'is_active' => $userData['is_active'],
            ]);
        }
        
        // Test 4: Retrieve stored data and verify integrity
        $retrievedDevice = MikroTikDevice::with(['configHistory', 'mikrotikUsers'])->find($device->id);
        
        $this->assertNotNull($retrievedDevice, 'Device should be retrievable');
        $this->assertEquals($configurationData, $retrievedDevice->configuration, 'Retrieved configuration should match stored data');
        
        // Verify configuration history is retrievable
        $configHistoryEntries = $retrievedDevice->configHistory;
        $this->assertGreaterThan(0, $configHistoryEntries->count(), 'Configuration history should exist');
        
        $latestConfig = $configHistoryEntries->first();
        $this->assertEquals($configurationData, $latestConfig->configuration_data, 'Latest configuration should match stored data');
        
        // Verify users are retrievable
        $retrievedUsers = $retrievedDevice->mikrotikUsers;
        $this->assertEquals(count($usersData), $retrievedUsers->count(), 'User count should match');
        
        foreach ($usersData as $userData) {
            $foundUser = $retrievedUsers->firstWhere('username', $userData['username']);
            $this->assertNotNull($foundUser, "User {$userData['username']} should be found");
            $this->assertEquals($userData['profile'], $foundUser->profile, 'User profile should match');
            $this->assertEquals($userData['is_active'], $foundUser->is_active, 'User active status should match');
        }
        
        // Test 5: Configuration backup and restore
        $backupConfigData = $this->generateRandomConfigurationData();
        $backupHistory = $device->createConfigBackup($backupConfigData, $this->user->id);
        
        $this->assertNotNull($backupHistory, 'Backup should be created');
        $this->assertEquals('backup', $backupHistory->change_type, 'Backup type should be correct');
        $this->assertEquals($backupConfigData, $backupHistory->configuration_data, 'Backup data should match');
        
        // Test restore functionality
        $restoreSuccess = $device->restoreConfigFromBackup($backupHistory->id, $this->user->id);
        $this->assertTrue($restoreSuccess, 'Configuration should be restored successfully');
        
        $device->refresh();
        $this->assertEquals($backupConfigData, $device->configuration, 'Restored configuration should match backup');
        
        // Test 6: Device statistics retrieval
        $statistics = $this->databaseService->getDeviceStatistics($device->id);
        
        $this->assertIsArray($statistics, 'Statistics should be an array');
        $this->assertArrayHasKey('device_info', $statistics, 'Statistics should contain device info');
        $this->assertArrayHasKey('users', $statistics, 'Statistics should contain user info');
        $this->assertArrayHasKey('configuration', $statistics, 'Statistics should contain configuration info');
        
        // Verify device info accuracy
        $this->assertEquals($device->id, $statistics['device_info']['id'], 'Device ID should match');
        $this->assertEquals($device->name, $statistics['device_info']['name'], 'Device name should match');
        $this->assertEquals($device->status, $statistics['device_info']['status'], 'Device status should match');
        
        // Verify user counts
        $expectedActiveUsers = $retrievedUsers->where('is_active', true)->count();
        $expectedTotalUsers = $retrievedUsers->count();
        
        $this->assertEquals($expectedTotalUsers, $statistics['users']['total'], 'Total user count should match');
        $this->assertEquals($expectedActiveUsers, $statistics['users']['active'], 'Active user count should match');
        
        // Verify configuration info
        $this->assertTrue($statistics['configuration']['has_config'], 'Should indicate configuration exists');
        $this->assertGreaterThan(0, $statistics['configuration']['history_entries'], 'Should have configuration history');
        
        // Clean up for next iteration
        $device->mikrotikUsers()->delete();
        $device->configHistory()->delete();
        $device->delete();
    }

    /**
     * Generate random device data for testing
     */
    private function generateRandomDeviceData(): array
    {
        return [
            'name' => 'Test-Device-' . fake()->bothify('##??##'),
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
            ],
            'status' => fake()->randomElement(['online', 'offline', 'error']),
            'uptime_seconds' => fake()->numberBetween(0, 2592000),
        ];
    }

    /**
     * Generate random configuration data for testing
     */
    private function generateRandomConfigurationData(): array
    {
        $interfaceCount = fake()->numberBetween(2, 5);
        $interfaces = [];
        
        for ($i = 1; $i <= $interfaceCount; $i++) {
            $interfaces["ether{$i}"] = [
                'name' => "ether{$i}",
                'type' => 'ethernet',
                'mtu' => fake()->randomElement([1500, 1400, 9000]),
                'disabled' => fake()->boolean(20), // 20% chance of being disabled
            ];
        }
        
        $ipAddresses = [];
        $addressCount = fake()->numberBetween(1, 3);
        
        for ($i = 0; $i < $addressCount; $i++) {
            $ipAddresses[] = [
                'address' => fake()->localIpv4() . '/' . fake()->randomElement([24, 16, 8]),
                'interface' => "ether" . fake()->numberBetween(1, $interfaceCount),
                'disabled' => fake()->boolean(10), // 10% chance of being disabled
            ];
        }
        
        return [
            'system' => [
                'identity' => fake()->domainWord(),
                'clock' => [
                    'time_zone_name' => fake()->randomElement(['Africa/Kampala', 'UTC', 'Africa/Nairobi']),
                ],
                'ntp_client' => [
                    'enabled' => fake()->boolean(80),
                    'servers' => fake()->boolean(70) ? ['pool.ntp.org', 'time.google.com'] : [],
                ],
            ],
            'interfaces' => $interfaces,
            'ip_addresses' => $ipAddresses,
            'dhcp_server' => fake()->boolean(60) ? [
                'name' => 'dhcp1',
                'interface' => "ether" . fake()->numberBetween(1, $interfaceCount),
                'address_pool' => fake()->localIpv4() . '-' . fake()->localIpv4(),
                'disabled' => fake()->boolean(10),
                'lease_time' => fake()->randomElement(['1h', '24h', '1w']),
            ] : null,
            'firewall_rules' => $this->generateRandomFirewallRules(),
            'user_manager' => [
                'enabled' => fake()->boolean(70),
                'database' => fake()->boolean(50) ? 'radius' : 'local',
            ],
        ];
    }

    /**
     * Generate random firewall rules
     */
    private function generateRandomFirewallRules(): array
    {
        $rules = [];
        $ruleCount = fake()->numberBetween(2, 8);
        
        for ($i = 0; $i < $ruleCount; $i++) {
            $rules[] = [
                'chain' => fake()->randomElement(['input', 'forward', 'output']),
                'action' => fake()->randomElement(['accept', 'drop', 'reject']),
                'protocol' => fake()->optional()->randomElement(['tcp', 'udp', 'icmp']),
                'src_address' => fake()->optional()->ipv4(),
                'dst_port' => fake()->optional()->numberBetween(1, 65535),
                'connection_state' => fake()->optional()->randomElement(['new', 'established', 'related']),
                'disabled' => fake()->boolean(15), // 15% chance of being disabled
            ];
        }
        
        return $rules;
    }

    /**
     * Generate random status data for testing
     */
    private function generateRandomStatusData(): array
    {
        $status = fake()->randomElement(['online', 'offline', 'error']);
        $additionalData = [];
        
        if ($status === 'online') {
            $additionalData['uptime_seconds'] = fake()->numberBetween(3600, 2592000); // 1 hour to 30 days
        } else {
            $additionalData['uptime_seconds'] = 0;
        }
        
        if (fake()->boolean(50)) {
            $additionalData['last_seen'] = now()->subMinutes(fake()->numberBetween(1, 1440));
        }
        
        return [
            'status' => $status,
            'additional_data' => $additionalData,
        ];
    }

    /**
     * Generate random users data for testing
     */
    private function generateRandomUsersData(): array
    {
        $users = [];
        $userCount = fake()->numberBetween(1, 10);
        
        for ($i = 0; $i < $userCount; $i++) {
            $users[] = [
                'username' => 'user_' . fake()->unique()->numberBetween(1000, 9999),
                'password' => fake()->password(8, 16),
                'profile' => fake()->randomElement(['default', '1M', '2M', '5M', '10M', 'unlimited']),
                'is_active' => fake()->boolean(80), // 80% chance of being active
                // Don't include voucher_id to avoid foreign key issues
            ];
        }
        
        return $users;
    }

    /**
     * Test edge case: Invalid device ID for configuration storage
     */
    public function test_store_configuration_invalid_device_id()
    {
        $invalidDeviceId = fake()->uuid();
        $configData = $this->generateRandomConfigurationData();
        
        $result = $this->databaseService->storeDeviceConfiguration($invalidDeviceId, $configData);
        
        $this->assertNull($result, 'Should return null for invalid device ID');
    }

    /**
     * Test edge case: Invalid device ID for status sync
     */
    public function test_sync_status_invalid_device_id()
    {
        $invalidDeviceId = fake()->uuid();
        
        $result = $this->databaseService->syncDeviceStatus($invalidDeviceId, 'online');
        
        $this->assertFalse($result, 'Should return false for invalid device ID');
    }

    /**
     * Test edge case: Empty configuration data
     */
    public function test_store_empty_configuration()
    {
        $device = MikroTikDevice::factory()->create();
        
        $result = $this->databaseService->storeDeviceConfiguration($device->id, []);
        
        $this->assertNotNull($result, 'Should handle empty configuration');
        
        $device->refresh();
        $this->assertEquals([], $device->configuration, 'Empty configuration should be stored');
    }

    /**
     * Test edge case: Large configuration data
     */
    public function test_store_large_configuration()
    {
        $device = MikroTikDevice::factory()->create();
        
        // Generate large configuration with many interfaces and rules
        $largeConfig = [
            'interfaces' => [],
            'firewall_rules' => [],
        ];
        
        // Add 50 interfaces
        for ($i = 1; $i <= 50; $i++) {
            $largeConfig['interfaces']["ether{$i}"] = [
                'name' => "ether{$i}",
                'type' => 'ethernet',
                'mtu' => 1500,
                'disabled' => false,
            ];
        }
        
        // Add 100 firewall rules
        for ($i = 1; $i <= 100; $i++) {
            $largeConfig['firewall_rules'][] = [
                'chain' => 'forward',
                'action' => 'accept',
                'src_address' => fake()->ipv4(),
                'dst_address' => fake()->ipv4(),
                'comment' => "Rule {$i}",
            ];
        }
        
        $result = $this->databaseService->storeDeviceConfiguration($device->id, $largeConfig);
        
        $this->assertNotNull($result, 'Should handle large configuration');
        
        $device->refresh();
        $this->assertEquals($largeConfig, $device->configuration, 'Large configuration should be stored correctly');
    }

    /**
     * Test edge case: Connection failure handling
     */
    public function test_handle_connection_failure()
    {
        $device = MikroTikDevice::factory()->online()->create();
        
        $result = $this->databaseService->handleConnectionFailure($device->id, 'Network timeout');
        
        $this->assertTrue($result, 'Should handle connection failure successfully');
        
        $device->refresh();
        $this->assertEquals('error', $device->status, 'Device status should be set to error');
        $this->assertEquals(0, $device->uptime_seconds, 'Uptime should be reset to 0');
    }
}