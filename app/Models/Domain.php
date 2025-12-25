<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

class Domain extends BaseDomain
{
    protected $fillable = [
        'domain',
        'tenant_id',
        'is_primary',
        'is_fallback',
        'is_redirect',
        'redirect_to',
        'ssl_status',
        'certificate_expires_at',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_fallback' => 'boolean',
        'is_redirect' => 'boolean',
        'certificate_expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the tenant that owns the domain.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope for primary domains
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Check if SSL is valid
     */
    public function hasValidSsl(): bool
    {
        if (!$this->certificate_expires_at) {
            return false;
        }

        return $this->certificate_expires_at->isFuture();
    }

    /**
     * Get SSL status
     */
    public function getSslStatusAttribute(): string
    {
        if ($this->ssl_status) {
            return $this->ssl_status;
        }

        return $this->hasValidSsl() ? 'valid' : 'invalid';
    }
}
