<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, HasFactory;

    protected $fillable = [
        'id',
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'logo',
        'is_active',
        'plan',
        'max_users',
        'max_vouchers_per_day',
        'data_retention_days',
        'billing_cycle',
        'next_billing_date',
        'metadata',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_users' => 'integer',
        'max_vouchers_per_day' => 'integer',
        'data_retention_days' => 'integer',
        'next_billing_date' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the columns that should be used for the tenant's ID.
     */
    public function getTenantKeyName(): string
    {
        return 'id';
    }

    /**
     * Get the actual value of the tenant's ID.
     */
    public function getTenantKey()
    {
        return $this->getAttribute($this->getTenantKeyName());
    }

    /**
     * Get the columns that should be selected when querying the tenant.
     */
    public function getTenantColumns(): array
    {
        return ['id'];
    }

    /**
     * Get the tenant's custom columns.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'email',
            'phone',
            'address',
            'logo',
            'is_active',
            'plan',
            'max_users',
            'max_vouchers_per_day',
            'data_retention_days',
            'billing_cycle',
            'next_billing_date',
            'metadata',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Scope for active tenants
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific plan
     */
    public function scopePlan($query, $plan)
    {
        return $query->where('plan', $plan);
    }

    /**
     * Check if tenant can create more users
     */
    public function canCreateUser(): bool
    {
        $currentUsers = $this->users()->count();
        return $currentUsers < $this->max_users;
    }

    /**
     * Check if tenant can create more vouchers today
     */
    public function canCreateVoucher(): bool
    {
        $todayVouchers = $this->run(function () {
            return \App\Models\Voucher::whereDate('created_at', today())->count();
        });

        return $todayVouchers < $this->max_vouchers_per_day;
    }

    /**
     * Get tenant's usage statistics
     */
    public function getUsageStatistics(): array
    {
        return $this->run(function () {
            $today = today();
            $monthStart = now()->startOfMonth();

            return [
                'users' => [
                    'total' => \App\Models\User::count(),
                    'limit' => $this->max_users,
                    'remaining' => $this->max_users - \App\Models\User::count(),
                ],
                'vouchers_today' => [
                    'total' => \App\Models\Voucher::whereDate('created_at', $today)->count(),
                    'limit' => $this->max_vouchers_per_day,
                    'remaining' => $this->max_vouchers_per_day - \App\Models\Voucher::whereDate('created_at', $today)->count(),
                ],
                'payments_month' => [
                    'total' => \App\Models\Payment::where('created_at', '>=', $monthStart)
                        ->where('status', 'completed')
                        ->count(),
                    'revenue' => \App\Models\Payment::where('created_at', '>=', $monthStart)
                        ->where('status', 'completed')
                        ->sum('amount'),
                ],
                'storage' => [
                    'used_mb' => 0, // Implement storage calculation
                    'limit_mb' => $this->metadata['storage_limit_mb'] ?? 1024, // 1GB default
                ],
            ];
        });
    }

    /**
     * Suspend tenant
     */
    public function suspend(string $reason = null): bool
    {
        $this->update([
            'is_active' => false,
            'metadata' => array_merge($this->metadata ?? [], [
                'suspended_at' => now()->toISOString(),
                'suspension_reason' => $reason,
            ])
        ]);

        return true;
    }

    /**
     * Activate tenant
     */
    public function activate(): bool
    {
        $this->update([
            'is_active' => true,
            'metadata' => array_merge($this->metadata ?? [], [
                'activated_at' => now()->toISOString(),
                'suspended_at' => null,
                'suspension_reason' => null,
            ])
        ]);

        return true;
    }

    /**
     * Update tenant plan
     */
    public function updatePlan(string $plan, array $features): bool
    {
        $this->update([
            'plan' => $plan,
            'max_users' => $features['max_users'] ?? 10,
            'max_vouchers_per_day' => $features['max_vouchers_per_day'] ?? 100,
            'data_retention_days' => $features['data_retention_days'] ?? 365,
            'metadata' => array_merge($this->metadata ?? [], [
                'plan_features' => $features,
                'plan_updated_at' => now()->toISOString(),
            ])
        ]);

        return true;
    }
}
