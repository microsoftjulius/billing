<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends BaseModel
{
    use SoftDeletes, HasFactory, HasUuid;

    protected $table = 'customers';

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'address',
        'id_type',
        'id_number',
        'date_of_birth',
        'gender',
        'is_active',
        'last_login_at',
        'metadata',
        'tenant_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'date_of_birth' => 'date',
        'metadata' => 'array'
    ];

    protected $appends = [
        'total_spent',
        'active_vouchers_count'
    ];

    /**
     * Relationships
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function latestVoucher(): HasOne
    {
        return $this->hasOne(Voucher::class)->latestOfMany();
    }

    /**
     * Scopes
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithActiveVouchers(Builder $query): Builder
    {
        return $query->whereHas('vouchers', function ($q) {
            $q->where('status', 'active')
                ->where('expires_at', '>', now());
        });
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")
                ->orWhere('phone', 'ilike', "%{$search}%")
                ->orWhere('id_number', 'ilike', "%{$search}%");
        });
    }

    /**
     * Accessors
     */
    public function getTotalSpentAttribute(): float
    {
        return $this->payments()
            ->where('status', 'completed')
            ->sum('amount') ?? 0;
    }

    public function getActiveVouchersCountAttribute(): int
    {
        return $this->vouchers()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->count();
    }

    public function getHasActiveVoucherAttribute(): bool
    {
        return $this->active_vouchers_count > 0;
    }

    /**
     * Business Logic
     */
    public function makeInactive(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function reactivate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function recordLogin(): bool
    {
        return $this->update(['last_login_at' => now()]);
    }
}
