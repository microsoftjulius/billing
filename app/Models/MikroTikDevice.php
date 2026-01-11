<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MikroTikDevice extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'ip_address',
        'location',
        'api_port',
        'username',
        'password_encrypted',
        'status',
        'last_seen',
        'uptime_seconds',
    ];

    protected $casts = [
        'location' => 'array',
        'last_seen' => 'datetime',
        'uptime_seconds' => 'integer',
        'api_port' => 'integer',
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
}