<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MikroTikConfigHistory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'mikrotik_config_history';

    protected $fillable = [
        'device_id',
        'configuration_data',
        'change_type',
        'changed_by',
    ];

    protected $casts = [
        'configuration_data' => 'array',
    ];

    /**
     * Get the device that owns this configuration history
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(MikroTikDevice::class, 'device_id');
    }

    /**
     * Get the user who made this change
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Scope for backup entries
     */
    public function scopeBackups($query)
    {
        return $query->where('change_type', 'backup');
    }

    /**
     * Scope for restore entries
     */
    public function scopeRestores($query)
    {
        return $query->where('change_type', 'restore');
    }

    /**
     * Scope for update entries
     */
    public function scopeUpdates($query)
    {
        return $query->where('change_type', 'update');
    }

    /**
     * Get the latest configuration for a device
     */
    public static function getLatestConfigForDevice(string $deviceId): ?self
    {
        return static::where('device_id', $deviceId)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get configuration history for a device
     */
    public static function getHistoryForDevice(string $deviceId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('device_id', $deviceId)
            ->with('changedBy:id,name')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}