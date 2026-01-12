<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Voucher extends BaseModel
{
    use HasFactory;
    protected $table = 'vouchers';

    protected $fillable = [
        'uuid',
        'customer_id',
        'payment_id',
        'code',
        'password',
        'profile',
        'validity_hours',
        'data_limit_mb',
        'price',
        'currency',
        'status',
        'activated_at',
        'expires_at',
        'used_at',
        'sms_sent_at',
        'router_metadata',
        'usage_stats',
        'metadata',
        'tenant_id'
    ];

    protected $casts = [
        'validity_hours' => 'integer',
        'data_limit_mb' => 'integer',
        'price' => 'decimal:2',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'sms_sent_at' => 'datetime',
        'router_metadata' => 'array',
        'usage_stats' => 'array',
        'metadata' => 'array'
    ];

    protected $appends = [
        'is_expired',
        'is_active',
        'remaining_time',
        'formatted_price'
    ];

    /**
     * Relationships
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('status', 'expired')
                ->orWhere('expires_at', '<=', now());
        });
    }

    public function scopeUsed(Builder $query): Builder
    {
        return $query->where('status', 'used');
    }

    public function scopeDisabled(Builder $query): Builder
    {
        return $query->where('status', 'disabled');
    }

    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    public function scopeByProfile(Builder $query, string $profile): Builder
    {
        return $query->where('profile', $profile);
    }

    public function scopeExpiringSoon(Builder $query, int $hours = 24): Builder
    {
        return $query->whereBetween('expires_at', [
            now(),
            now()->addHours($hours)
        ]);
    }

    public function scopeNotSentViaSms(Builder $query): Builder
    {
        return $query->whereNull('sms_sent_at');
    }

    /**
     * Accessors
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active' && !$this->is_expired;
    }

    public function getRemainingTimeAttribute(): ?string
    {
        if (!$this->expires_at) {
            return null;
        }

        $diff = $this->expires_at->diff(now());

        if ($diff->days > 0) {
            return $diff->days . ' days ' . $diff->h . ' hours';
        }

        if ($diff->h > 0) {
            return $diff->h . ' hours ' . $diff->i . ' minutes';
        }

        return $diff->i . ' minutes';
    }

    public function getRemainingHoursAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return max(0, now()->diffInHours($this->expires_at, false));
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2);
    }

    public function getProfileNameAttribute(): string
    {
        $profiles = [
            'daily_1gb' => 'Daily 1GB',
            'weekly_5gb' => 'Weekly 5GB',
            'monthly_20gb' => 'Monthly 20GB',
            'unlimited_daily' => 'Unlimited Daily',
            'unlimited_weekly' => 'Unlimited Weekly',
        ];

        return $profiles[$this->profile] ?? ucfirst(str_replace('_', ' ', $this->profile));
    }

    public function getDataLimitFormattedAttribute(): ?string
    {
        if (!$this->data_limit_mb) {
            return null;
        }

        if ($this->data_limit_mb >= 1024) {
            return round($this->data_limit_mb / 1024, 1) . ' GB';
        }

        return $this->data_limit_mb . ' MB';
    }

    /**
     * Business Logic
     */
    public function activate(): bool
    {
        return $this->update([
            'status' => 'active',
            'activated_at' => now(),
            'expires_at' => now()->addHours($this->validity_hours)
        ]);
    }

    public function markAsUsed(): bool
    {
        return $this->update([
            'status' => 'used',
            'used_at' => now()
        ]);
    }

    public function markAsExpired(): bool
    {
        return $this->update([
            'status' => 'expired'
        ]);
    }

    public function disable(): bool
    {
        return $this->update([
            'status' => 'disabled'
        ]);
    }

    public function markSmsSent(): bool
    {
        return $this->update([
            'sms_sent_at' => now()
        ]);
    }

    public function recordUsage(array $stats): bool
    {
        $currentStats = $this->usage_stats ?? [];

        return $this->update([
            'usage_stats' => array_merge($currentStats, $stats, [
                'last_updated' => now()->toISOString()
            ])
        ]);
    }

    public function isUsable(): bool
    {
        return $this->is_active &&
            !$this->used_at &&
            !$this->is_expired;
    }

    public function renew(int $additionalHours): bool
    {
        $newExpiry = $this->expires_at->addHours($additionalHours);

        return $this->update([
            'expires_at' => $newExpiry,
            'validity_hours' => $this->validity_hours + $additionalHours
        ]);
    }

    public function generateCode(): string
    {
        do {
            // Format: BIL-XXXX-XXXX where X is alphanumeric
            $code = 'BIL-' . strtoupper(\Illuminate\Support\Str::random(4)) . '-' . strtoupper(\Illuminate\Support\Str::random(4));
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
