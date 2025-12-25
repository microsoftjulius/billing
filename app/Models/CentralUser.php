<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\HasUuid;

class CentralUser extends Authenticatable
{
    use HasFactory, Notifiable, HasUuid;

    protected $table = 'central_users';

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'password',
        'role',
        'is_super_admin',
        'phone',
        'is_active',
        'last_login_at',
        'metadata'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'is_super_admin' => 'boolean',
        'last_login_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Scopes
     */
    public function scopeSuperAdmins($query)
    {
        return $query->where('is_super_admin', true);
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if user can manage tenants
     */
    public function canManageTenants(): bool
    {
        return $this->is_super_admin || $this->role === 'admin';
    }

    /**
     * Get managed tenants
     */
    public function managedTenants()
    {
        $tenantIds = $this->metadata['managed_tenants'] ?? [];

        return Tenant::whereIn('id', $tenantIds)->get();
    }

    /**
     * Assign tenant to user for management
     */
    public function assignTenant(Tenant $tenant): bool
    {
        $managedTenants = $this->metadata['managed_tenants'] ?? [];

        if (!in_array($tenant->id, $managedTenants)) {
            $managedTenants[] = $tenant->id;

            $this->update([
                'metadata' => array_merge($this->metadata ?? [], [
                    'managed_tenants' => $managedTenants,
                ])
            ]);

            return true;
        }

        return false;
    }

    /**
     * Remove tenant from user management
     */
    public function removeTenant(Tenant $tenant): bool
    {
        $managedTenants = $this->metadata['managed_tenants'] ?? [];

        $key = array_search($tenant->id, $managedTenants);
        if ($key !== false) {
            unset($managedTenants[$key]);

            $this->update([
                'metadata' => array_merge($this->metadata ?? [], [
                    'managed_tenants' => array_values($managedTenants),
                ])
            ]);

            return true;
        }

        return false;
    }
}
