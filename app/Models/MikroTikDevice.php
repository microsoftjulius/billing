<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MikroTikDevice extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'mikrotik_devices';

    protected $fillable = [
        'name',
        'ip_address',
        'location',
        'api_port',
        'username',
        'password', // Add password to fillable so the mutator works
        'password_encrypted',
        'status',
        'last_seen',
        'uptime_seconds',
        'configuration',
        'backup_data',
    ];

    protected $casts = [
        'location' => 'array',
        'last_seen' => 'datetime',
        'uptime_seconds' => 'integer',
        'api_port' => 'integer',
        'configuration' => 'array',
        'backup_data' => 'array',
    ];

    protected $hidden = [
        'password_encrypted',
    ];

    /**
     * Get the decrypted password
     */
    public function getPasswordAttribute(): string
    {
        return decrypt($this->password_encrypted);
    }

    /**
     * Set the encrypted password
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password_encrypted'] = encrypt($value);
    }

    /**
     * Get vouchers associated with this device
     */
    public function vouchers()
    {
        return $this->hasMany(\App\Models\Voucher::class, 'mikrotik_device_id');
    }

    /**
     * Get configuration history for this device
     */
    public function configHistory()
    {
        return $this->hasMany(MikroTikConfigHistory::class, 'device_id');
    }

    /**
     * Get users for this device
     */
    public function mikrotikUsers()
    {
        return $this->hasMany(MikroTikUser::class, 'device_id');
    }

    /**
     * Scope for online devices
     */
    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    /**
     * Scope for offline devices
     */
    public function scopeOffline($query)
    {
        return $query->where('status', 'offline');
    }

    /**
     * Check if device is online
     */
    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    /**
     * Check if device is offline
     */
    public function isOffline(): bool
    {
        return $this->status === 'offline';
    }

    /**
     * Get formatted uptime
     */
    public function getFormattedUptimeAttribute(): string
    {
        if (!$this->uptime_seconds) {
            return 'Unknown';
        }

        $days = floor($this->uptime_seconds / 86400);
        $hours = floor(($this->uptime_seconds % 86400) / 3600);
        $minutes = floor(($this->uptime_seconds % 3600) / 60);

        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }

    /**
     * Create a configuration backup
     */
    public function createConfigBackup(array $configData, $userId): MikroTikConfigHistory
    {
        // Ensure userId is a valid UUID or null
        if ($userId === 'system' || !$userId) {
            $userId = null;
        }

        return $this->configHistory()->create([
            'configuration_data' => $configData,
            'change_type' => 'backup',
            'changed_by' => $userId,
        ]);
    }

    /**
     * Restore configuration from backup
     */
    public function restoreConfigFromBackup(string $historyId, $userId): bool
    {
        $backup = $this->configHistory()->find($historyId);
        
        if (!$backup) {
            return false;
        }

        // Ensure userId is a valid UUID or null
        if ($userId === 'system' || !$userId) {
            $userId = null;
        }

        // Update device configuration
        $this->update(['configuration' => $backup->configuration_data]);

        // Log the restore action
        $this->configHistory()->create([
            'configuration_data' => $backup->configuration_data,
            'change_type' => 'restore',
            'changed_by' => $userId,
        ]);

        return true;
    }

    /**
     * Update configuration and log the change
     */
    public function updateConfiguration(array $configData, $userId): bool
    {
        // Ensure userId is a valid UUID or null
        if ($userId === 'system' || !$userId) {
            // Create a system user ID or use null
            $userId = null;
        }

        // Store old configuration as backup if it exists
        if ($this->configuration) {
            $this->createConfigBackup($this->configuration, $userId);
        }

        // Update with new configuration
        $updated = $this->update(['configuration' => $configData]);

        // Log the update
        if ($updated) {
            $this->configHistory()->create([
                'configuration_data' => $configData,
                'change_type' => 'update',
                'changed_by' => $userId,
            ]);
        }

        return $updated;
    }

    /**
     * Get the latest configuration backup
     */
    public function getLatestBackup(): ?MikroTikConfigHistory
    {
        return $this->configHistory()
            ->where('change_type', 'backup')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Check if device has configuration
     */
    public function hasConfiguration(): bool
    {
        return !empty($this->configuration);
    }

    /**
     * Get active users count
     */
    public function getActiveUsersCountAttribute(): int
    {
        return $this->mikrotikUsers()->active()->count();
    }

    /**
     * Get total users count
     */
    public function getTotalUsersCountAttribute(): int
    {
        return $this->mikrotikUsers()->count();
    }
}