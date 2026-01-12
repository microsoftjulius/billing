<?php

namespace App\Observers;

use App\Models\MikroTikDevice;
use App\Models\MikroTikConfigHistory;
use App\Models\SystemLog;
use App\Events\MikroTikStatusUpdated;
use App\Jobs\InitializeMikroTikMonitoring;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class MikroTikDeviceObserver
{
    /**
     * Handle the MikroTikDevice "created" event.
     */
    public function created(MikroTikDevice $mikrotikDevice): void
    {
        try {
            // Log device creation
            Log::info('MikroTik device created', [
                'device_id' => $mikrotikDevice->id,
                'name' => $mikrotikDevice->name,
                'ip_address' => $mikrotikDevice->ip_address,
            ]);

            // Create system log entry
            SystemLog::create([
                'type' => 'mikrotik_device_created',
                'data' => [
                    'device_id' => $mikrotikDevice->id,
                    'device_name' => $mikrotikDevice->name,
                    'ip_address' => $mikrotikDevice->ip_address,
                    'created_by' => Auth::id(),
                    'timestamp' => now(),
                ],
            ]);

            // Initialize device monitoring (dispatch job)
            InitializeMikroTikMonitoring::dispatch($mikrotikDevice);

            // Broadcast device addition
            broadcast(new \App\Events\MikroTikDeviceAdded($mikrotikDevice));

        } catch (\Exception $e) {
            Log::error('Error in MikroTikDeviceObserver::created', [
                'device_id' => $mikrotikDevice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the MikroTikDevice "updated" event.
     */
    public function updated(MikroTikDevice $mikrotikDevice): void
    {
        try {
            // Handle status changes
            if ($mikrotikDevice->wasChanged('status')) {
                $this->handleStatusChange($mikrotikDevice);
            }

            // Handle configuration changes
            if ($mikrotikDevice->wasChanged('configuration')) {
                $this->handleConfigurationChange($mikrotikDevice);
            }

            // Handle connection details changes
            if ($mikrotikDevice->wasChanged(['ip_address', 'api_port', 'username', 'password_encrypted'])) {
                $this->handleConnectionDetailsChange($mikrotikDevice);
            }

            // Broadcast device update
            broadcast(new MikroTikStatusUpdated($mikrotikDevice, 'updated'));

        } catch (\Exception $e) {
            Log::error('Error in MikroTikDeviceObserver::updated', [
                'device_id' => $mikrotikDevice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the MikroTikDevice "deleted" event.
     */
    public function deleted(MikroTikDevice $mikrotikDevice): void
    {
        try {
            // Log device deletion
            Log::info('MikroTik device deleted', [
                'device_id' => $mikrotikDevice->id,
                'name' => $mikrotikDevice->name,
            ]);

            // Create system log entry
            SystemLog::create([
                'type' => 'mikrotik_device_deleted',
                'data' => [
                    'device_id' => $mikrotikDevice->id,
                    'device_name' => $mikrotikDevice->name,
                    'ip_address' => $mikrotikDevice->ip_address,
                    'deleted_by' => Auth::id(),
                    'timestamp' => now(),
                ],
            ]);

            // Broadcast device deletion
            broadcast(new MikroTikStatusUpdated($mikrotikDevice, 'deleted'));

        } catch (\Exception $e) {
            Log::error('Error in MikroTikDeviceObserver::deleted', [
                'device_id' => $mikrotikDevice->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle status changes
     */
    private function handleStatusChange(MikroTikDevice $mikrotikDevice): void
    {
        $oldStatus = $mikrotikDevice->getOriginal('status');
        $newStatus = $mikrotikDevice->status;

        // Log status change
        Log::info('MikroTik device status changed', [
            'device_id' => $mikrotikDevice->id,
            'device_name' => $mikrotikDevice->name,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        // Create system log entry for status change
        SystemLog::create([
            'type' => 'mikrotik_status_change',
            'data' => [
                'device_id' => $mikrotikDevice->id,
                'device_name' => $mikrotikDevice->name,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'timestamp' => now(),
                'last_seen' => $mikrotikDevice->last_seen,
            ],
        ]);

        // Handle connection failures
        if ($newStatus === 'offline' || $newStatus === 'error') {
            $this->handleConnectionFailure($mikrotikDevice, $oldStatus, $newStatus);
        }

        // Update last_seen timestamp for online status
        if ($newStatus === 'online' && $oldStatus !== 'online') {
            $mikrotikDevice->updateQuietly(['last_seen' => now()]);
        }
    }

    /**
     * Handle configuration changes
     */
    private function handleConfigurationChange(MikroTikDevice $mikrotikDevice): void
    {
        $userId = Auth::id();

        // Create configuration history entry
        $configHistory = MikroTikConfigHistory::create([
            'device_id' => $mikrotikDevice->id,
            'configuration_data' => $mikrotikDevice->configuration,
            'change_type' => 'update',
            'changed_by' => $userId,
        ]);

        // Log configuration change
        Log::info('MikroTik device configuration updated', [
            'device_id' => $mikrotikDevice->id,
            'device_name' => $mikrotikDevice->name,
            'changed_by' => $userId ?? 'system',
        ]);

        // Create system log entry
        SystemLog::create([
            'type' => 'mikrotik_config_change',
            'data' => [
                'device_id' => $mikrotikDevice->id,
                'device_name' => $mikrotikDevice->name,
                'change_type' => 'update',
                'changed_by' => $userId ?? 'system',
                'timestamp' => now(),
            ],
        ]);

        // Broadcast configuration change
        broadcast(new \App\Events\MikroTikConfigurationChanged($mikrotikDevice, $configHistory, 'update'));
    }

    /**
     * Handle connection details changes
     */
    private function handleConnectionDetailsChange(MikroTikDevice $mikrotikDevice): void
    {
        $changedFields = array_keys($mikrotikDevice->getChanges());
        $connectionFields = array_intersect($changedFields, ['ip_address', 'api_port', 'username', 'password_encrypted']);

        if (!empty($connectionFields)) {
            Log::info('MikroTik device connection details changed', [
                'device_id' => $mikrotikDevice->id,
                'device_name' => $mikrotikDevice->name,
                'changed_fields' => $connectionFields,
                'changed_by' => Auth::id(),
            ]);

            // Create system log entry
            SystemLog::create([
                'type' => 'mikrotik_connection_change',
                'data' => [
                    'device_id' => $mikrotikDevice->id,
                    'device_name' => $mikrotikDevice->name,
                    'changed_fields' => $connectionFields,
                    'changed_by' => Auth::id(),
                    'timestamp' => now(),
                ],
            ]);
        }
    }

    /**
     * Handle connection failures
     */
    private function handleConnectionFailure(MikroTikDevice $mikrotikDevice, string $oldStatus, string $newStatus): void
    {
        // Log connection failure
        Log::warning('MikroTik device connection failure', [
            'device_id' => $mikrotikDevice->id,
            'device_name' => $mikrotikDevice->name,
            'ip_address' => $mikrotikDevice->ip_address,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'last_seen' => $mikrotikDevice->last_seen,
        ]);

        // Create system log entry for connection failure
        SystemLog::create([
            'type' => 'mikrotik_connection_failure',
            'data' => [
                'device_id' => $mikrotikDevice->id,
                'device_name' => $mikrotikDevice->name,
                'ip_address' => $mikrotikDevice->ip_address,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'last_seen' => $mikrotikDevice->last_seen,
                'timestamp' => now(),
                'failure_reason' => $newStatus === 'error' ? 'Connection error' : 'Device offline',
            ],
        ]);

        // TODO: Could implement additional failure handling here:
        // - Send notifications to administrators
        // - Trigger automatic reconnection attempts
        // - Update dependent services
    }
}