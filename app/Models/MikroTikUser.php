<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MikroTikUser extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'mikrotik_users';

    protected $fillable = [
        'device_id',
        'username',
        'password',
        'profile',
        'voucher_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the device that owns this user
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(MikroTikDevice::class, 'device_id');
    }

    /**
     * Get the voucher associated with this user
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for inactive users
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope for users with vouchers
     */
    public function scopeWithVoucher($query)
    {
        return $query->whereNotNull('voucher_id');
    }

    /**
     * Scope for users without vouchers
     */
    public function scopeWithoutVoucher($query)
    {
        return $query->whereNull('voucher_id');
    }

    /**
     * Activate the user
     */
    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    /**
     * Deactivate the user
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get users for a specific device
     */
    public static function getUsersForDevice(string $deviceId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('device_id', $deviceId)
            ->with('voucher')
            ->orderBy('username')
            ->get();
    }

    /**
     * Find user by device and username
     */
    public static function findByDeviceAndUsername(string $deviceId, string $username): ?self
    {
        return static::where('device_id', $deviceId)
            ->where('username', $username)
            ->first();
    }
}