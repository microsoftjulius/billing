<?php

use Tests\TestCase;
use App\Models\User;
use App\Models\MikroTikDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;

/**
 * Property 37: Router Credential Encryption
 * Feature: vue-frontend-enhancement, Property 37: Router Credential Encryption
 * 
 * For any router credentials stored in the system, they should be encrypted in the database and never stored in plain text.
 * Validates: Requirements 14.3
 */
class RouterCredentialEncryptionTest extends TestCase
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
     * Property test for router credential encryption
     */
    public function test_router_credential_encryption_property()
    {
        // Generate random test data for property-based testing
        for ($i = 0; $i < 100; $i++) {
            $this->runRouterCredentialEncryptionTest($i);
        }
    }

    /**
     * Run a single iteration of router credential encryption test
     */
    private function runRouterCredentialEncryptionTest(int $iteration): void
    {
        // Generate random router configuration with various password types
        $routerConfig = $this->generateRandomRouterConfig();
        $plainTextPassword = $routerConfig['password'];
        
        // Create router with the generated configuration
        $router = MikroTikDevice::create([
            'name' => $routerConfig['name'],
            'ip_address' => $routerConfig['ip_address'],
            'api_port' => $routerConfig['api_port'],
            'username' => $routerConfig['username'],
            'password' => $plainTextPassword, // This should be encrypted by the model mutator
            'location' => $routerConfig['location'],
            'status' => 'offline',
            'uptime_seconds' => 0,
        ]);

        // Property 1: Password should never be stored in plain text in the database
        $this->assertNotEquals(
            $plainTextPassword, 
            $router->password_encrypted,
            'Password should not be stored in plain text in the database'
        );

        // Property 2: Encrypted password should not be null or empty
        $this->assertNotNull(
            $router->password_encrypted,
            'Encrypted password should not be null'
        );
        
        $this->assertNotEmpty(
            $router->password_encrypted,
            'Encrypted password should not be empty'
        );

        // Property 3: Encrypted password should be different from original password
        $this->assertNotEquals(
            $plainTextPassword,
            $router->password_encrypted,
            'Encrypted password should be different from original password'
        );

        // Property 4: Password should be decryptable back to original value
        $decryptedPassword = $router->password;
        $this->assertEquals(
            $plainTextPassword,
            $decryptedPassword,
            'Decrypted password should match original password'
        );

        // Property 5: Encrypted password should be a valid encrypted string
        try {
            $testDecrypt = decrypt($router->password_encrypted);
            $this->assertEquals(
                $plainTextPassword,
                $testDecrypt,
                'Encrypted password should be decryptable using Laravel decrypt function'
            );
        } catch (\Exception $e) {
            $this->fail('Encrypted password should be a valid encrypted string: ' . $e->getMessage());
        }

        // Property 6: Password should not be exposed in array/JSON serialization
        $routerArray = $router->toArray();
        $this->assertArrayNotHasKey(
            'password',
            $routerArray,
            'Password should not be exposed in array serialization'
        );
        
        $this->assertArrayNotHasKey(
            'password_encrypted',
            $routerArray,
            'Encrypted password should not be exposed in array serialization'
        );

        // Property 7: Password should not be exposed in JSON serialization
        $routerJson = json_decode($router->toJson(), true);
        $this->assertArrayNotHasKey(
            'password',
            $routerJson,
            'Password should not be exposed in JSON serialization'
        );
        
        $this->assertArrayNotHasKey(
            'password_encrypted',
            $routerJson,
            'Encrypted password should not be exposed in JSON serialization'
        );

        // Property 8: Multiple routers with same password should have different encrypted values
        if ($iteration % 10 === 0) { // Test this property every 10th iteration to avoid too many DB operations
            $secondRouterConfig = $this->generateRandomRouterConfig();
            $secondRouterConfig['password'] = $plainTextPassword; // Use same password
            
            $secondRouter = MikroTikDevice::create([
                'name' => $secondRouterConfig['name'],
                'ip_address' => $secondRouterConfig['ip_address'],
                'api_port' => $secondRouterConfig['api_port'],
                'username' => $secondRouterConfig['username'],
                'password' => $plainTextPassword,
                'location' => $secondRouterConfig['location'],
                'status' => 'offline',
                'uptime_seconds' => 0,
            ]);

            // Even with same password, encrypted values should be different (due to Laravel's encryption)
            $this->assertNotEquals(
                $router->password_encrypted,
                $secondRouter->password_encrypted,
                'Same passwords should have different encrypted values due to Laravel encryption'
            );

            // But both should decrypt to the same original password
            $this->assertEquals(
                $router->password,
                $secondRouter->password,
                'Both routers should decrypt to the same original password'
            );

            $secondRouter->delete();
        }

        // Property 9: Password update should result in new encrypted value
        $newPassword = $this->generateRandomPassword();
        $oldEncryptedPassword = $router->password_encrypted;
        
        $router->update(['password' => $newPassword]);
        $router->refresh();

        $this->assertNotEquals(
            $oldEncryptedPassword,
            $router->password_encrypted,
            'Updating password should result in new encrypted value'
        );

        $this->assertEquals(
            $newPassword,
            $router->password,
            'Updated password should decrypt to new password value'
        );

        // Property 10: Encryption should be reversible and consistent
        $testPassword = $this->generateRandomPassword();
        $router->password = $testPassword;
        $router->save();
        $router->refresh();

        $this->assertEquals(
            $testPassword,
            $router->password,
            'Password encryption should be reversible and consistent'
        );

        // Clean up
        $router->delete();
    }

    /**
     * Test specific encryption edge cases
     */
    public function test_router_credential_encryption_edge_cases()
    {
        $edgeCasePasswords = [
            'minimum_length' => 'abcdef', // Minimum 6 characters
            'special_chars' => 'p@ssw0rd!#$%',
            'unicode_chars' => 'pässwörd123',
            'long_password' => str_repeat('a', 100),
            'mixed_case' => 'MiXeDcAsEpAsSwOrD123',
            'numbers_only' => '123456789',
            'spaces_included' => 'password with spaces',
            'quotes_included' => 'pass"word\'with`quotes',
        ];

        foreach ($edgeCasePasswords as $caseName => $password) {
            $routerConfig = $this->generateValidRouterConfig();
            $routerConfig['password'] = $password;

            $router = MikroTikDevice::create([
                'name' => $routerConfig['name'] . '-' . $caseName,
                'ip_address' => $routerConfig['ip_address'],
                'api_port' => $routerConfig['api_port'],
                'username' => $routerConfig['username'],
                'password' => $password,
                'location' => $routerConfig['location'],
                'status' => 'offline',
                'uptime_seconds' => 0,
            ]);

            // Test encryption properties for edge case
            $this->assertNotEquals(
                $password,
                $router->password_encrypted,
                "Password should be encrypted for case: {$caseName}"
            );

            $this->assertEquals(
                $password,
                $router->password,
                "Password should decrypt correctly for case: {$caseName}"
            );

            // Test that encrypted value is not exposed
            $routerArray = $router->toArray();
            $this->assertArrayNotHasKey(
                'password',
                $routerArray,
                "Password should not be exposed in array for case: {$caseName}"
            );

            $router->delete();
        }
    }

    /**
     * Test encryption consistency across multiple operations
     */
    public function test_router_credential_encryption_consistency()
    {
        $password = 'consistent_test_password_123';
        $routerConfig = $this->generateValidRouterConfig();

        // Create router
        $router = MikroTikDevice::create([
            'name' => $routerConfig['name'],
            'ip_address' => $routerConfig['ip_address'],
            'api_port' => $routerConfig['api_port'],
            'username' => $routerConfig['username'],
            'password' => $password,
            'location' => $routerConfig['location'],
            'status' => 'offline',
            'uptime_seconds' => 0,
        ]);

        $originalEncrypted = $router->password_encrypted;

        // Test multiple reads
        for ($i = 0; $i < 10; $i++) {
            $router->refresh();
            $this->assertEquals(
                $password,
                $router->password,
                'Password should decrypt consistently across multiple reads'
            );
            
            $this->assertEquals(
                $originalEncrypted,
                $router->password_encrypted,
                'Encrypted password should remain the same across reads'
            );
        }

        // Test update with same password
        $router->update(['password' => $password]);
        $router->refresh();

        // Should have new encrypted value even with same password
        $this->assertNotEquals(
            $originalEncrypted,
            $router->password_encrypted,
            'Re-setting same password should create new encrypted value'
        );

        $this->assertEquals(
            $password,
            $router->password,
            'Password should still decrypt correctly after update'
        );

        $router->delete();
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
            'password' => $this->generateRandomPassword(),
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
            'password' => $this->generateRandomPassword(),
            'location' => [
                'region' => 'Central',
                'district' => 'Kampala'
            ]
        ];
    }

    /**
     * Generate a random password meeting minimum requirements
     */
    private function generateRandomPassword(): string
    {
        $passwordTypes = [
            fake()->password(6, 20),
            'admin123',
            'password' . fake()->numberBetween(100, 999),
            fake()->lexify('??????') . fake()->numberBetween(10, 99),
            'secure_' . fake()->bothify('??##??'),
            fake()->regexify('[A-Za-z0-9]{8,15}'),
        ];

        return fake()->randomElement($passwordTypes);
    }
}