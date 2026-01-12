<?php

namespace App\Services;

use App\Models\MikroTikDevice;
use App\Models\MikroTikConfigHistory;
use App\Models\MikroTikUser;
use App\Events\MikroTikConfigurationChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class MikroTikDatabaseService
{
    /**
     * Synchronize device status to database
     */
    public function syncDeviceStatus(string $deviceId, string $status, ?array $additionalData = null): bool
    {
        try {
            $device = MikroTikDevice::find($deviceId);
            
            if (!$device) {
                Log::warning('Attempted to sync status for non-existent device', [
                    'device_id' => $deviceId,
                    'status' => $status,
                ]);
                return false;
            }

            $updateData = ['status' => $status];
            
            // Add additional data if provided
            if ($additionalData) {
                if (isset($additionalData['uptime_seconds'])) {
                    $updateData['uptime_seconds'] = $additionalData['uptime_seconds'];
                }
                if (isset($additionalData['last_seen'])) {
                    $updateData['last_seen'] = $additionalData['last_seen'];
                }
            }

            // Update last_seen for online devices
            if ($status === 'online') {
                $updateData['last_seen'] = now();
            }

            $updated = $device->update($updateData);

            if ($updated) {
                Log::info('Device status synchronized', [
                    'device_id' => $deviceId,
                    'status' => $status,
                    'additional_data' => $additionalData,
                ]);
            }

            return $updated;

        } catch (\Exception $e) {
            Log::error('Failed to sync device status', [
                'device_id' => $deviceId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Store device configuration with history tracking
     */
    public function storeDeviceConfiguration(string $deviceId, array $configuration, string $changeType = 'update'): ?MikroTikConfigHistory
    {
        try {
            return DB::transaction(function () use ($deviceId, $configuration, $changeType) {
                $device = MikroTikDevice::find($deviceId);
                
                if (!$device) {
                    throw new \Exception("Device not found: {$deviceId}");
                }

                $userId = Auth::id();

                // Create backup of current configuration if it exists and this is an update
                if ($changeType === 'update' && $device->configuration) {
                    $device->createConfigBackup($device->configuration, $userId);
                }

                // Update device configuration
                $device->update(['configuration' => $configuration]);

                // Create configuration history entry
                $configHistory = MikroTikConfigHistory::create([
                    'device_id' => $deviceId,
                    'configuration_data' => $configuration,
                    'change_type' => $changeType,
                    'changed_by' => $userId,
                ]);

                // Broadcast configuration change
                broadcast(new MikroTikConfigurationChanged($device, $configHistory, $changeType));

                Log::info('Device configuration stored', [
                    'device_id' => $deviceId,
                    'change_type' => $changeType,
                    'config_history_id' => $configHistory->id,
                ]);

                return $configHistory;
            });

        } catch (\Exception $e) {
            Log::error('Failed to store device configuration', [
                'device_id' => $deviceId,
                'change_type' => $changeType,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Sync MikroTik users to database
     */
    public function syncMikroTikUsers(string $deviceId, array $users): bool
    {
        try {
            return DB::transaction(function () use ($deviceId, $users) {
                $device = MikroTikDevice::find($deviceId);
                
                if (!$device) {
                    throw new \Exception("Device not found: {$deviceId}");
                }

                // Get existing users for this device
                $existingUsers = MikroTikUser::where('device_id', $deviceId)->get()->keyBy('username');
                $syncedUsernames = [];

                foreach ($users as $userData) {
                    $username = $userData['username'];
                    $syncedUsernames[] = $username;

                    $existingUser = $existingUsers->get($username);

                    if ($existingUser) {
                        // Update existing user
                        $existingUser->update([
                            'password' => $userData['password'] ?? $existingUser->password,
                            'profile' => $userData['profile'] ?? $existingUser->profile,
                            'is_active' => $userData['is_active'] ?? $existingUser->is_active,
                        ]);
                    } else {
                        // Create new user
                        MikroTikUser::create([
                            'device_id' => $deviceId,
                            'username' => $username,
                            'password' => $userData['password'] ?? 'default',
                            'profile' => $userData['profile'] ?? 'default',
                            'voucher_id' => $userData['voucher_id'] ?? null,
                            'is_active' => $userData['is_active'] ?? true,
                        ]);
                    }
                }

                // Deactivate users that are no longer present on the device
                MikroTikUser::where('device_id', $deviceId)
                    ->whereNotIn('username', $syncedUsernames)
                    ->update(['is_active' => false]);

                Log::info('MikroTik users synchronized', [
                    'device_id' => $deviceId,
                    'synced_users_count' => count($syncedUsernames),
                ]);

                return true;
            });

        } catch (\Exception $e) {
            Log::error('Failed to sync MikroTik users', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle device connection failure gracefully
     */
    public function handleConnectionFailure(string $deviceId, string $reason = 'Connection timeout'): bool
    {
        try {
            $device = MikroTikDevice::find($deviceId);
            
            if (!$device) {
                Log::warning('Attempted to handle connection failure for non-existent device', [
                    'device_id' => $deviceId,
                ]);
                return false;
            }

            // Update device status to error
            $device->update([
                'status' => 'error',
                'uptime_seconds' => 0,
            ]);

            // Log the failure
            Log::warning('MikroTik device connection failure handled', [
                'device_id' => $deviceId,
                'device_name' => $device->name,
                'reason' => $reason,
                'last_seen' => $device->last_seen,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to handle device connection failure', [
                'device_id' => $deviceId,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get device configuration history
     */
    public function getConfigurationHistory(string $deviceId, int $limit = 50): array
    {
        try {
            $history = MikroTikConfigHistory::getHistoryForDevice($deviceId, $limit);
            
            return $history->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'change_type' => $entry->change_type,
                    'changed_by' => $entry->changedBy?->name ?? 'System',
                    'created_at' => $entry->created_at->toISOString(),
                    'configuration_size' => strlen(json_encode($entry->configuration_data)),
                ];
            })->toArray();

        } catch (\Exception $e) {
            Log::error('Failed to get configuration history', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Restore device configuration from backup
     */
    public function restoreConfiguration(string $deviceId, string $historyId): bool
    {
        try {
            return DB::transaction(function () use ($deviceId, $historyId) {
                $device = MikroTikDevice::find($deviceId);
                
                if (!$device) {
                    throw new \Exception("Device not found: {$deviceId}");
                }

                $success = $device->restoreConfigFromBackup($historyId, Auth::id());

                if ($success) {
                    Log::info('Device configuration restored', [
                        'device_id' => $deviceId,
                        'history_id' => $historyId,
                        'restored_by' => Auth::id(),
                    ]);
                }

                return $success;
            });

        } catch (\Exception $e) {
            Log::error('Failed to restore device configuration', [
                'device_id' => $deviceId,
                'history_id' => $historyId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get device statistics
     */
    public function getDeviceStatistics(string $deviceId): array
    {
        try {
            $device = MikroTikDevice::with(['mikrotikUsers', 'configHistory'])->find($deviceId);
            
            if (!$device) {
                return [];
            }

            return [
                'device_info' => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'status' => $device->status,
                    'uptime' => $device->formatted_uptime,
                    'last_seen' => $device->last_seen?->toISOString(),
                ],
                'users' => [
                    'total' => $device->total_users_count,
                    'active' => $device->active_users_count,
                    'inactive' => $device->total_users_count - $device->active_users_count,
                ],
                'configuration' => [
                    'has_config' => $device->hasConfiguration(),
                    'history_entries' => $device->configHistory()->count(),
                    'last_updated' => $device->updated_at->toISOString(),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get device statistics', [
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}