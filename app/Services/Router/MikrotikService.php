<?php

namespace App\Services\Router;

use App\Contracts\Router\RouterManagerInterface;
use App\DTOs\Router\VoucherDTO;
use App\DTOs\Router\UserConnectionDTO;
use App\DTOs\Router\RouterStatsDTO;
use App\DTOs\Router\InterfaceStatsDTO;
use RouterOS\Client;
use RouterOS\Query;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MikrotikService implements RouterManagerInterface
{
    private Client $client;
    private array $config;
    private bool $isConnected = false;

    public function __construct()
    {
        $this->config = config('mikrotik');
        $this->connect();
    }

    private function connect(): void
    {
        try {
            $this->client = new Client([
                'host' => $this->config['host'],
                'port' => $this->config['port'],
                'user' => $this->config['user'],
                'pass' => $this->config['password'],
                'ssl' => $this->config['ssl'] ?? false,
                'timeout' => $this->config['timeout'] ?? 10,
                'attempts' => $this->config['attempts'] ?? 3,
                'delay' => $this->config['delay'] ?? 1,
            ]);

            $this->isConnected = true;

            Log::channel('router')->info('MikroTik connected successfully', [
                'host' => $this->config['host'],
                'user' => $this->config['user']
            ]);

        } catch (\Exception $e) {
            $this->isConnected = false;

            Log::channel('router')->error('MikroTik connection failed', [
                'host' => $this->config['host'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \RuntimeException('Failed to connect to MikroTik router: ' . $e->getMessage());
        }
    }

    public function createVoucher(VoucherDTO $voucher): bool
    {
        if (!$this->isConnected) {
            throw new \RuntimeException('Not connected to MikroTik router');
        }

        try {
            // Check if voucher already exists
            $existingUser = $this->getUser($voucher->code);
            if ($existingUser) {
                Log::channel('router')->warning('Voucher already exists on router', [
                    'code' => $voucher->code
                ]);
                return false;
            }

            // Prepare user data for MikroTik
            $userData = $voucher->toMikrotikArray();

            // Create hotspot user
            $query = (new Query('/ip/hotspot/user/add'))
                ->equal('name', $userData['name'])
                ->equal('password', $userData['password'])
                ->equal('profile', $userData['profile'])
                ->equal('limit-uptime', $userData['limit-uptime'])
                ->equal('comment', $userData['comment']);

            // Add data limit if specified
            if (isset($userData['limit-bytes-total'])) {
                $query->equal('limit-bytes-total', $userData['limit-bytes-total']);
            }

            $response = $this->client->query($query)->read();

            if (empty($response) || !isset($response[0]['ret'])) {
                throw new \Exception('Invalid response from MikroTik');
            }

            $userId = $response[0]['ret'];

            Log::channel('router')->info('Voucher created on MikroTik', [
                'code' => $voucher->code,
                'profile' => $voucher->profile,
                'validity_hours' => $voucher->validityHours,
                'mikrotik_id' => $userId
            ]);

            // Clear cache for this user
            Cache::forget("mikrotik.user.{$voucher->code}");
            Cache::forget("mikrotik.active_users");

            return true;

        } catch (\Exception $e) {
            Log::channel('router')->error('Failed to create voucher on MikroTik', [
                'voucher' => $voucher->toArray(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    public function disableVoucher(string $voucherCode): bool
    {
        if (!$this->isConnected) {
            throw new \RuntimeException('Not connected to MikroTik router');
        }

        try {
            $user = $this->getUser($voucherCode);
            if (!$user) {
                Log::channel('router')->warning('Voucher not found on router', [
                    'code' => $voucherCode
                ]);
                return false;
            }

            // Disable the user
            $query = (new Query('/ip/hotspot/user/disable'))
                ->equal('.id', $user['.id']);

            $this->client->query($query)->read();

            Log::channel('router')->info('Voucher disabled on MikroTik', [
                'code' => $voucherCode,
                'mikrotik_id' => $user['.id']
            ]);

            // Clear cache
            Cache::forget("mikrotik.user.{$voucherCode}");
            Cache::forget("mikrotik.active_users");

            return true;

        } catch (\Exception $e) {
            Log::channel('router')->error('Failed to disable voucher on MikroTik', [
                'voucher_code' => $voucherCode,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function getUserConnections(string $username): array
    {
        return Cache::remember("mikrotik.connections.{$username}", 60, function () use ($username) {
            try {
                if (!$this->isConnected) {
                    return [];
                }

                $query = (new Query('/ip/hotspot/active/print'))
                    ->where('user', $username);

                $connections = $this->client->query($query)->read();

                return array_map(function ($connection) {
                    return UserConnectionDTO::fromMikrotikData($connection)->toArray();
                }, $connections);

            } catch (\Exception $e) {
                Log::channel('router')->error('Failed to get user connections', [
                    'username' => $username,
                    'error' => $e->getMessage()
                ]);

                return [];
            }
        });
    }

    public function getSystemResources(): array
    {
        return Cache::remember('mikrotik.system.resources', 30, function () {
            try {
                if (!$this->isConnected) {
                    return [];
                }

                $query = (new Query('/system/resource/print'));
                $resources = $this->client->query($query)->read();

                if (empty($resources)) {
                    return [];
                }

                $data = $resources[0];

                // Get additional stats
                $activeUsers = $this->getActiveUsersCount();
                $totalUsers = $this->getTotalUsersCount();

                // Combine data
                $combinedData = array_merge($data, [
                    'total-users' => $totalUsers,
                    'active-users' => $activeUsers,
                    'total-vouchers' => $totalUsers, // Assuming each user is a voucher
                    'active-vouchers' => $activeUsers,
                ]);

                return RouterStatsDTO::fromMikrotikData($combinedData)->toArray();

            } catch (\Exception $e) {
                Log::channel('router')->error('Failed to get system resources', [
                    'error' => $e->getMessage()
                ]);

                return [];
            }
        });
    }

    public function getActiveUsers(): array
    {
        return Cache::remember('mikrotik.active_users', 30, function () {
            try {
                if (!$this->isConnected) {
                    return [];
                }

                $query = (new Query('/ip/hotspot/active/print'));
                $activeUsers = $this->client->query($query)->read();

                return array_map(function ($user) {
                    return UserConnectionDTO::fromMikrotikData($user)->toArray();
                }, $activeUsers);

            } catch (\Exception $e) {
                Log::channel('router')->error('Failed to get active users', [
                    'error' => $e->getMessage()
                ]);

                return [];
            }
        });
    }

    public function getActiveUsersCount(): int
    {
        $activeUsers = $this->getActiveUsers();
        return count($activeUsers);
    }

    public function getTotalUsersCount(): int
    {
        return Cache::remember('mikrotik.total_users', 300, function () {
            try {
                if (!$this->isConnected) {
                    return 0;
                }

                $query = (new Query('/ip/hotspot/user/print'))
                    ->where('disabled', 'false');

                $users = $this->client->query($query)->read();
                return count($users);

            } catch (\Exception $e) {
                Log::channel('router')->error('Failed to get total users count', [
                    'error' => $e->getMessage()
                ]);

                return 0;
            }
        });
    }

    public function getUser(string $username): ?array
    {
        return Cache::remember("mikrotik.user.{$username}", 300, function () use ($username) {
            try {
                if (!$this->isConnected) {
                    return null;
                }

                $query = (new Query('/ip/hotspot/user/print'))
                    ->where('name', $username);

                $users = $this->client->query($query)->read();

                return !empty($users) ? $users[0] : null;

            } catch (\Exception $e) {
                Log::channel('router')->error('Failed to get user', [
                    'username' => $username,
                    'error' => $e->getMessage()
                ]);

                return null;
            }
        });
    }

    public function getInterfaceStats(): array
    {
        return Cache::remember('mikrotik.interfaces', 60, function () {
            try {
                if (!$this->isConnected) {
                    return [];
                }

                $query = (new Query('/interface/print'));
                $interfaces = $this->client->query($query)->read();

                return array_map(function ($interface) {
                    return InterfaceStatsDTO::fromMikrotikData($interface)->toArray();
                }, $interfaces);

            } catch (\Exception $e) {
                Log::channel('router')->error('Failed to get interface stats', [
                    'error' => $e->getMessage()
                ]);

                return [];
            }
        });
    }

    public function getHotspotProfiles(): array
    {
        return Cache::remember('mikrotik.hotspot_profiles', 300, function () {
            try {
                if (!$this->isConnected) {
                    return [];
                }

                $query = (new Query('/ip/hotspot/user/profile/print'));
                $profiles = $this->client->query($query)->read();

                return array_map(function ($profile) {
                    return [
                        'name' => $profile['name'] ?? 'unknown',
                        'session_timeout' => $profile['session-timeout'] ?? 'none',
                        'idle_timeout' => $profile['idle-timeout'] ?? 'none',
                        'keepalive_timeout' => $profile['keepalive-timeout'] ?? '2m',
                        'status_autorefresh' => $profile['status-autorefresh'] ?? '1m',
                        'shared_users' => $profile['shared-users'] ?? 1,
                        'rate_limit' => $profile['rate-limit'] ?? '0/0',
                        'address_pool' => $profile['address-pool'] ?? 'none',
                        'parent_queue' => $profile['parent-queue'] ?? 'none',
                    ];
                }, $profiles);

            } catch (\Exception $e) {
                Log::channel('router')->error('Failed to get hotspot profiles', [
                    'error' => $e->getMessage()
                ]);

                return [];
            }
        });
    }

    public function removeExpiredUsers(): int
    {
        try {
            if (!$this->isConnected) {
                return 0;
            }

            // Get all disabled users (expired vouchers)
            $query = (new Query('/ip/hotspot/user/print'))
                ->where('disabled', 'true');

            $disabledUsers = $this->client->query($query)->read();

            $removedCount = 0;

            foreach ($disabledUsers as $user) {
                // Remove user
                $removeQuery = (new Query('/ip/hotspot/user/remove'))
                    ->equal('.id', $user['.id']);

                $this->client->query($removeQuery)->read();
                $removedCount++;

                Log::channel('router')->debug('Removed expired user from MikroTik', [
                    'username' => $user['name'] ?? 'unknown',
                    'mikrotik_id' => $user['.id']
                ]);
            }

            if ($removedCount > 0) {
                Cache::forget('mikrotik.total_users');
                Log::channel('router')->info('Removed expired users from MikroTik', [
                    'count' => $removedCount
                ]);
            }

            return $removedCount;

        } catch (\Exception $e) {
            Log::channel('router')->error('Failed to remove expired users', [
                'error' => $e->getMessage()
            ]);

            return 0;
        }
    }

    public function testConnection(): bool
    {
        try {
            if (!$this->isConnected) {
                $this->connect();
            }

            // Simple query to test connection
            $query = new Query('/system/identity/print');
            $response = $this->client->query($query)->read();

            return !empty($response) && isset($response[0]['name']);

        } catch (\Exception $e) {
            $this->isConnected = false;
            Log::channel('router')->error('MikroTik connection test failed', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function reboot(): bool
    {
        try {
            if (!$this->isConnected) {
                return false;
            }

            $query = new Query('/system/reboot');
            $this->client->query($query)->read();

            Log::channel('router')->warning('MikroTik reboot initiated');

            // Mark as disconnected since router will restart
            $this->isConnected = false;

            return true;

        } catch (\Exception $e) {
            Log::channel('router')->error('Failed to reboot MikroTik', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function __destruct()
    {
        if (isset($this->client)) {
            try {
                unset($this->client);
            } catch (\Exception $e) {
                // Ignore errors during destruction
            }
        }
    }
}
