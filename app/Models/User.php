<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Concerns\HasUuid;
// Assuming Tenant model exists in App\Models namespace
use App\Models\Tenant;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuid;

    protected $table = 'users';

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
        'last_login_at',
        'metadata',
        'tenant_id' // Added since tenant() relationship exists
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'metadata' => 'array'
    ];

    protected $appends = [
        'role_name',
        'is_admin',
        'is_staff'
    ];

    /**
     * Get the tenant that owns the user.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scopes
     */
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeStaff($query)
    {
        return $query->where('role', 'staff');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTenantUsers($query, $tenantId = null)
    {
        if ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        }
        return $query;
    }

    /**
     * Accessors
     */
    public function getRoleNameAttribute(): string
    {
        return match($this->role) {
            'admin' => 'Administrator',
            'staff' => 'Staff Member',
            default => ucfirst($this->role)
        };
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->role === 'admin';
    }

    public function getIsStaffAttribute(): bool
    {
        return $this->role === 'staff';
    }

    /**
     * Business Logic
     */
    public function markAsActive(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function markAsInactive(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function recordLogin(): bool
    {
        return $this->update(['last_login_at' => now()]);
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = match($this->role) {
            'admin' => ['view_payments', 'manage_vouchers', 'manage_customers', 'view_reports', 'system_settings'],
            'staff' => ['view_payments', 'manage_vouchers', 'view_customers'],
            default => []
        };

        return in_array($permission, $permissions);
    }

    /**
     * Override default tenant column if needed
     * Note: This seems to be for multi-tenancy. If using a package like stancl/tenancy,
     * you might need different implementation.
     */
    public function getTenantColumns(): array
    {
        return ['tenant_id'];
    }
}
